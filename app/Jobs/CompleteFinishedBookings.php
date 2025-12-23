<?php

namespace App\Jobs;

use App\Models\Booking;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CompleteFinishedBookings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     * Auto-complete bookings after their service end_time has passed
     */
    public function handle(NotificationService $notificationService)
    {
        // Find bookings that:
        // 1. Are confirmed (provider accepted and client paid)
        // 2. Payment status is paid
        // 3. Service end_time has passed
        // 4. Status is not already completed or cancelled
        $finishedBookings = Booking::with(['client', 'provider'])
            ->where('status', 'confirmed')
            ->where('payment_status', 'paid')
            ->where('end_time', '<=', now())
            ->get();

        foreach ($finishedBookings as $booking) {
            try {
                $booking->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);

                // Send completion notifications to both client and provider
                $notificationService->sendBookingCompleted($booking);

                // Send notification to admin/dashboard users
                $notificationService->sendToAdmins(
                    'booking_completed',
                    'Booking Auto-Completed',
                    "Booking #{$booking->booking_number} was auto-completed after service time ended. Amount: {$booking->total_amount} SAR",
                    [
                        'booking_id' => $booking->id,
                        'booking_number' => $booking->booking_number,
                        'amount' => $booking->total_amount,
                        'completed_at' => now()->toIso8601String(),
                    ]
                );

                Log::info('Booking auto-completed after service time ended', [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'end_time' => $booking->end_time,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to auto-complete booking', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        if ($finishedBookings->count() > 0) {
            Log::info('Auto-completed ' . $finishedBookings->count() . ' finished bookings');
        }
    }
}
