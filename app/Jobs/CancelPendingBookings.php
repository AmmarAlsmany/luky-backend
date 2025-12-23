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

class CancelPendingBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     * Auto-cancel bookings if provider doesn't respond within timeout
     */
    public function handle(NotificationService $notificationService)
    {
        $timeoutMinutes = AppSetting::get('provider_acceptance_timeout_minutes', 30);

        // Find bookings that are still pending and have exceeded timeout
        $expiredBookings = Booking::with(['client', 'provider'])
            ->where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes($timeoutMinutes))
            ->get();

        foreach ($expiredBookings as $booking) {
            $booking->update([
                'status' => 'cancelled',
                'cancelled_by' => 'system',
                'cancellation_reason' => 'Provider did not respond within ' . $timeoutMinutes . ' minutes',
                'cancelled_at' => now()
            ]);

            // Send notification to client (provider didn't respond)
            $notificationService->sendBookingCancelled($booking, 'client');

            // Send notification to admin/dashboard users
            $notificationService->sendToAdmins(
                'booking_cancelled',
                'Booking Auto-Cancelled',
                "Booking #{$booking->booking_number} was cancelled - provider did not respond within {$timeoutMinutes} minutes",
                [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'cancelled_by' => 'system',
                    'reason' => $booking->cancellation_reason,
                ]
            );

            Log::info('Booking auto-cancelled - provider did not respond', [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'timeout_minutes' => $timeoutMinutes
            ]);
        }

        if ($expiredBookings->count() > 0) {
            Log::info('Auto-cancelled ' . $expiredBookings->count() . ' pending bookings due to provider timeout');
        }
    }
}
