<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Jobs\SendFCMTopicNotificationJob;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;

class FCMService
{
    protected $messaging;
    protected ?string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = config('services.fcm.credentials_path', env('FCM_CREDENTIALS_PATH'));
        
        // Handle relative paths from project root
        if ($this->credentialsPath && !file_exists($this->credentialsPath)) {
            // Try as relative path from base
            $absolutePath = base_path($this->credentialsPath);
            if (file_exists($absolutePath)) {
                $this->credentialsPath = $absolutePath;
            }
        }
        
        if ($this->credentialsPath && file_exists($this->credentialsPath)) {
            try {
                $this->messaging = (new Factory)
                    ->withServiceAccount($this->credentialsPath)
                    ->createMessaging();
                // Log::info('Firebase Messaging initialized successfully', [
                //     'credentials_file' => basename($this->credentialsPath)
                // ]);
            } catch (\Exception $e) {
                // Log::error('Failed to initialize Firebase Messaging: ' . $e->getMessage());
                $this->messaging = null;
            }
        } else {
            Log::warning('FCM credentials path not configured or file not found', [
                'path' => $this->credentialsPath,
                'absolute_path' => $this->credentialsPath ? base_path($this->credentialsPath) : null,
            ]);
            $this->messaging = null;
        }
    }

    /**
     * Send notification to a specific user
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): bool
    {
        if (!$this->messaging) {
            Log::warning('FCM messaging not configured');
            return false;
        }

        $tokens = DeviceToken::where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('token')
            ->toArray();

        if (empty($tokens)) {
            Log::info("No device tokens found for user: {$userId}");
            return false;
        }

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Send notification to multiple device tokens
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): bool
    {
        if (!$this->messaging || empty($tokens)) {
            Log::warning('FCM messaging not configured or no tokens provided');
            return false;
        }

        try {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($data)
                ->withAndroidConfig([
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'luky_notifications',
                        'sound' => 'default',
                        'default_vibrate_timings' => true,
                    ],
                ])
                ->withApnsConfig([
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ]);

            $report = $this->messaging->sendMulticast($message, $tokens);

            $successCount = $report->successes()->count();
            $failureCount = $report->failures()->count();

            Log::info('FCM notification sent', [
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'total_tokens' => count($tokens),
            ]);

            // Log failures for debugging
            if ($failureCount > 0) {
                Log::warning("FCM has {$failureCount} failures. Processing...");

                try {
                    $failuresArray = $report->failures()->getItems();

                    foreach ($failuresArray as $index => $failure) {
                        try {
                            $error = $failure->error();
                            $target = $failure->target();

                            $errorCode = 'UNKNOWN';
                            $errorMsg = 'Unknown error';
                            $tokenValue = 'Unknown';

                            // Safely extract error code
                            if (is_object($error)) {
                                if (method_exists($error, 'getCode')) {
                                    $errorCode = $error->getCode();
                                } elseif (method_exists($error, 'errorCode')) {
                                    $errorCode = $error->errorCode();
                                }

                                if (method_exists($error, 'getMessage')) {
                                    $errorMsg = $error->getMessage();
                                }
                            }

                            // Safely extract token
                            if (is_object($target)) {
                                if (method_exists($target, 'value')) {
                                    $tokenValue = $target->value();
                                } elseif (method_exists($target, 'getValue')) {
                                    $tokenValue = $target->getValue();
                                }
                            }

                            Log::error('FCM SEND FAILURE', [
                                'index' => $index,
                                'token_preview' => substr($tokenValue, 0, 50) . '...',
                                'error_code' => $errorCode,
                                'error_message' => $errorMsg,
                            ]);
                        } catch (\Throwable $innerEx) {
                            Log::error("Error processing failure at index {$index}: " . $innerEx->getMessage());
                        }
                    }
                } catch (\Throwable $ex) {
                    Log::error("Error processing FCM failures: " . $ex->getMessage());
                }
            }

            if ($successCount > 0) {
                Log::info("FCM successfully sent to {$successCount} devices");
            }

            // Handle invalid tokens
            $this->handleInvalidTokens($report);

            return $report->successes()->count() > 0;
        } catch (MessagingException $e) {
            Log::error('FCM messaging exception: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('FCM exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification to a topic
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): bool
    {
        if (!$this->messaging) {
            Log::warning('FCM messaging not configured');
            return false;
        }

        try {
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            return true;
        } catch (MessagingException $e) {
            Log::error('FCM topic notification exception: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('FCM topic notification exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Handle invalid/expired tokens
     */
    protected function handleInvalidTokens($report): void
    {
        $failuresArray = $report->failures()->getItems();

        foreach ($failuresArray as $failure) {
            try {
                $error = $failure->error();
                $target = $failure->target();

                if (!$target || !method_exists($target, 'value')) {
                    Log::warning('Invalid target in failure, skipping');
                    continue;
                }

                $token = $target->value();
                $errorMsg = method_exists($error, 'getMessage') ? $error->getMessage() : '';
                $errorCode = method_exists($error, 'errorCode') ? $error->errorCode() : null;

                // Check for various invalid token conditions
                $shouldDeactivate = false;

                // Check by error code
                if ($errorCode && in_array($errorCode, ['INVALID_ARGUMENT', 'NOT_FOUND', 'UNREGISTERED'])) {
                    $shouldDeactivate = true;
                }

                // Check by error message for "not found" type errors
                if (stripos($errorMsg, 'not found') !== false ||
                    stripos($errorMsg, 'unregistered') !== false ||
                    stripos($errorMsg, 'invalid registration') !== false) {
                    $shouldDeactivate = true;
                }

                // Deactivate invalid tokens
                if ($shouldDeactivate && $token) {
                    $updated = DeviceToken::where('token', $token)->update(['is_active' => false]);
                    if ($updated > 0) {
                        Log::info("Deactivated invalid FCM token", [
                            'token_preview' => substr($token, 0, 50) . '...',
                            'error_code' => $errorCode,
                            'error_message' => $errorMsg,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Error handling invalid token: ' . $e->getMessage());
            }
        }
    }

    /**
     * Subscribe token to topic
     */
    public function subscribeToTopic(string $token, string $topic): bool
    {
        if (!$this->messaging) {
            Log::warning('FCM messaging not configured');
            return false;
        }

        try {
            $this->messaging->subscribeToTopic($topic, $token);
            return true;
        } catch (MessagingException $e) {
            Log::error('FCM topic subscription failed: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('FCM topic subscription failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Unsubscribe token from topic
     */
    public function unsubscribeFromTopic(string $token, string $topic): bool
    {
        if (!$this->messaging) {
            Log::warning('FCM messaging not configured');
            return false;
        }

        try {
            $this->messaging->unsubscribeFromTopic($topic, $token);
            return true;
        } catch (MessagingException $e) {
            Log::error('FCM topic unsubscription failed: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            Log::error('FCM topic unsubscription failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send payment completed notification to booking topic
     * Uses user-specific topic for security: user_{userId}_booking_{bookingId}_payment
     *
     * @param \App\Models\Booking $booking
     * @param \App\Models\Payment $payment
     * @return bool
     */
    public function sendPaymentCompleted($booking, $payment): bool
    {
        $userId = $booking->client_id;
        $bookingId = $booking->id;
        $topic = "user_{$userId}_booking_{$bookingId}_payment";

        $title = 'Payment Successful';
        $body = "Your payment of {$payment->amount} SAR has been confirmed for booking #{$bookingId}";

        $data = [
            'event' => 'payment_completed',
            'booking_id' => (string) $bookingId,
            'payment_id' => (string) $payment->id,
            'amount' => (string) $payment->amount,
            'status' => 'completed',
            'timestamp' => now()->toIso8601String(),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            // Tell mobile app to dismiss the old "Pay Now" notification
            'dismiss_notification_type' => 'booking_accepted',
            'dismiss_booking_id' => (string) $bookingId,
            // Additional flag for clarity
            'action' => 'dismiss_payment_notification',
            'refresh_notifications' => 'true',
        ];

        Log::info("Dispatching payment completed FCM job (2s delay) to topic: {$topic}", [
            'booking_id' => $bookingId,
            'payment_id' => $payment->id,
            'amount' => $payment->amount,
        ]);

        // Dispatch job with 2-second delay (non-blocking, allows mobile to subscribe)
        SendFCMTopicNotificationJob::dispatch($topic, $title, $body, $data)
            ->delay(now()->addSeconds(2));

        return true;
    }

    /**
     * Send payment failed notification to booking topic
     *
     * @param \App\Models\Booking $booking
     * @param \App\Models\Payment $payment
     * @param string $errorMessage
     * @return bool
     */
    public function sendPaymentFailed($booking, $payment, string $errorMessage = 'Payment processing failed'): bool
    {
        $userId = $booking->client_id;
        $bookingId = $booking->id;
        $topic = "user_{$userId}_booking_{$bookingId}_payment";

        $title = 'Payment Failed';
        $body = "Payment for booking #{$bookingId} could not be processed. Please try again.";

        $data = [
            'event' => 'payment_failed',
            'booking_id' => (string) $bookingId,
            'payment_id' => (string) $payment->id,
            'amount' => (string) $payment->amount,
            'status' => 'failed',
            'error' => $errorMessage,
            'timestamp' => now()->toIso8601String(),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        Log::info("Dispatching payment failed FCM job (2s delay) to topic: {$topic}", [
            'booking_id' => $bookingId,
            'payment_id' => $payment->id,
            'error' => $errorMessage,
        ]);

        // Dispatch job with 2-second delay (non-blocking, allows mobile to subscribe)
        SendFCMTopicNotificationJob::dispatch($topic, $title, $body, $data)
            ->delay(now()->addSeconds(2));

        return true;
    }

    /**
     * Send payment timeout notification to booking topic
     *
     * @param \App\Models\Booking $booking
     * @param \App\Models\Payment $payment
     * @return bool
     */
    public function sendPaymentTimeout($booking, $payment): bool
    {
        $userId = $booking->client_id;
        $bookingId = $booking->id;
        $topic = "user_{$userId}_booking_{$bookingId}_payment";

        $title = 'Payment Timeout';
        $body = "Payment time expired for booking #{$bookingId}. Please request a new payment link.";

        $data = [
            'event' => 'payment_timeout',
            'booking_id' => (string) $bookingId,
            'payment_id' => (string) $payment->id,
            'amount' => (string) $payment->amount,
            'status' => 'timeout',
            'timestamp' => now()->toIso8601String(),
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];

        Log::info("Sending payment timeout FCM to topic: {$topic}", [
            'booking_id' => $bookingId,
            'payment_id' => $payment->id,
        ]);

        return $this->sendToTopic($topic, $title, $body, $data);
    }
}