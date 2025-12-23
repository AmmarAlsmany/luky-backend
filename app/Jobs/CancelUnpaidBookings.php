<?php
namespace App\Jobs;

use App\Models\Booking;
use App\Models\AppSetting;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CancelUnpaidBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationService $notificationService)
    {
        $timeoutMinutes = AppSetting::get('payment_timeout_minutes', 5);

        $expiredBookings = Booking::with(['client', 'provider'])
            ->where('status', 'confirmed')
            ->where('payment_status', 'pending')
            ->whereNotNull('confirmed_at')
            ->where('confirmed_at', '<=', now()->subMinutes($timeoutMinutes))
            ->get();

        foreach ($expiredBookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
                'cancelled_by' => 'system',
                'cancellation_reason' => 'Payment timeout - not paid within ' . $timeoutMinutes . ' minutes',
                'cancelled_at' => now()
            ]);

            // Send notifications to both client and provider
            $notificationService->sendBookingCancelled($booking, 'both');

            // Send notification to admin/dashboard users
            $notificationService->sendToAdmins(
                'booking_cancelled',
                'Booking Auto-Cancelled',
                "Booking #{$booking->booking_number} was cancelled - payment not completed within {$timeoutMinutes} minutes",
                [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'cancelled_by' => 'system',
                    'reason' => $booking->cancellation_reason,
                ]
            );

            Log::info('Booking auto-cancelled due to payment timeout', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number
            ]);
        }
    }
}