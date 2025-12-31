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
                // Log::info('Firebase Messaging initialized successfully', [
                //     'credentials_file' => basename($this->credentialsPath)
                // ]);
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
            Log::warning('FCM messaging not configured', [
                'credentials_path' => $this->credentialsPath,
                'file_exists' => $this->credentialsPath ? file_exists($this->credentialsPath) : false,
            ]);
            return false;
        }

        $tokens = DeviceToken::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        if ($tokens->isEmpty()) {
            Log::info("No active device tokens found for user: {$userId}");
            return false;
        }

        Log::info("Sending FCM to user {$userId}", [
            'token_count' => $tokens->count(),
            'title' => $title,
            'tokens_preview' => $tokens->map(fn($t) => [
                'id' => $t->id,
                'type' => $t->device_type,
                'preview' => substr($t->token, 0, 20) . '...',
            ])->toArray(),
        ]);

        return $this->sendToTokens($tokens->pluck('token')->toArray(), $title, $body, $data);
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
                        'channel_id' => 'luky_notifications_v4',
                        'icon' => 'ic_notification',
                        'color' => '#9C27B0',
                        'sound' => 'default',
                        'default_vibrate_timings' => true,
                        'default_light_settings' => true,
                        'visibility' => 'public',
                        'notification_priority' => 'PRIORITY_HIGH',
                        'tag' => 'luky_notification',
                    ],
                ])
                ->withApnsConfig([
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                        ],
                    ],
                ]);

            $report = $this->messaging->sendMulticast($message, $tokens);

            // Get failures and successes (store once to avoid iterator consumption issues)
            $failures = $report->failures();
            $successes = $report->successes();

            $successCount = $successes->count();
            $failureCount = $failures->count();

            Log::info('FCM notification sent', [
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'total_tokens' => count($tokens),
            ]);

            // Log failures for debugging
            if ($failureCount > 0) {
                Log::warning("FCM has {$failureCount} failures. Detailed errors:");

                try {
                    $failureDetails = [];
                    $failureItems = $failures->getItems();

                    foreach ($failureItems as $index => $failure) {
                        try {
                            $error = $failure->error();
                            $target = $failure->target();

                            // Extract error details
                            $errorCode = 'UNKNOWN';
                            $errorMsg = 'Unknown error';

                            if (is_object($error)) {
                                // Firebase SDK uses errorCode() and getMessage() methods
                                if (method_exists($error, 'errorCode')) {
                                    $errorCode = $error->errorCode();
                                } elseif (method_exists($error, 'getCode')) {
                                    $errorCode = $error->getCode();
                                }

                                if (method_exists($error, 'getMessage')) {
                                    $errorMsg = $error->getMessage();
                                }
                            } else {
                                $errorMsg = (string) $error;
                            }

                            $tokenValue = 'Unknown';
                            if (is_object($target) && method_exists($target, 'value')) {
                                $tokenValue = $target->value();
                            } elseif (is_string($target)) {
                                $tokenValue = $target;
                            }

                            $failureDetail = [
                                'index' => $index,
                                'token_preview' => substr($tokenValue, 0, 50) . '...',
                                'error_code' => $errorCode,
                                'error_message' => $errorMsg,
                            ];

                            $failureDetails[] = $failureDetail;

                            Log::error('FCM Token Failed', $failureDetail);
                        } catch (\Throwable $innerEx) {
                            Log::error('Error extracting failure details', [
                                'index' => $index,
                                'exception' => $innerEx->getMessage(),
                                'trace' => $innerEx->getTraceAsString(),
                            ]);
                        }
                    }

                    Log::warning('FCM Failure Summary', [
                        'total_failures' => count($failureDetails),
                        'failures' => $failureDetails,
                    ]);
                } catch (\Throwable $ex) {
                    Log::error('Error processing FCM failures', [
                        'exception' => $ex->getMessage(),
                        'trace' => $ex->getTraceAsString(),
                    ]);
                }
            }

            if ($successCount > 0) {
                Log::info("FCM successfully sent to {$successCount} devices");
            }

            // Handle invalid tokens
            $this->handleInvalidTokens($failures);

            return $successCount > 0;
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
                ->withData($data)
                ->withAndroidConfig([
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'luky_notifications_v4',
                        'icon' => 'ic_notification',
                        'color' => '#9C27B0',
                        'sound' => 'default',
                        'default_vibrate_timings' => true,
                        'default_light_settings' => true,
                        'visibility' => 'public',
                        'notification_priority' => 'PRIORITY_HIGH',
                        'tag' => 'luky_notification',
                    ],
                ])
                ->withApnsConfig([
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                        ],
                    ],
                ]);

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
    protected function handleInvalidTokens($failures): void
    {
        try {
            $failureItems = $failures->getItems();

            foreach ($failureItems as $failure) {
                try {
                    $error = $failure->error();
                    $target = $failure->target();

                    $token = null;
                    if (is_object($target) && method_exists($target, 'value')) {
                        $token = $target->value();
                    } elseif (is_string($target)) {
                        $token = $target;
                    }

                    if (!$token) {
                        continue;
                    }

                    // Extract error code and message safely
                    $errorCode = null;
                    $errorMessage = '';

                    if (is_object($error)) {
                        if (method_exists($error, 'errorCode')) {
                            $errorCode = $error->errorCode();
                        } elseif (method_exists($error, 'getCode')) {
                            $errorCode = $error->getCode();
                        }

                        if (method_exists($error, 'getMessage')) {
                            $errorMessage = $error->getMessage();
                        }
                    }

                    // Deactivate invalid tokens based on error code or message
                    $invalidErrors = ['INVALID_ARGUMENT', 'NOT_FOUND', 'UNREGISTERED'];
                    $invalidMessages = ['not found', 'invalid registration', 'unregistered'];

                    $shouldDeactivate = ($errorCode && in_array($errorCode, $invalidErrors)) ||
                                       ($errorMessage && str_contains(strtolower($errorMessage), 'not found')) ||
                                       ($errorMessage && str_contains(strtolower($errorMessage), 'invalid registration')) ||
                                       ($errorMessage && str_contains(strtolower($errorMessage), 'unregistered'));

                    if ($shouldDeactivate) {
                        DeviceToken::where('token', $token)->update(['is_active' => false]);
                        Log::info("Deactivated invalid FCM token", [
                            'token_preview' => substr($token, 0, 20) . '...',
                            'error_code' => $errorCode,
                            'error_message' => $errorMessage,
                        ]);
                    }
                } catch (\Throwable $ex) {
                    Log::warning('Error handling invalid token', [
                        'exception' => $ex->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $ex) {
            Log::error('Error processing invalid tokens', [
                'exception' => $ex->getMessage(),
                'trace' => $ex->getTraceAsString(),
            ]);
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