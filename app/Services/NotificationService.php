<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Booking;
use App\Models\User;
use App\Jobs\SendFCMNotificationJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    protected FCMService $fcmService;

    public function __construct(FCMService $fcmService)
    {
        $this->fcmService = $fcmService;
    }

    /**
     * Get bilingual text (EN | AR) from translation keys
     */
    private function getBilingualText(string $key, array $replacements = []): string
    {
        $en = __('notifications.' . $key, $replacements, 'en');
        $ar = __('notifications.' . $key, $replacements, 'ar');

        return "{$en}\n{$ar}";
    }

    /**
     * Get title in bilingual format (EN | AR)
     */
    private function getBilingualTitle(string $key): string
    {
        return __('notifications.' . $key, [], 'en');
    }

    /**
     * Send a notification
     */
    public function send(int $userId, string $type, string $title, string $body, array $data = []): ?Notification
    {
        try {
            // Create in-app notification
            $notification = Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'is_read' => false,
                'is_sent' => false,
            ]);

            // Dispatch FCM notification job asynchronously (non-blocking)
            SendFCMNotificationJob::dispatch($userId, $title, $body, array_merge($data, [
                'notification_id' => $notification->id,
                'type' => $type,
            ]));

            // Mark as sent immediately (job will be processed by queue worker)
            $notification->markAsSent();

            // Clear unread count cache for this user
            Cache::forget("user:{$userId}:unread_notifications_count");

            return $notification;
        } catch (\Exception $e) {
            Log::error('Notification send failed: ' . $e->getMessage(), [
                'user_id' => $userId,
                'type' => $type,
            ]);
            return null;
        }
    }

    /**
     * Send booking request notification to provider
     */
    public function sendBookingRequest(Booking $booking): void
    {
        $provider = $booking->provider;
        $client = $booking->client;

        $this->send(
            $provider->user_id,
            'booking_request',
            'New Booking Request',
            "{$client->name} has requested a booking for {$booking->booking_date->format('M d, Y')} at {$booking->start_time->format('h:i A')}",
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'client_name' => $client->name,
                'booking_date' => $booking->booking_date->toDateString(),
                'start_time' => $booking->start_time->format('H:i'),
            ]
        );
    }

    /**
     * Send booking accepted notification to client
     */
    public function sendBookingAccepted(Booking $booking): void
    {
        Log::info('=== SENDING BOOKING ACCEPTED NOTIFICATION ===');
        Log::info('Booking ID: ' . $booking->id);
        Log::info('Client ID: ' . $booking->client_id);

        $provider = $booking->provider;
        $timeoutMinutes = \App\Models\AppSetting::get('payment_timeout_minutes', 5);

        $title = $this->getBilingualTitle('messages.booking_accepted_title');
        $body = $this->getBilingualText('messages.booking_accepted_body', [
            'provider' => $provider->business_name,
            'timeout' => $timeoutMinutes,
        ]) . "\n" . $this->getBilingualText('messages.booking_accepted_body_ar', [
            'provider' => $provider->business_name,
            'timeout' => $timeoutMinutes,
        ]);

        $this->send(
            $booking->client_id,
            'booking_accepted',
            $title,
            $body,
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'provider_name' => $provider->business_name,
                'total_amount' => $booking->total_amount,
                'payment_status' => $booking->payment_status,
                'timeout_minutes' => $timeoutMinutes,
                'confirmed_at' => $booking->confirmed_at?->toIso8601String(),
            ]
        );

        Log::info('=== BOOKING ACCEPTED NOTIFICATION SENT ===');
    }

    /**
     * Send booking rejected notification to client
     */
    public function sendBookingRejected(Booking $booking): void
    {
        $provider = $booking->provider;

        // Bilingual message
        $title = 'Booking Rejected | تم رفض الحجز';
        $body = "{$provider->business_name} has rejected your booking request." .
                ($booking->cancellation_reason ? " Reason: {$booking->cancellation_reason}" : '') . "\n" .
                "رفض {$provider->business_name} طلب حجزك." .
                ($booking->cancellation_reason ? " السبب: {$booking->cancellation_reason}" : '');

        $this->send(
            $booking->client_id,
            'booking_rejected',
            $title,
            $body,
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'provider_name' => $provider->business_name,
                'reason' => $booking->cancellation_reason,
            ]
        );
    }

    /**
     * Send booking cancelled notification
     */
    public function sendBookingCancelled(Booking $booking, string $recipientType = 'both'): void
    {
        $provider = $booking->provider;
        $client = $booking->client;

        if (in_array($recipientType, ['client', 'both'])) {
            // Bilingual message for client
            $title = 'Booking Cancelled | تم إلغاء الحجز';
            $body = "Your booking with {$provider->business_name} has been cancelled." .
                    ($booking->cancellation_reason ? " Reason: {$booking->cancellation_reason}" : '') . "\n" .
                    "تم إلغاء حجزك مع {$provider->business_name}." .
                    ($booking->cancellation_reason ? " السبب: {$booking->cancellation_reason}" : '');

            $this->send(
                $booking->client_id,
                'booking_cancelled',
                $title,
                $body,
                [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'cancelled_by' => $booking->cancelled_by,
                    'reason' => $booking->cancellation_reason,
                ]
            );
        }

        if (in_array($recipientType, ['provider', 'both'])) {
            // Bilingual message for provider
            $title = 'Booking Cancelled | تم إلغاء الحجز';
            $body = "Booking from {$client->name} has been cancelled." .
                    ($booking->cancellation_reason ? " Reason: {$booking->cancellation_reason}" : '') . "\n" .
                    "تم إلغاء الحجز من {$client->name}." .
                    ($booking->cancellation_reason ? " السبب: {$booking->cancellation_reason}" : '');

            $this->send(
                $provider->user_id,
                'booking_cancelled',
                $title,
                $body,
                [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'cancelled_by' => $booking->cancelled_by,
                    'reason' => $booking->cancellation_reason,
                ]
            );
        }
    }

    /**
     * Send payment reminder notification to client
     */
    public function sendPaymentReminder(Booking $booking, int $minutesRemaining): void
    {
        $provider = $booking->provider;

        // Bilingual message
        $title = 'Payment Reminder | تذكير بالدفع';
        $body = "Only {$minutesRemaining} minutes left to complete payment for your booking with {$provider->business_name}\n" .
                "باقي {$minutesRemaining} دقيقة فقط لإتمام الدفع لحجزك مع {$provider->business_name}";

        $this->send(
            $booking->client_id,
            'payment_reminder',
            $title,
            $body,
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'minutes_remaining' => $minutesRemaining,
                'total_amount' => $booking->total_amount,
            ]
        );
    }

    /**
     * Send payment success notification
     */
    public function sendPaymentSuccess(Booking $booking): void
    {
        $provider = $booking->provider;
        $client = $booking->client;

        // Notify client - Bilingual
        $clientTitle = 'Payment Successful | تم الدفع بنجاح';
        $clientBody = "Your payment of {$booking->total_amount} SAR has been processed successfully for booking with {$provider->business_name}\n" .
                      "تم معالجة دفعتك بقيمة {$booking->total_amount} ريال بنجاح لحجزك مع {$provider->business_name}";

        $this->send(
            $booking->client_id,
            'payment_success',
            $clientTitle,
            $clientBody,
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'amount' => $booking->total_amount,
            ]
        );

        // Notify provider - Bilingual
        $providerTitle = 'Payment Received | تم استلام الدفع';
        $providerBody = "Payment received for booking from {$client->name}. Amount: {$booking->total_amount} SAR\n" .
                        "تم استلام الدفع لحجز من {$client->name}. المبلغ: {$booking->total_amount} ريال";

        $this->send(
            $provider->user_id,
            'payment_success',
            $providerTitle,
            $providerBody,
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'amount' => $booking->total_amount,
                'client_name' => $client->name,
            ]
        );
    }

    /**
     * Send payment failed notification to client
     */
    public function sendPaymentFailed(Booking $booking, string $reason = ''): void
    {
        // Bilingual message
        $title = 'Payment Failed | فشل الدفع';
        $body = "Payment for your booking failed." . ($reason ? " Reason: {$reason}" : ' Please try again.') . "\n" .
                "فشل الدفع لحجزك." . ($reason ? " السبب: {$reason}" : ' يرجى المحاولة مرة أخرى.');

        $this->send(
            $booking->client_id,
            'payment_failed',
            $title,
            $body,
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Send refund processed notification to client
     */
    public function sendRefundProcessed(Booking $booking, float $refundAmount, float $cancellationFee): void
    {
        $this->send(
            $booking->client_id,
            'refund_processed',
            'Refund Processed',
            "Your refund of {$refundAmount} SAR has been processed (cancellation fee: {$cancellationFee} SAR)",
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'refund_amount' => $refundAmount,
                'cancellation_fee' => $cancellationFee,
                'total_paid' => $booking->total_amount,
            ]
        );
    }

    /**
     * Send review reminder to client
     */
    public function sendReviewReminder(Booking $booking): void
    {
        $provider = $booking->provider;

        $this->send(
            $booking->client_id,
            'review_reminder',
            'Rate Your Experience',
            "How was your experience with {$provider->business_name}? Please leave a review.",
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'provider_id' => $provider->id,
                'provider_name' => $provider->business_name,
            ]
        );
    }

    /**
     * Send new review notification to provider
     */
    public function sendNewReview(int $providerId, int $rating, string $clientName, int $reviewId): void
    {
        $provider = \App\Models\ServiceProvider::find($providerId);
        if (!$provider) return;

        $stars = str_repeat('⭐', $rating);

        $this->send(
            $provider->user_id,
            'new_review',
            'New Review Received',
            "{$clientName} rated you {$stars} ({$rating}/5)",
            [
                'review_id' => $reviewId,
                'rating' => $rating,
                'client_name' => $clientName,
            ]
        );
    }

    /**
     * Send booking completed notification
     */
    public function sendBookingCompleted(Booking $booking): void
    {
        $provider = $booking->provider;
        $client = $booking->client;

        // Notify client - Bilingual
        $clientTitle = 'Booking Completed | تم إكمال الحجز';
        $clientBody = "Your booking with {$provider->business_name} has been completed. Thank you!\n" .
                      "تم إكمال حجزك مع {$provider->business_name}. شكراً لك!";

        $this->send(
            $booking->client_id,
            'booking_completed',
            $clientTitle,
            $clientBody,
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
            ]
        );

        // Notify provider - Bilingual
        $providerTitle = 'Booking Completed | تم إكمال الحجز';
        $providerBody = "Booking with {$client->name} has been marked as completed.\n" .
                        "تم تحديد الحجز مع {$client->name} كمكتمل.";

        $this->send(
            $provider->user_id,
            'booking_completed',
            $providerTitle,
            $providerBody,
            [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
            ]
        );
    }

    /**
     * Send chat message notification
     */
    public function sendChatMessage(int $recipientId, string $senderName, string $messagePreview, int $conversationId, string $messageType = 'text'): void
    {
        // Bilingual message
        $title = "New message from {$senderName} | رسالة جديدة من {$senderName}";

        // If image message, adjust preview
        if ($messageType === 'image') {
            $body = "{$senderName} sent you an image | أرسل {$senderName} صورة";
        } else {
            $body = $messagePreview;
        }

        $this->send(
            $recipientId,
            'chat_message',
            $title,
            $body,
            [
                'conversation_id' => $conversationId,
                'sender_name' => $senderName,
                'message_type' => $messageType,
            ]
        );
    }

    /**
     * Send provider approved notification
     */
    public function sendProviderApproved(int $userId, string $businessName): void
    {
        // Bilingual notification
        $title = 'Congratulations! | مبروك!';
        $body = "Your business '{$businessName}' has been approved. You can now start receiving bookings!\n" .
                "تم الموافقة على نشاطك التجاري '{$businessName}'. يمكنك الآن البدء في استقبال الحجوزات!";

        $this->send(
            $userId,
            'provider_approved',
            $title,
            $body,
            [
                'business_name' => $businessName,
            ]
        );
    }

    /**
     * Send provider rejected notification
     */
    public function sendProviderRejected(int $userId, string $businessName, string $reason = ''): void
    {
        // Bilingual notification
        $title = 'Application Status | حالة الطلب';
        $body = "Your business application '{$businessName}' has been rejected." .
                ($reason ? " Reason: {$reason}" : '') . "\n" .
                "تم رفض طلب نشاطك التجاري '{$businessName}'." .
                ($reason ? " السبب: {$reason}" : '');

        $this->send(
            $userId,
            'provider_rejected',
            $title,
            $body,
            [
                'business_name' => $businessName,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Send admin announcement to all users
     */
    public function sendAdminAnnouncement(string $title, string $body, array $userIds = []): void
    {
        if (empty($userIds)) {
            // Send to all active users
            $userIds = User::whereNull('deleted_at')->pluck('id')->toArray();
        }

        foreach ($userIds as $userId) {
            $this->send(
                $userId,
                'admin_announcement',
                $title,
                $body,
                ['is_announcement' => true]
            );
        }
    }

    /**
     * Send notification to all admin/dashboard users
     */
    public function sendToAdmins(string $type, string $title, string $body, array $data = []): void
    {
        try {
            Log::info('=== SENDING NOTIFICATION TO DASHBOARD USERS ===', [
                'type' => $type,
                'title' => $title,
            ]);
            
            // Get all users with dashboard access (user_type = 'admin')
            // This includes: super_admin, admin, manager, support_agent, content_manager, analyst
            $adminUsers = User::where('user_type', 'admin')
                ->where('is_active', true)
                ->get();
            
            Log::info('Found dashboard users:', ['count' => $adminUsers->count()]);
            
            // If no users found, log and return
            if ($adminUsers->isEmpty()) {
                Log::warning('No dashboard users found to send notification', ['type' => $type]);
                return;
            }
            
            foreach ($adminUsers as $admin) {
                Log::info('Sending notification to dashboard user:', [
                    'user_id' => $admin->id,
                    'user_name' => $admin->name,
                    'roles' => $admin->roles->pluck('name')->toArray(),
                ]);
                
                $this->send(
                    $admin->id,
                    $type,
                    $title,
                    $body,
                    $data
                );
            }
            
            Log::info('=== DASHBOARD USER NOTIFICATIONS SENT SUCCESSFULLY ===', [
                'total_sent' => $adminUsers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send notification to dashboard users: ' . $e->getMessage(), [
                'type' => $type,
                'title' => $title,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Send notification when provider requests profile change
     */
    public function sendProviderChangeRequest(int $providerId, string $businessName, array $changedFields): void
    {
        // Bilingual notification to admins
        $title = 'Profile Change Request | طلب تعديل ملف';
        $fieldsCount = count($changedFields);
        $body = "{$businessName} has requested to change {$fieldsCount} field(s) in their profile. Please review.\n" .
                "طلب {$businessName} تعديل {$fieldsCount} حقل/حقول في ملفه. يرجى المراجعة.";

        $this->sendToAdmins(
            'profile_change_request',
            $title,
            $body,
            [
                'provider_id' => $providerId,
                'business_name' => $businessName,
                'fields_count' => $fieldsCount,
                'changed_fields' => array_keys($changedFields),
            ]
        );
    }

    /**
     * Send notification when admin approves profile change
     */
    public function sendProfileChangeApproved(int $userId, string $businessName): void
    {
        // Bilingual notification to provider
        $title = 'Profile Updated | تم تحديث الملف';
        $body = "Great news! Your profile changes for '{$businessName}' have been approved and applied.\n" .
                "أخبار رائعة! تم الموافقة على تغييرات ملف '{$businessName}' وتطبيقها.";

        $this->send(
            $userId,
            'profile_change_approved',
            $title,
            $body,
            [
                'business_name' => $businessName,
            ]
        );
    }

    /**
     * Send notification when admin rejects profile change
     */
    public function sendProfileChangeRejected(int $userId, string $businessName, string $reason = ''): void
    {
        // Bilingual notification to provider
        $title = 'Profile Changes Rejected | تم رفض التغييرات';
        $body = "Your profile changes for '{$businessName}' have been rejected." .
                ($reason ? " Reason: {$reason}" : '') . "\n" .
                "تم رفض تغييرات ملف '{$businessName}'." .
                ($reason ? " السبب: {$reason}" : '');

        $this->send(
            $userId,
            'profile_change_rejected',
            $title,
            $body,
            [
                'business_name' => $businessName,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Send notification when provider submits withdrawal request
     */
    public function sendWithdrawalRequestSubmitted(int $userId, string $businessName, float $amount, int $withdrawalId): void
    {
        // Notify provider - Bilingual
        $title = 'Withdrawal Request Submitted | تم إرسال طلب السحب';
        $body = "Your withdrawal request for {$amount} SAR has been submitted and is pending review.\n" .
                "تم إرسال طلب السحب الخاص بك بمبلغ {$amount} ريال وهو قيد المراجعة.";

        $this->send(
            $userId,
            'withdrawal_request_submitted',
            $title,
            $body,
            [
                'withdrawal_id' => $withdrawalId,
                'amount' => $amount,
                'business_name' => $businessName,
            ]
        );

        // Notify admins - Bilingual
        $adminTitle = 'New Withdrawal Request | طلب سحب جديد';
        $adminBody = "{$businessName} has requested a withdrawal of {$amount} SAR. Please review.\n" .
                     "طلب {$businessName} سحب مبلغ {$amount} ريال. يرجى المراجعة.";

        $this->sendToAdmins(
            'withdrawal_request',
            $adminTitle,
            $adminBody,
            [
                'withdrawal_id' => $withdrawalId,
                'provider_name' => $businessName,
                'amount' => $amount,
            ]
        );
    }

    /**
     * Send notification when withdrawal is approved
     */
    public function sendWithdrawalApproved(int $userId, string $businessName, float $amount, int $withdrawalId): void
    {
        // Bilingual notification to provider
        $title = 'Withdrawal Approved | تم الموافقة على السحب';
        $body = "Great news! Your withdrawal request for {$amount} SAR has been approved and is being processed.\n" .
                "أخبار رائعة! تم الموافقة على طلب السحب الخاص بك بمبلغ {$amount} ريال وجاري معالجته.";

        $this->send(
            $userId,
            'withdrawal_approved',
            $title,
            $body,
            [
                'withdrawal_id' => $withdrawalId,
                'amount' => $amount,
                'business_name' => $businessName,
            ]
        );
    }

    /**
     * Send notification when withdrawal is rejected
     */
    public function sendWithdrawalRejected(int $userId, string $businessName, float $amount, int $withdrawalId, string $reason = ''): void
    {
        // Bilingual notification to provider
        $title = 'Withdrawal Rejected | تم رفض السحب';
        $body = "Your withdrawal request for {$amount} SAR has been rejected." .
                ($reason ? " Reason: {$reason}" : '') . "\n" .
                "تم رفض طلب السحب الخاص بك بمبلغ {$amount} ريال." .
                ($reason ? " السبب: {$reason}" : '');

        $this->send(
            $userId,
            'withdrawal_rejected',
            $title,
            $body,
            [
                'withdrawal_id' => $withdrawalId,
                'amount' => $amount,
                'business_name' => $businessName,
                'reason' => $reason,
            ]
        );
    }

    /**
     * Send notification when withdrawal is completed
     */
    public function sendWithdrawalCompleted(int $userId, string $businessName, float $amount, int $withdrawalId, string $transactionReference = ''): void
    {
        // Bilingual notification to provider
        $title = 'Withdrawal Completed | تم إتمام السحب';
        $body = "Your withdrawal of {$amount} SAR has been completed successfully." .
                ($transactionReference ? " Reference: {$transactionReference}" : '') . "\n" .
                "تم إتمام سحب مبلغ {$amount} ريال بنجاح." .
                ($transactionReference ? " المرجع: {$transactionReference}" : '');

        $this->send(
            $userId,
            'withdrawal_completed',
            $title,
            $body,
            [
                'withdrawal_id' => $withdrawalId,
                'amount' => $amount,
                'business_name' => $businessName,
                'transaction_reference' => $transactionReference,
            ]
        );
    }

    /**
     * Send notification when bank info is updated
     */
    public function sendBankInfoUpdated(int $userId, string $businessName): void
    {
        // Bilingual notification to provider
        $title = 'Bank Info Updated | تم تحديث معلومات البنك';
        $body = "Your bank account information has been updated successfully.\n" .
                "تم تحديث معلومات حسابك البنكي بنجاح.";

        $this->send(
            $userId,
            'bank_info_updated',
            $title,
            $body,
            [
                'business_name' => $businessName,
            ]
        );
    }
}
