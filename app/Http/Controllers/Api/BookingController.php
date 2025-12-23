<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\ServiceProvider;
use App\Models\Service;
use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\BookingResource;
use Carbon\Carbon;

class BookingController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    /**
     * Create new booking (Client)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->user_type !== 'client') {
            throw ValidationException::withMessages([
                'message' => ['Only clients can create bookings.']
            ]);
        }

        $validated = $request->validate([
            'provider_id' => 'required|exists:service_providers,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'start_time' => 'required|date_format:H:i',
            'services' => 'required|array|min:1',
            'services.*.service_id' => 'required|exists:services,id',
            'services.*.quantity' => 'required|integer|min:1',
            'services.*.location' => 'required|in:salon,home',
            'client_address' => 'nullable|string|max:500',
            'client_latitude' => 'nullable|numeric|between:-90,90',
            'client_longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:500',
            'promo_code' => 'nullable|string|max:50',
        ]);

        // Check if any service is at home and validate client_address
        $hasHomeService = collect($validated['services'])->contains('location', 'home');
        if ($hasHomeService && empty($validated['client_address'])) {
            throw ValidationException::withMessages([
                'client_address' => ['Address is required when selecting home services.']
            ]);
        }

        $provider = ServiceProvider::with('services')->findOrFail($validated['provider_id']);

        // Validate provider is approved and active
        if ($provider->verification_status !== 'approved' || !$provider->is_active) {
            throw ValidationException::withMessages([
                'provider_id' => ['This provider is not currently accepting bookings.']
            ]);
        }

        DB::beginTransaction();
        try {
            // Calculate booking details
            $subtotal = 0;
            $totalDuration = 0;
            $serviceDetails = [];

            foreach ($validated['services'] as $serviceData) {
                $service = Service::findOrFail($serviceData['service_id']);
                
                // Validate service belongs to provider
                if ($service->provider_id !== $provider->id) {
                    throw ValidationException::withMessages([
                        'services' => ['Service does not belong to the selected provider.']
                    ]);
                }

                // Validate location availability
                if ($serviceData['location'] === 'home' && !$service->available_at_home) {
                    throw ValidationException::withMessages([
                        'services' => ["Service '{$service->name}' is not available at home."]
                    ]);
                }

                // Get correct price based on location
                $unitPrice = $service->getPriceForLocation($serviceData['location']);
                $quantity = $serviceData['quantity'];
                $itemTotal = $unitPrice * $quantity;
                
                $subtotal += $itemTotal;
                $totalDuration += ($service->duration_minutes * $quantity);

                $serviceDetails[] = [
                    'service' => $service,
                    'quantity' => $quantity,
                    'location' => $serviceData['location'],
                    'unit_price' => $unitPrice,
                    'total_price' => $itemTotal,
                ];
            }

            // Calculate end time based on total duration
            $startDateTime = $validated['booking_date'] . ' ' . $validated['start_time'];
            $endDateTime = date('Y-m-d H:i:s', strtotime($startDateTime) + ($totalDuration * 60));

            // Check if time is within working hours
            $this->validateWorkingHours($provider, $validated['booking_date'], $validated['start_time']);

            // Validate and apply promo code if provided
            $discountAmount = 0;
            $promoCodeId = null;

            if (!empty($validated['promo_code'])) {
                $promoCode = PromoCode::where('code', strtoupper($validated['promo_code']))->first();

                if (!$promoCode) {
                    throw ValidationException::withMessages([
                        'promo_code' => ['Invalid promo code']
                    ]);
                }

                // Validate promo code
                if (!$promoCode->isValid()) {
                    throw ValidationException::withMessages([
                        'promo_code' => ['This promo code has expired or is no longer active']
                    ]);
                }

                // Check if user can use this promo code
                if (!$promoCode->canBeUsedByUser($user->id)) {
                    throw ValidationException::withMessages([
                        'promo_code' => ['You have already used this promo code the maximum number of times']
                    ]);
                }

                // Check minimum booking amount
                if ($promoCode->min_booking_amount && $subtotal < $promoCode->min_booking_amount) {
                    throw ValidationException::withMessages([
                        'promo_code' => ["Minimum booking amount of {$promoCode->min_booking_amount} SAR required for this promo code"]
                    ]);
                }

                // Check service applicability
                if ($promoCode->applicable_services) {
                    $applicableServiceIds = $promoCode->applicable_services;
                    $bookingServiceIds = collect($validated['services'])->pluck('service_id')->toArray();
                    $hasApplicableService = !empty(array_intersect($applicableServiceIds, $bookingServiceIds));

                    if (!$hasApplicableService) {
                        throw ValidationException::withMessages([
                            'promo_code' => ['This promo code is not applicable to the selected services']
                        ]);
                    }
                }

                // Calculate discount
                $discountAmount = $promoCode->calculateDiscount($subtotal);
                $promoCodeId = $promoCode->id;
            }

            // Calculate tax and commission (after discount)
            $amountAfterDiscount = $subtotal - $discountAmount;
            $taxRate = 0.15; // 15% VAT
            $taxAmount = $amountAfterDiscount * $taxRate;
            $totalAmount = $amountAfterDiscount + $taxAmount;
            $commissionAmount = $amountAfterDiscount * ($provider->commission_rate / 100);

            // Generate unique booking number with timestamp and random component
            $bookingNumber = 'BK' . date('Ymd') . strtoupper(substr(uniqid(), -6));

            // Create booking
            $booking = Booking::create([
                'booking_number' => $bookingNumber,
                'client_id' => $user->id,
                'provider_id' => $provider->id,
                'booking_date' => $validated['booking_date'],
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
                'status' => 'pending',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'commission_amount' => $commissionAmount,
                'payment_status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                'client_address' => $validated['client_address'] ?? null,
                'client_latitude' => $validated['client_latitude'] ?? null,
                'client_longitude' => $validated['client_longitude'] ?? null,
                'promo_code_id' => $promoCodeId,
            ]);

            // Create booking items
            foreach ($serviceDetails as $detail) {
                BookingItem::create([
                    'booking_id' => $booking->id,
                    'service_id' => $detail['service']->id,
                    'quantity' => $detail['quantity'],
                    'unit_price' => $detail['unit_price'],
                    'total_price' => $detail['total_price'],
                    'service_location' => $detail['location'],
                ]);
            }

            // Record promo code usage if applied
            if ($promoCodeId) {
                PromoCodeUsage::create([
                    'promo_code_id' => $promoCodeId,
                    'user_id' => $user->id,
                    'booking_id' => $booking->id,
                    'discount_amount' => $discountAmount,
                ]);

                // Increment promo code used count
                PromoCode::where('id', $promoCodeId)->increment('used_count');
            }

            DB::commit();

            // Send notification to provider about new booking request
            $this->notificationService->sendBookingRequest($booking);

            return response()->json([
                'success' => true,
                'message' => 'Booking request submitted successfully',
                'data' => new BookingResource($booking->load(['client', 'provider', 'items.service']))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validate working hours
     */
    protected function validateWorkingHours(ServiceProvider $provider, string $date, string $time)
    {
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        
        // Check if day is off
        if ($provider->off_days && in_array($dayOfWeek, $provider->off_days)) {
            throw ValidationException::withMessages([
                'booking_date' => ['Provider is not available on this day.']
            ]);
        }

        // Check working hours
        if ($provider->working_hours && isset($provider->working_hours[$dayOfWeek])) {
            $workingHours = $provider->working_hours[$dayOfWeek];

            if ($time < $workingHours['open'] || $time > $workingHours['close']) {
                throw ValidationException::withMessages([
                    'start_time' => ['Time is outside provider working hours.']
                ]);
            }
        }
    }


    /**
     * Get client bookings
     */
    public function clientBookings(Request $request): JsonResponse
    {
        $user = $request->user();
        $status = $request->get('status'); // pending, confirmed, completed, cancelled

        $query = Booking::with(['provider.user', 'items.service'])
            ->where('client_id', $user->id);

        if ($status) {
            $query->where('status', $status);
        }

        $bookings = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings->items()),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'total' => $bookings->total(),
            ]
        ]);
    }

    /**
     * Get booking details by ID
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $booking = Booking::with(['provider.user', 'items.service', 'client'])
            ->findOrFail($id);

        // Verify user has access to this booking
        if ($booking->client_id !== $user->id &&
            ($user->user_type !== 'provider' || $booking->provider->user_id !== $user->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to booking.'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new BookingResource($booking)
        ]);
    }

    /**
     * Get provider bookings
     */
    public function providerBookings(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $status = $request->get('status');
        $date = $request->get('date'); // YYYY-MM-DD (specific date)
        $dateFilter = $request->get('date_filter'); // today, this_month, all

        $query = Booking::with(['client', 'items.service'])
            ->where('provider_id', $provider->id);

        if ($status) {
            $query->where('status', $status);
        }

        // Date filtering
        if ($dateFilter) {
            switch ($dateFilter) {
                case 'today':
                    $query->whereDate('booking_date', Carbon::today());
                    break;
                case 'this_month':
                    $query->whereYear('booking_date', Carbon::now()->year)
                          ->whereMonth('booking_date', Carbon::now()->month);
                    break;
                case 'all':
                    // No date filter - show all bookings
                    break;
                default:
                    // If invalid filter, default to today
                    $query->whereDate('booking_date', Carbon::today());
            }
        } elseif ($date) {
            // Support legacy single date parameter
            $query->whereDate('booking_date', $date);
        }

        $bookings = $query->orderBy('booking_date', 'desc')
                          ->orderBy('start_time', 'desc')
                          ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => BookingResource::collection($bookings->items()),
            'pagination' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'total' => $bookings->total(),
            ]
        ]);
    }

    /**
     * Accept booking (Provider)
     */
    public function acceptBooking(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        $booking = Booking::where('provider_id', $provider->id)
            ->where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        // Get payment timeout from admin settings
        $paymentTimeoutMinutes = (int) (\App\Models\AppSetting::where('key', 'payment_timeout_minutes')->value('value') ?? 5);

        \Log::info('=== ACCEPT BOOKING DEBUG ===', [
            'booking_id' => $booking->id,
            'timeout_minutes' => $paymentTimeoutMinutes,
            'payment_deadline' => now()->addMinutes($paymentTimeoutMinutes)->toDateTimeString()
        ]);

        $booking->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'payment_deadline' => now()->addMinutes($paymentTimeoutMinutes)
        ]);

        \Log::info('=== AFTER UPDATE ===', [
            'payment_deadline_raw' => $booking->getRawOriginal('payment_deadline')
        ]);

        // Refresh the booking to get the updated values
        $booking->refresh();

        // Send notification to client about booking acceptance
        $this->notificationService->sendBookingAccepted($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking accepted successfully',
            'data' => new BookingResource($booking->load(['client', 'items.service']))
        ]);
    }

    /**
     * Reject booking (Provider)
     */
    public function rejectBooking(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:500'
        ]);

        $booking = Booking::where('provider_id', $provider->id)
            ->where('id', $id)
            ->where('status', 'pending')
            ->firstOrFail();

        $booking->update([
            'status' => 'rejected',
            'cancellation_reason' => $validated['rejection_reason'] ?? 'Rejected by provider',
            'cancelled_by' => 'provider',
            'cancelled_at' => now()
        ]);

        // Send notification to client about booking rejection
        $this->notificationService->sendBookingRejected($booking);

        return response()->json([
            'success' => true,
            'message' => 'Booking rejected',
            'data' => new BookingResource($booking->load(['client', 'items.service']))
        ]);
    }

    /**
     * Calculate cancellation fee based on time remaining before appointment
     */
    private function calculateCancellationFee(Booking $booking): array
    {
        // If not paid yet, no fee
        if ($booking->payment_status !== 'paid') {
            return [
                'fee' => 0,
                'refund' => 0,
                'percentage' => 0,
                'reason' => 'No payment made yet'
            ];
        }

        // Calculate hours until appointment
        // Use start_time if available, otherwise fall back to booking_time
        $timeField = $booking->start_time ?? $booking->booking_time;
        if (!$timeField) {
            // If no time is set, assume appointment is in the future
            $appointmentDateTime = \Carbon\Carbon::parse($booking->booking_date)->endOfDay();
        } else {
            $appointmentDateTime = \Carbon\Carbon::parse($timeField);
        }
        $hoursUntilAppointment = now()->diffInHours($appointmentDateTime, false);

        // If appointment already passed, no refund
        if ($hoursUntilAppointment < 0) {
            return [
                'fee' => $booking->total_amount,
                'refund' => 0,
                'percentage' => 100,
                'reason' => 'Appointment time has passed'
            ];
        }

        // Time-based cancellation policy
        $feePercentage = 0;
        $reason = '';

        if ($hoursUntilAppointment < 24) {
            // Less than 24 hours: 50% fee
            $feePercentage = 50;
            $reason = 'Cancellation within 24 hours of appointment';
        } elseif ($hoursUntilAppointment < 48) {
            // 24-48 hours: 25% fee
            $feePercentage = 25;
            $reason = 'Cancellation within 48 hours of appointment';
        } else {
            // More than 48 hours: No fee
            $feePercentage = 0;
            $reason = 'Free cancellation (more than 48 hours notice)';
        }

        $cancellationFee = ($booking->total_amount * $feePercentage) / 100;
        $refundAmount = $booking->total_amount - $cancellationFee;

        return [
            'fee' => round($cancellationFee, 2),
            'refund' => round($refundAmount, 2),
            'percentage' => $feePercentage,
            'reason' => $reason,
            'hours_until_appointment' => round($hoursUntilAppointment, 1)
        ];
    }

    /**
     * Preview cancellation fees before actually cancelling
     */
    public function previewCancellation(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $booking = Booking::where('client_id', $user->id)
            ->where('id', $id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->firstOrFail();

        $feeInfo = $this->calculateCancellationFee($booking);

        return response()->json([
            'success' => true,
            'data' => [
                'booking_id' => $booking->id,
                'total_amount' => (float) $booking->total_amount,
                'cancellation_fee' => (float) $feeInfo['fee'],
                'refund_amount' => (float) $feeInfo['refund'],
                'fee_percentage' => $feeInfo['percentage'],
                'reason' => $feeInfo['reason'],
                'hours_until_appointment' => $feeInfo['hours_until_appointment'] ?? null,
                'appointment_date' => $booking->booking_date,
                'appointment_time' => $booking->start_time ?? $booking->booking_time,
            ]
        ]);
    }

    /**
     * Cancel booking (Client)
     */
    public function cancelBooking(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'cancellation_reason' => 'nullable|string|max:500'
        ]);

        // Check if user is admin/super_admin
        $isAdmin = $user->hasAnyRole(['admin', 'super_admin', 'manager']);

        // Build query - admins can cancel any booking, clients only their own
        $query = Booking::with(['payment', 'client'])
            ->where('id', $id)
            ->whereIn('status', ['pending', 'confirmed']);

        if (!$isAdmin) {
            $query->where('client_id', $user->id);
        }

        $booking = $query->firstOrFail();

        DB::beginTransaction();
        try {
            // Calculate cancellation fee based on time remaining
            $feeInfo = $this->calculateCancellationFee($booking);
            $cancellationFee = $feeInfo['fee'];
            $refundAmount = $feeInfo['refund'];

            // Update payment status if refund is applicable
            if ($refundAmount > 0 && $booking->payment_status === 'paid') {
                $payment = $booking->payment;

                // Handle wallet refunds
                if ($payment && $payment->method === 'wallet') {
                    $client = $booking->client;
                    $balanceBefore = $client->wallet_balance;
                    $balanceAfter = $balanceBefore + $refundAmount;

                    // Create refund transaction
                    \App\Models\WalletTransaction::create([
                        'user_id' => $client->id,
                        'type' => 'refund',
                        'amount' => $refundAmount,
                        'description' => "Refund for cancelled booking #{$booking->id}",
                        'reference_number' => 'REFUND-' . $booking->id,
                        'related_id' => $booking->id,
                        'related_type' => 'booking',
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'metadata' => [
                            'booking_id' => $booking->id,
                            'cancellation_fee' => $cancellationFee,
                            'original_amount' => $booking->total_amount,
                        ],
                    ]);

                    // Credit wallet
                    $client->increment('wallet_balance', $refundAmount);

                    Log::info('Wallet refund processed', [
                        'booking_id' => $booking->id,
                        'refund_amount' => $refundAmount,
                        'cancellation_fee' => $cancellationFee,
                        'new_balance' => $balanceAfter,
                    ]);
                }
                // Handle MyFatoorah refunds
                elseif ($payment && $payment->gateway === 'myfatoorah') {
                    // TODO: Process actual refund via MyFatoorah API
                    Log::info('MyFatoorah refund needed', [
                        'booking_id' => $booking->id,
                        'payment_id' => $payment->payment_id,
                        'refund_amount' => $refundAmount,
                    ]);
                }

                $booking->update(['payment_status' => 'refunded']);
            }

            // Determine who cancelled the booking
            $cancelledBy = $isAdmin ? 'admin' : 'client';
            $defaultReason = $isAdmin ? 'Cancelled by admin' : 'Cancelled by client';

            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['cancellation_reason'] ?? $defaultReason,
                'cancelled_by' => $cancelledBy,
                'cancelled_at' => now(),
                'discount_amount' => $booking->discount_amount + $cancellationFee, // Store cancellation fee
            ]);

            DB::commit();

            $message = 'Booking cancelled successfully';
            if ($refundAmount > 0) {
                $message .= sprintf('. Refund of %.2f SAR will be processed (cancellation fee: %.2f SAR)', $refundAmount, $cancellationFee);
                // Send refund notification to client
                $this->notificationService->sendRefundProcessed($booking, $refundAmount, $cancellationFee);
            }

            // Send cancellation notification to provider
            $this->notificationService->sendBookingCancelled($booking, 'provider');

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'booking' => new BookingResource($booking->load(['provider', 'items.service'])),
                    'refund_info' => [
                        'cancellation_fee' => (float) $cancellationFee,
                        'refund_amount' => (float) $refundAmount,
                        'total_paid' => (float) $booking->total_amount,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get provider daily schedule
     */
    public function providerSchedule(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        $date = $request->get('date', now()->format('Y-m-d'));

        $bookings = Booking::with(['client', 'items.service'])
            ->where('provider_id', $provider->id)
            ->whereDate('booking_date', $date)
            ->whereIn('status', ['pending', 'confirmed', 'completed'])
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date,
                'total_bookings' => $bookings->count(),
                'bookings' => BookingResource::collection($bookings)
            ]
        ]);
    }
}