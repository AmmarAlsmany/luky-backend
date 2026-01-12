<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\User;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    /**
     * Display a listing of bookings
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 20);
        $search = $request->input('search');
        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        $providerId = $request->input('provider_id');

        $query = Booking::with(['client', 'provider.user', 'items.service']);

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('booking_number', 'LIKE', "%{$search}%")
                  ->orWhereHas('client', function($q) use ($search) {
                      $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%");
                  })
                  ->orWhereHas('provider', function($q) use ($search) {
                      $q->where('business_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Status filter
        if ($status) {
            $query->where('status', $status);
        }

        // Payment status filter
        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        // Provider filter
        if ($providerId) {
            $query->where('provider_id', $providerId);
        }

        // Date range filter
        if ($dateFrom) {
            $query->whereDate('booking_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('booking_date', '<=', $dateTo);
        }

        $query->orderBy('created_at', 'desc');

        $bookings = $query->paginate($perPage);

        // Transform bookings data
        $bookingsData = $bookings->getCollection()->map(function ($booking) {
            return [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'client' => [
                    'id' => $booking->client->id,
                    'name' => $booking->client->name,
                    'phone' => $booking->client->phone,
                    'email' => $booking->client->email,
                    'avatar' => $booking->client->avatar ?? null,
                ],
                'provider' => $booking->provider ? [
                    'id' => $booking->provider->id,
                    'business_name' => $booking->provider->business_name ?? 'N/A',
                    'phone' => $booking->provider->user->phone ?? null,
                ] : null,
                'items_count' => $booking->items->count(),
                'services' => $booking->items->map(function($item) {
                    return $item->service->name_en ?? $item->service->name ?? 'N/A';
                })->join(', '),
                'booking_date' => $booking->booking_date->format('Y-m-d'),
                'start_time' => $booking->start_time->format('H:i'),
                'status' => $booking->status,
                'payment_status' => $booking->payment_status,
                'subtotal' => $booking->subtotal,
                'tax_amount' => $booking->tax_amount,
                'discount_amount' => $booking->discount_amount,
                'total_amount' => $booking->total_amount,
                'commission_amount' => $booking->commission_amount,
                'created_at' => $booking->created_at,
            ];
        });

        // Get stats with commission tracking
        $stats = [
            'total' => Booking::count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'completed' => Booking::where('status', 'completed')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
            'rejected' => Booking::where('status', 'rejected')->count(),
            'total_revenue' => Booking::where('status', 'completed')->sum('total_amount'),
            'total_commission' => Booking::where('status', 'completed')->sum('commission_amount'),
        ];

        $pagination = [
            'current_page' => $bookings->currentPage(),
            'last_page' => $bookings->lastPage(),
            'per_page' => $bookings->perPage(),
            'total' => $bookings->total(),
            'from' => $bookings->firstItem(),
            'to' => $bookings->lastItem(),
        ];

        // Get providers for filter
        $providers = ServiceProvider::select('id', 'business_name')->get();

        $filters = compact('status', 'paymentStatus', 'dateFrom', 'dateTo', 'search', 'providerId');

        return view('bookings.list', [
            'bookings' => $bookingsData,
            'pagination' => $pagination,
            'stats' => $stats,
            'providers' => $providers,
            'filters' => $filters
        ]);
    }

    /**
     * Display the specified booking
     */
    public function show($id)
    {
        $booking = Booking::with([
            'client',
            'provider.user',
            'items.service.providerServiceCategory',
            'payment',
            'review'
        ])->findOrFail($id);

        // Build timeline from booking status changes
        $timeline = [];

        $timeline[] = [
            'status' => 'created',
            'title' => 'Booking Created',
            'description' => 'Booking was created by ' . $booking->client->name,
            'timestamp' => $booking->created_at,
            'icon' => 'plus-circle',
            'color' => 'primary'
        ];

        if ($booking->confirmed_at) {
            $timeline[] = [
                'status' => 'confirmed',
                'title' => 'Booking Confirmed',
                'description' => 'Booking was confirmed',
                'timestamp' => $booking->confirmed_at,
                'icon' => 'check-circle',
                'color' => 'success'
            ];
        }

        if ($booking->completed_at) {
            $timeline[] = [
                'status' => 'completed',
                'title' => 'Booking Completed',
                'description' => 'Service was completed',
                'timestamp' => $booking->completed_at,
                'icon' => 'check-all',
                'color' => 'success'
            ];
        }

        if ($booking->cancelled_at) {
            $cancelledBy = ucfirst($booking->cancelled_by ?? 'system');
            $timeline[] = [
                'status' => 'cancelled',
                'title' => 'Booking Cancelled',
                'description' => "Cancelled by {$cancelledBy}" . ($booking->cancellation_reason ? ": {$booking->cancellation_reason}" : ''),
                'timestamp' => $booking->cancelled_at,
                'icon' => 'x-circle',
                'color' => 'danger'
            ];
        }

        // Sort timeline by timestamp
        usort($timeline, function($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        $bookingData = [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'client' => [
                'id' => $booking->client->id,
                'name' => $booking->client->name,
                'email' => $booking->client->email,
                'phone' => $booking->client->phone,
                'avatar' => $booking->client->avatar ?? null,
            ],
            'provider' => $booking->provider ? [
                'id' => $booking->provider->id,
                'business_name' => $booking->provider->business_name ?? 'N/A',
                'phone' => $booking->provider->user->phone ?? null,
                'email' => $booking->provider->user->email ?? null,
                'logo' => $booking->provider->logo_url ?? null,
            ] : null,
            'items' => $booking->items->map(function($item) {
                return [
                    'id' => $item->id,
                    'service' => [
                        'id' => $item->service->id,
                        'name_en' => $item->service->name_en ?? $item->service->name ?? 'N/A',
                        'name_ar' => $item->service->name_ar ?? $item->service->name ?? 'N/A',
                        'description' => $item->service->description ?? '',
                        'duration' => $item->service->duration ?? null,
                        'image' => $item->service->image_url ?? null,
                        'category' => $item->service->providerServiceCategory ? [
                            'id' => $item->service->providerServiceCategory->id,
                            'name' => $item->service->providerServiceCategory->name_en ?? $item->service->providerServiceCategory->name_ar ?? 'N/A',
                            'name_en' => $item->service->providerServiceCategory->name_en ?? 'N/A',
                            'name_ar' => $item->service->providerServiceCategory->name_ar ?? 'N/A',
                        ] : null,
                    ],
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'service_location' => $item->service_location ?? 'center',
                ];
            }),
            'booking_date' => $booking->booking_date->format('Y-m-d'),
            'start_time' => $booking->start_time->format('Y-m-d H:i:s'),
            'end_time' => $booking->end_time->format('Y-m-d H:i:s'),
            'start_time_formatted' => $booking->start_time->format('h:i A'),
            'end_time_formatted' => $booking->end_time->format('h:i A'),
            'duration_minutes' => $booking->start_time->diffInMinutes($booking->end_time),
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'payment_method' => $booking->payment_method ?? 'N/A',
            'payment_reference' => $booking->payment_reference ?? null,
            'service_amount' => $booking->subtotal, // Alias for view compatibility
            'subtotal' => $booking->subtotal,
            'tax_amount' => $booking->tax_amount,
            'discount_amount' => $booking->discount_amount,
            'total_amount' => $booking->total_amount,
            'commission_amount' => $booking->commission_amount,
            'notes' => $booking->notes,
            'client_address' => $booking->client_address,
            'client_latitude' => $booking->client_latitude,
            'client_longitude' => $booking->client_longitude,
            'cancellation_reason' => $booking->cancellation_reason,
            'cancelled_by' => $booking->cancelled_by,
            'confirmed_at' => $booking->confirmed_at,
            'completed_at' => $booking->completed_at,
            'cancelled_at' => $booking->cancelled_at,
            'timeline' => $timeline,
            'payment' => $booking->payment,
            'review' => $booking->review,
            'created_at' => $booking->created_at,
            'updated_at' => $booking->updated_at,
            // Backward compatibility for views expecting single service
            'service' => $booking->items->first() ? [
                'name' => $booking->items->first()->service->name_en ?? $booking->items->first()->service->name ?? 'N/A',
                'description' => $booking->items->first()->service->description ?? '',
                'duration' => $booking->items->first()->service->duration ?? 'N/A',
                'image' => $booking->items->first()->service->image_url ?? null,
            ] : null,
            'service_category' => $booking->items->first() && $booking->items->first()->service->providerServiceCategory ? [
                'name' => $booking->items->first()->service->providerServiceCategory->name_en ?? $booking->items->first()->service->providerServiceCategory->name ?? 'N/A',
            ] : null,
        ];

        // Get available providers for reassignment
        $availableProviders = ServiceProvider::where('verification_status', 'approved')
            ->where('is_active', true)
            ->with('user:id,phone')
            ->select('id', 'business_name', 'user_id')
            ->get()
            ->map(function($provider) {
                return (object) [
                    'id' => $provider->id,
                    'business_name' => $provider->business_name,
                    'phone' => $provider->user->phone ?? 'N/A',
                ];
            });

        return view('bookings.details', [
            'booking' => $bookingData,
            'availableProviders' => $availableProviders
        ]);
    }

    /**
     * Update booking status
     */
    public function updateStatus(Request $request, $id, NotificationService $notificationService)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,confirmed,completed,cancelled,rejected',
            'reason' => 'nullable|string|max:500',
            'cancelled_by' => 'nullable|in:client,provider,admin',
            'process_refund' => 'nullable|boolean', // Flag to confirm refund processing
        ]);

        $booking = Booking::with(['provider', 'client', 'payment'])->findOrFail($id);

        // CRITICAL: Prevent cancellation of paid bookings without refund confirmation
        if ($validated['status'] === 'cancelled' && $booking->payment_status === 'paid') {
            // Check if admin confirmed they want to process the refund
            if (!($validated['process_refund'] ?? false)) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'This booking has been paid. Cancelling requires processing a refund.',
                        'requires_refund' => true,
                        'payment_info' => [
                            'amount_paid' => (float) $booking->total_amount,
                            'payment_method' => $booking->payment_method,
                            'payment_reference' => $booking->payment_reference,
                        ],
                    ], 400);
                }

                return redirect()->back()->with('error', 'This booking has been paid. You must process a refund before cancelling.');
            }

            // If refund confirmed, update payment status to refunded
            // TODO: Integrate with payment gateway API to process actual refund
            if ($booking->payment) {
                $booking->payment->update([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'refund_reason' => $validated['reason'] ?? 'Booking cancelled by admin',
                ]);
            }
        }

        DB::beginTransaction();
        try {
            $updateData = ['status' => $validated['status']];

            // Set timestamp based on status
            switch ($validated['status']) {
                case 'confirmed':
                    $paymentTimeoutMinutes = (int) (\App\Models\AppSetting::where('key', 'payment_timeout_minutes')->value('value') ?? 5);
                    $updateData['confirmed_at'] = now();
                    $updateData['payment_deadline'] = now()->addMinutes($paymentTimeoutMinutes);
                    break;
                case 'completed':
                    $updateData['completed_at'] = now();
                    $updateData['payment_status'] = 'paid'; // Auto-mark as paid when completed
                    break;
                case 'cancelled':
                    $updateData['cancelled_at'] = now();
                    $updateData['cancellation_reason'] = $validated['reason'] ?? null;
                    $updateData['cancelled_by'] = $validated['cancelled_by'] ?? 'admin';

                    // If booking was paid, mark as refunded
                    if ($booking->payment_status === 'paid') {
                        $updateData['payment_status'] = 'refunded';
                    }
                    break;
            }

            $booking->update($updateData);

            DB::commit();

            // Send appropriate notification based on status
            switch ($validated['status']) {
                case 'confirmed':
                    $notificationService->sendBookingAccepted($booking);
                    break;
                case 'rejected':
                    $notificationService->sendBookingRejected($booking);
                    break;
                case 'cancelled':
                    $notificationService->sendBookingCancelled($booking, 'client');

                    // If booking was paid and refunded, notify client about refund
                    if ($booking->payment_status === 'refunded' && $booking->total_amount > 0) {
                        $notificationService->sendRefundProcessed($booking, $booking->total_amount, 0);
                    }
                    break;
                case 'completed':
                    $notificationService->sendBookingCompleted($booking);
                    break;
            }

            if ($request->expectsJson()) {
                $message = 'Booking status updated successfully';
                $additionalData = [];

                // Add refund info if applicable
                if ($validated['status'] === 'cancelled' && $booking->payment_status === 'refunded') {
                    $message .= '. Refund of ' . $booking->total_amount . ' SAR will be processed.';
                    $additionalData['refund_amount'] = (float) $booking->total_amount;
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $additionalData,
                ]);
            }

            $successMessage = 'Booking status updated successfully';
            if ($validated['status'] === 'cancelled' && $booking->payment_status === 'refunded') {
                $successMessage .= '. Refund of ' . $booking->total_amount . ' SAR will be processed.';
            }

            return redirect()->back()->with('success', $successMessage);

        } catch (\Exception $e) {
            DB::rollBack();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update booking status: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->back()->with('error', 'Failed to update booking status');
        }
    }

    /**
     * Assign provider to booking
     */
    public function assignProvider(Request $request, $id)
    {
        $validated = $request->validate([
            'provider_id' => 'required|exists:service_providers,id',
        ]);

        $booking = Booking::findOrFail($id);

        DB::beginTransaction();
        try {
            $booking->update(['provider_id' => $validated['provider_id']]);

            // Send notification to new provider
            $provider = ServiceProvider::findOrFail($validated['provider_id']);

            \App\Models\Notification::create([
                'user_id' => $provider->user_id ?? null,
                'type' => 'booking_assigned',
                'title' => 'New Booking Assigned',
                'body' => "You have been assigned to booking #{$booking->booking_number}",
                'data' => [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'assigned_at' => now()->toISOString(),
                ],
                'is_read' => false,
                'is_sent' => true,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Provider assigned successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign provider: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export bookings to CSV
     */
    public function export(Request $request)
    {
        $status = $request->input('status');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Booking::with(['client', 'provider', 'items.service']);

        // Apply same filters as index
        if ($status) {
            $query->where('status', $status);
        }
        if ($dateFrom) {
            $query->whereDate('booking_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('booking_date', '<=', $dateTo);
        }

        $bookings = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'bookings_export_' . date('Y-m-d_His') . '.csv';
        $filepath = storage_path('app/exports/' . $filename);

        // Create directory if not exists
        if (!file_exists(storage_path('app/exports'))) {
            mkdir(storage_path('app/exports'), 0755, true);
        }

        $file = fopen($filepath, 'w');

        // Add CSV headers
        fputcsv($file, [
            'Booking Number',
            'Client Name',
            'Client Email',
            'Client Phone',
            'Provider Name',
            'Services',
            'Booking Date',
            'Start Time',
            'Status',
            'Payment Status',
            'Subtotal',
            'Tax',
            'Discount',
            'Total Amount',
            'Commission',
            'Created At'
        ]);

        // Add data rows
        foreach ($bookings as $booking) {
            $services = $booking->items->map(function($item) {
                return $item->service->name_en ?? $item->service->name ?? 'N/A';
            })->join('; ');

            fputcsv($file, [
                $booking->booking_number,
                $booking->client->name,
                $booking->client->email,
                $booking->client->phone,
                $booking->provider->business_name ?? 'N/A',
                $services,
                $booking->booking_date->format('Y-m-d'),
                $booking->start_time->format('H:i'),
                $booking->status,
                $booking->payment_status,
                $booking->subtotal,
                $booking->tax_amount,
                $booking->discount_amount,
                $booking->total_amount,
                $booking->commission_amount,
                $booking->created_at->format('Y-m-d H:i:s')
            ]);
        }

        fclose($file);

        return response()->download($filepath, $filename)->deleteFileAfterSend(true);
    }

    /**
     * Delete booking
     */
    public function destroy($id)
    {
        $booking = Booking::findOrFail($id);

        // Check if booking can be deleted
        if (in_array($booking->status, ['confirmed', 'completed'])) {
            return response()->json([
                'success' => false,
                'message' => __('bookings.cannot_delete_confirmed_completed'),
            ], 400);
        }

        // Check if payment was made
        if ($booking->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => __('bookings.cannot_delete_paid_booking'),
            ], 400);
        }

        $booking->delete();

        return response()->json([
            'success' => true,
            'message' => __('bookings.booking_deleted_successfully'),
        ]);
    }
}
