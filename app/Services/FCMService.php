<?php

namespace App\Services;

use App\Models\DeviceToken;
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
                Log::info('Firebase Messaging initialized successfully', [
                    'credentials_file' => basename($this->credentialsPath)
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to initialize Firebase Messaging: ' . $e->getMessage());
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
                        'channel_id' => 'booking_notifications',
                        'sound' => 'default',
                        'priority' => 'high',
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

                $failures = $report->failures();
                Log::warning("Failures type: " . get_class($failures));

                $failures->getItems(); // Force iterator to load

                foreach ($failures as $index => $failure) {
                    Log::warning("Processing failure index: {$index}");

                    try {
                        $error = $failure->error();
                        $errorCode = method_exists($error, 'errorCode') ? $error->errorCode() : 'UNKNOWN';
                        $errorMsg = method_exists($error, 'getMessage') ? $error->getMessage() : 'Unknown error';

                        $target = $failure->target();
                        $tokenValue = method_exists($target, 'value') ? $target->value() : 'Unknown';

                        Log::error('FCM FAILURE FOUND', [
                            'index' => $index,
                            'token_preview' => substr($tokenValue, 0, 50),
                            'error_code' => $errorCode,
                            'error_message' => $errorMsg,
                        ]);
                    } catch (\Throwable $innerEx) {
                        Log::error("Error in failure loop: " . $innerEx->getMessage());
                    }
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
        foreach ($report->failures() as $failure) {
            $error = $failure->error();
            $token = $failure->target()->value();
            
            // Deactivate invalid tokens
            if (in_array($error->errorCode(), ['INVALID_ARGUMENT', 'NOT_FOUND', 'UNREGISTERED']) && $token) {
                DeviceToken::where('token', $token)->update(['is_active' => false]);
                Log::info("Deactivated invalid FCM token: {$token}");
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
}