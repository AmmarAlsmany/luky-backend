<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\MyFatoorahService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    protected $myFatoorah;

    public function __construct(MyFatoorahService $myFatoorah)
    {
        $this->myFatoorah = $myFatoorah;
    }

    /**
     * Get available payment methods for booking
     */
    public function getPaymentMethods(Request $request): JsonResponse
    {
        Log::info('=== GET PAYMENT METHODS STARTED ===');
        Log::info('Request data:', $request->all());

        $request->validate([
            'booking_id' => 'required|exists:bookings,id'
        ]);

        $booking = Booking::findOrFail($request->booking_id);

        Log::info('Booking found:', [
            'id' => $booking->id,
            'client_id' => $booking->client_id,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'total_amount' => $booking->total_amount,
        ]);

        // Verify booking belongs to authenticated user
        if ($booking->client_id !== $request->user()->id) {
            throw ValidationException::withMessages([
                'booking_id' => ['Unauthorized access to booking.']
            ]);
        }

        // Verify booking is confirmed and payment is pending
        if ($booking->status !== 'confirmed' || $booking->payment_status !== 'pending') {
            throw ValidationException::withMessages([
                'booking_id' => ['Booking is not ready for payment.']
            ]);
        }

        Log::info('Calling MyFatoorah getPaymentMethods...');

        $result = $this->myFatoorah->getPaymentMethods($booking->total_amount);

        Log::info('MyFatoorah result:', $result);

        if (!$result['success']) {
            Log::error('Failed to load payment methods:', $result);
            return response()->json([
                'success' => false,
                'message' => 'Failed to load payment methods'
            ], 500);
        }

        Log::info('=== GET PAYMENT METHODS SUCCESS ===');

        return response()->json([
            'success' => true,
            'data' => [
                'booking_id' => $booking->id,
                'amount' => (float) $booking->total_amount,
                'currency' => 'SAR',
                'payment_methods' => $result['data']
            ]
        ]);
    }

    /**
     * Get payment status for a booking
     * GET /api/v1/payments/status?booking_id=X
     */
    public function getPaymentStatus(Request $request): JsonResponse
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id'
        ]);

        $booking = Booking::with('payment')->findOrFail($request->booking_id);

        // Verify booking belongs to authenticated user
        if ($booking->client_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to booking'
            ], 403);
        }

        // Get payment information
        $payment = $booking->payment;

        if (!$payment) {
            return response()->json([
                'success' => true,
                'data' => [
                    'booking_id' => $booking->id,
                    'payment_status' => $booking->payment_status,
                    'payment_method' => $booking->payment_method,
                    'has_payment' => false,
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'booking_id' => $booking->id,
                'payment_id' => $payment->id,
                'payment_status' => $booking->payment_status,
                'payment_method' => $booking->payment_method,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'created_at' => $payment->created_at->toIso8601String(),
                'has_payment' => true,
            ]
        ]);
    }

    /**
     * Initiate payment for booking
     */
    public function initiatePayment(Request $request): JsonResponse
    {
        Log::info('=== PAYMENT INITIATION STARTED ===');
        Log::info('Request data:', $request->all());

        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'payment_method_id' => 'required|integer',
        ]);

        $user = $request->user();
        $booking = Booking::with(['client', 'provider'])->findOrFail($request->booking_id);

        Log::info('Booking details:', [
            'id' => $booking->id,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'confirmed_at' => $booking->confirmed_at,
        ]);

        // Verify booking belongs to user
        if ($booking->client_id !== $user->id) {
            throw ValidationException::withMessages([
                'booking_id' => ['Unauthorized access to booking.']
            ]);
        }

        // Verify booking status
        if ($booking->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'booking_id' => ['Booking must be confirmed by provider first.']
            ]);
        }

        if ($booking->payment_status !== 'pending') {
            throw ValidationException::withMessages([
                'booking_id' => ['Payment already processed for this booking.']
            ]);
        }

        // Check payment timeout
        $timeoutMinutes = config('app.payment_timeout_minutes', 5);
        $confirmedAt = $booking->confirmed_at;
        
        if ($confirmedAt && $confirmedAt->addMinutes($timeoutMinutes)->isPast()) {
            // Auto-cancel booking
            $booking->update([
                'status' => 'cancelled',
                'cancelled_by' => 'system',
                'cancellation_reason' => 'Payment timeout',
                'cancelled_at' => now()
            ]);

            throw ValidationException::withMessages([
                'booking_id' => ['Payment time has expired. Booking has been cancelled.']
            ]);
        }

        DB::beginTransaction();
        try {
            Log::info('Calling MyFatoorah executePayment...');

            // Execute payment with MyFatoorah
            $result = $this->myFatoorah->executePayment([
                'payment_method_id' => $request->payment_method_id,
                'amount' => $booking->total_amount,
                'customer_name' => $user->name,
                'customer_mobile' => $user->phone,
                'customer_email' => $user->email,
                'booking_id' => $booking->id,
                'client_id' => $user->id,
                'language' => $request->header('Accept-Language', 'ar'),
            ]);

            Log::info('MyFatoorah response:', $result);

            if (!$result['success']) {
                DB::rollBack();
                Log::error('MyFatoorah failed:', [
                    'message' => $result['message'] ?? 'Unknown error',
                    'result' => $result
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Payment initiation failed',
                    'error' => $result['message'] ?? 'Unknown error'
                ], 400);
            }

            $paymentData = $result['data']['Data'];
            Log::info('Payment data from MyFatoorah:', $paymentData);

            // Create payment record
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'payment_id' => $paymentData['InvoiceId'],
                'amount' => $booking->total_amount,
                'currency' => 'SAR',
                'gateway' => 'myfatoorah',
                'method' => $this->getPaymentMethodName($request->payment_method_id),
                'status' => 'pending',
                'gateway_response' => $result['data'],
                'gateway_transaction_id' => $paymentData['InvoiceId'] ?? null,
            ]);

            // Update booking payment reference (status remains 'pending' until payment confirmed)
            $booking->update([
                'payment_reference' => $payment->payment_id,
            ]);

            DB::commit();

            Log::info('=== PAYMENT INITIATED SUCCESSFULLY ===');

            return response()->json([
                'success' => true,
                'message' => 'Payment initiated successfully',
                'data' => [
                    'payment_id' => $payment->id,
                    'payment_url' => $paymentData['PaymentURL'],
                    'invoice_id' => $paymentData['InvoiceId'],
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('=== PAYMENT INITIATION EXCEPTION ===', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment initiation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Webhook handler for payment notifications from MyFatoorah
     * Documentation: https://docs.myfatoorah.com/docs/webhook
     */
    public function webhook(Request $request): JsonResponse
    {
        Log::info('=== MYFATOORAH WEBHOOK RECEIVED ===', [
            'ip' => $request->ip(),
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
        ]);

        // Verify webhook signature for security (MyFatoorah V2 Webhook)
        // SECURITY WARNING: Signature validation is currently DISABLED
        // TODO: Re-enable this after getting the correct webhook secret from MyFatoorah portal
        $webhookSecret = config('services.myfatoorah.webhook_secret');

        if (false && $webhookSecret && $webhookSecret !== 'your_webhook_secret') {
            // MyFatoorah V2 sends signature in 'myfatoorah-signature' header
            $signature = $request->header('myfatoorah-signature')
                      ?? $request->header('MyFatoorah-Signature')
                      ?? $request->header('X-Webhook-Signature')
                      ?? $request->header('Signature');

            $data = $request->all();

            // Build signature string according to MyFatoorah V2 spec for PAYMENT_STATUS_CHANGED
            // Format: Invoice.Id=value,Invoice.Status=value,Transaction.Status=value,Transaction.PaymentId=value,Invoice.ExternalIdentifier=value
            $invoiceId = $data['Data']['Invoice']['Id'] ?? '';
            $invoiceStatus = $data['Data']['Invoice']['Status'] ?? '';
            $transactionStatus = $data['Data']['Transaction']['Status'] ?? '';
            $paymentId = $data['Data']['Transaction']['PaymentId'] ?? '';
            $externalIdentifier = $data['Data']['Invoice']['ExternalIdentifier'] ?? '';

            // Build the signature string
            $signatureString = "Invoice.Id={$invoiceId},Invoice.Status={$invoiceStatus},Transaction.Status={$transactionStatus},Transaction.PaymentId={$paymentId},Invoice.ExternalIdentifier={$externalIdentifier}";

            // Generate HMAC-SHA256 signature and base64 encode it
            $expectedSignature = base64_encode(hash_hmac('sha256', $signatureString, $webhookSecret, true));

            if (!hash_equals($expectedSignature, $signature ?? '')) {
                Log::warning('Invalid webhook signature from MyFatoorah', [
                    'ip' => $request->ip(),
                    'received_signature' => $signature,
                    'expected_signature' => $expectedSignature,
                    'signature_string' => $signatureString,
                    'invoice_id' => $invoiceId,
                    'all_headers' => $request->headers->all(),
                ]);
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
            }

            Log::info('Webhook signature verified successfully', [
                'invoice_id' => $invoiceId,
                'transaction_status' => $transactionStatus,
            ]);
        }

        Log::warning('⚠️ SECURITY WARNING: Webhook signature validation is DISABLED. This is insecure for production.');

        // MyFatoorah sends data in various formats depending on webhook version
        // Try to extract invoice/payment ID from different possible locations
        $data = $request->all();
        $paymentId = $data['Data']['Invoice']['Id']  // V2 format
                  ?? $data['Data']['InvoiceId']        // V1 format
                  ?? $data['InvoiceId']
                  ?? $data['data']['InvoiceId']
                  ?? null;

        if (!$paymentId) {
            Log::warning('Webhook received without payment ID', [
                'full_payload' => $data,
            ]);
            return response()->json(['success' => false, 'message' => 'Missing payment ID'], 400);
        }

        Log::info('Processing webhook for payment', ['payment_id' => $paymentId]);

        // Process payment status update directly from webhook data (V2 format)
        try {
            // Webhook V2 already contains all the data we need - no need to call API again
            $this->processPaymentStatusV2($data);

            Log::info('=== WEBHOOK PROCESSED SUCCESSFULLY ===', [
                'payment_id' => $paymentId,
            ]);

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('=== WEBHOOK PROCESSING FAILED ===', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Processing failed'], 500);
        }
    }

    /**
     * Payment Callback - User returns here after payment (works for test & live)
     * This is a BACKUP for when webhooks don't work (test environment)
     */
    public function paymentCallback(Request $request)
    {
        Log::info('=== PAYMENT CALLBACK RECEIVED ===', [
            'query_params' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        // MyFatoorah sends paymentId in query params
        $paymentId = $request->query('paymentId') ?? $request->query('Id');

        if (!$paymentId) {
            Log::warning('Payment callback without paymentId');
            return response()->json(['success' => false, 'message' => 'Missing payment ID'], 400);
        }

        try {
            // Call MyFatoorah API to get payment status
            Log::info('Fetching payment status from MyFatoorah', ['payment_id' => $paymentId]);
            $result = $this->myFatoorah->getPaymentStatus($paymentId);

            if ($result['success']) {
                // Process the payment status (same logic as webhook)
                $this->processPaymentStatus($result['data']);

                Log::info('=== PAYMENT CALLBACK PROCESSED SUCCESSFULLY ===', [
                    'payment_id' => $paymentId,
                ]);

                // Redirect to success page or return JSON for mobile app
                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'data' => [
                        'payment_id' => $paymentId,
                        'status' => $result['data']['Data']['InvoiceStatus'] ?? 'unknown'
                    ]
                ]);
            } else {
                Log::error('Failed to get payment status', [
                    'payment_id' => $paymentId,
                    'error' => $result['message'] ?? 'Unknown error'
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify payment status'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('=== PAYMENT CALLBACK FAILED ===', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed'
            ], 500);
        }
    }

    /**
     * Helper: Get payment method name
     */
    protected function getPaymentMethodName($methodId): string
    {
        // Map payment method IDs to names
        $methods = [
            1 => 'mada',
            2 => 'visa',
            3 => 'master',
            4 => 'apple_pay',
            // Add more as needed
        ];

        return $methods[$methodId] ?? 'unknown';
    }

    /**
     * Pay with wallet/balance
     * Supports both:
     * - POST /bookings/{id}/pay-with-wallet (booking_id in URL)
     * - POST /payments/wallet (booking_id in body) - for backward compatibility
     */
    public function payWithWallet(Request $request, $id = null): JsonResponse
    {
        Log::info('=== WALLET PAYMENT STARTED ===');
        Log::info('Request data:', $request->all());
        Log::info('URL param id:', ['id' => $id]);

        // Get booking_id from URL parameter or request body
        $bookingId = $id ?? $request->input('booking_id');

        if (!$bookingId) {
            return response()->json([
                'success' => false,
                'message' => 'Booking ID is required'
            ], 400);
        }

        $user = $request->user();
        $booking = Booking::with(['client', 'provider'])->find($bookingId);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking not found'
            ], 404);
        }

        Log::info('Booking details:', [
            'id' => $booking->id,
            'status' => $booking->status,
            'payment_status' => $booking->payment_status,
            'total_amount' => $booking->total_amount,
        ]);

        // Verify booking belongs to user
        if ($booking->client_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to booking.'
            ], 403);
        }

        // Verify booking status
        if ($booking->status !== 'confirmed') {
            return response()->json([
                'success' => false,
                'message' => 'Booking must be confirmed by provider first.'
            ], 400);
        }

        if ($booking->payment_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Booking has already been paid'
            ], 400);
        }

        // Check if user has sufficient balance (if wallet balance exists)
        // Note: Add wallet_balance column to users table if not exists
        $userBalance = $user->wallet_balance ?? 0;
        if ($userBalance < $booking->total_amount) {
            $shortfall = $booking->total_amount - $userBalance;
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance',
                'data' => [
                    'required_amount' => (float) $booking->total_amount,
                    'current_balance' => (float) $userBalance,
                    'shortfall' => (float) $shortfall,
                ]
            ], 400);
        }

        DB::beginTransaction();
        try {
            $balanceBefore = $userBalance;
            $balanceAfter = $userBalance - $booking->total_amount;

            // Create wallet transaction record
            $walletTransaction = \App\Models\WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'payment',
                'amount' => $booking->total_amount,
                'description' => "Payment for booking #{$booking->id}",
                'reference_number' => 'BOOK-' . $booking->id,
                'related_id' => $booking->id,
                'related_type' => 'booking',
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'metadata' => [
                    'booking_id' => $booking->id,
                    'provider_id' => $booking->provider_id,
                ],
            ]);

            // Deduct from wallet
            $user->decrement('wallet_balance', $booking->total_amount);

            // Create payment record
            $payment = Payment::create([
                'booking_id' => $booking->id,
                'payment_id' => 'WALLET_' . $booking->id . '_' . now()->timestamp,
                'amount' => $booking->total_amount,
                'currency' => 'SAR',
                'gateway' => 'wallet',
                'method' => 'wallet',
                'status' => 'completed',
                'gateway_response' => [
                    'payment_method' => 'wallet',
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'transaction_id' => $walletTransaction->id,
                ],
                'paid_at' => now(),
            ]);

            // Update booking to paid status
            $booking->update([
                'payment_status' => 'paid',
                'payment_method' => 'wallet',
                'payment_reference' => $payment->payment_id,
            ]);

            // Delete payment request notification completely so it disappears from notification list
            $deletedCount = \App\Models\Notification::where('user_id', $booking->client_id)
                ->where('type', 'booking_accepted')
                ->where('data->booking_id', $booking->id)
                ->delete();

            Log::info('Payment notification deleted', [
                'booking_id' => $booking->id,
                'client_id' => $booking->client_id,
                'deleted_count' => $deletedCount,
            ]);

            DB::commit();

            Log::info('=== WALLET PAYMENT COMPLETED ===', [
                'booking_id' => $booking->id,
                'amount' => $booking->total_amount,
                'new_balance' => $userBalance - $booking->total_amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Booking paid successfully with wallet',
                'data' => [
                    'booking_id' => $booking->id,
                    'transaction_id' => $walletTransaction->id,
                    'amount_paid' => (float) $booking->total_amount,
                    'new_balance' => (float) ($userBalance - $booking->total_amount),
                    'payment_status' => 'paid',
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('=== WALLET PAYMENT EXCEPTION ===', [
                'booking_id' => $booking->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Process payment status from Webhook V2
     */
    protected function processPaymentStatusV2(array $webhookData)
    {
        $invoiceData = $webhookData['Data']['Invoice'] ?? [];
        $transactionData = $webhookData['Data']['Transaction'] ?? [];

        $paymentId = $invoiceData['Id'] ?? null;
        $invoiceStatus = $invoiceData['Status'] ?? null; // PAID or PENDING
        $transactionStatus = $transactionData['Status'] ?? null; // SUCCESS, FAILED, etc.

        if (!$paymentId) {
            Log::warning('Webhook V2 missing payment ID');
            return;
        }

        $payment = Payment::where('payment_id', $paymentId)->first();

        if (!$payment) {
            Log::warning('Payment record not found for webhook processing', [
                'payment_id' => $paymentId,
            ]);
            return;
        }

        DB::transaction(function () use ($payment, $invoiceData, $transactionData, $webhookData, $invoiceStatus, $transactionStatus) {
            if ($invoiceStatus === 'PAID' && $transactionStatus === 'SUCCESS') {
                Log::info('Processing successful payment from webhook V2', [
                    'payment_id' => $invoiceData['Id'],
                    'booking_id' => $payment->booking_id,
                    'invoice_status' => $invoiceStatus,
                    'transaction_status' => $transactionStatus,
                ]);

                $payment->update([
                    'status' => 'completed',
                    'gateway_response' => $webhookData,
                    'paid_at' => now(),
                ]);

                $payment->booking->update([
                    'payment_status' => 'paid',
                    'payment_method' => $transactionData['PaymentMethod'] ?? 'myfatoorah',
                ]);

                // Delete payment request notification completely so it stops showing "Pay Now"
                $deletedCount = \App\Models\Notification::where('user_id', $payment->booking->client_id)
                    ->where('type', 'booking_accepted')
                    ->where('data->booking_id', $payment->booking_id)
                    ->delete();

                Log::info('Deleted booking_accepted notification', [
                    'client_id' => $payment->booking->client_id,
                    'booking_id' => $payment->booking_id,
                    'deleted_count' => $deletedCount,
                ]);

                // Send payment completed notification to dashboard users (admin notifications)
                $this->sendPaymentCompletedNotification($payment->booking);

                // Send FCM push notification to mobile app (includes dismissal of old notification)
                try {
                    $fcmService = app(\App\Services\FCMService::class);
                    $fcmService->sendPaymentCompleted($payment->booking, $payment);
                    Log::info('FCM payment completed notification sent (V2 webhook)', [
                        'booking_id' => $payment->booking_id,
                        'payment_id' => $payment->id,
                    ]);
                } catch (\Exception $fcmException) {
                    Log::error('Failed to send FCM payment completed notification', [
                        'error' => $fcmException->getMessage(),
                        'booking_id' => $payment->booking_id,
                    ]);
                }

                Log::info('=== PAYMENT COMPLETED SUCCESSFULLY ===', [
                    'payment_id' => $invoiceData['Id'],
                    'booking_id' => $payment->booking_id,
                    'notification_deleted' => true,
                ]);
            } else {
                // Handle failed or pending payment
                Log::warning('Processing non-successful payment from webhook V2', [
                    'payment_id' => $invoiceData['Id'],
                    'invoice_status' => $invoiceStatus,
                    'transaction_status' => $transactionStatus,
                    'error' => $transactionData['Error'] ?? null,
                ]);

                if ($transactionStatus === 'FAILED') {
                    $failureReason = $transactionData['Error']['Message'] ?? 'Payment failed';

                    $payment->update([
                        'status' => 'failed',
                        'failure_reason' => $failureReason,
                        'gateway_response' => $webhookData,
                    ]);

                    $payment->booking->update([
                        'payment_status' => 'failed',
                    ]);

                    // Send FCM push notification for payment failure
                    try {
                        $fcmService = app(\App\Services\FCMService::class);
                        $fcmService->sendPaymentFailed($payment->booking, $payment, $failureReason);
                        Log::info('FCM payment failed notification sent', [
                            'booking_id' => $payment->booking_id,
                            'payment_id' => $payment->id,
                        ]);
                    } catch (\Exception $fcmException) {
                        Log::error('Failed to send FCM payment failed notification', [
                            'error' => $fcmException->getMessage(),
                            'booking_id' => $payment->booking_id,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Send notification to dashboard users about payment completion
     */
    protected function sendPaymentCompletedNotification($booking)
    {
        try {
            // Get all users with dashboard access (super_admin, admin, manager)
            $dashboardUsers = \App\Models\User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['super_admin', 'admin', 'manager']);
            })->get();

            foreach ($dashboardUsers as $user) {
                \App\Models\Notification::create([
                    'user_id' => $user->id,
                    'type' => 'payment_completed',
                    'title' => 'Payment Completed',
                    'body' => "Payment received for booking #{$booking->id}. Amount: {$booking->total_amount} SAR",
                    'data' => [
                        'booking_id' => $booking->id,
                        'amount' => $booking->total_amount,
                        'client_id' => $booking->client_id,
                    ],
                    'is_read' => false,
                ]);
            }

            Log::info('Payment completed notifications sent to dashboard users', [
                'booking_id' => $booking->id,
                'users_notified' => $dashboardUsers->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send payment completed notifications', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Helper: Process payment status (legacy - for GetPaymentStatus API response)
     */
    protected function processPaymentStatus(array $data)
    {
        $paymentData = $data['Data'];
        $paymentId = $paymentData['InvoiceId'];
        $payment = Payment::where('payment_id', $paymentId)->first();

        if (!$payment) {
            Log::warning('Payment record not found for webhook processing', [
                'payment_id' => $paymentId,
            ]);
            return;
        }

        DB::transaction(function () use ($payment, $paymentData, $data) {
            $invoiceStatus = $paymentData['InvoiceStatus'];

            if ($invoiceStatus === 'Paid') {
                Log::info('Processing successful payment from webhook', [
                    'payment_id' => $paymentData['InvoiceId'],
                    'booking_id' => $payment->booking_id,
                ]);

                $payment->update([
                    'status' => 'completed',
                    'gateway_response' => $data,
                    'paid_at' => now(),
                ]);

                $payment->booking->update([
                    'payment_status' => 'paid',
                    'payment_method' => $paymentData['PaymentGateway'] ?? 'myfatoorah',
                ]);

                // Delete payment request notification completely so it stops showing "Pay Now"
                $deletedCount = \App\Models\Notification::where('user_id', $payment->booking->client_id)
                    ->where('type', 'booking_accepted')
                    ->where('data->booking_id', $payment->booking_id)
                    ->delete();

                Log::info('Deleted booking_accepted notification', [
                    'client_id' => $payment->booking->client_id,
                    'booking_id' => $payment->booking_id,
                    'deleted_count' => $deletedCount,
                ]);

                // Send payment completed notification to dashboard users (admin notifications)
                $this->sendPaymentCompletedNotification($payment->booking);

                // Send FCM push notification to mobile app (includes dismissal of old notification)
                try {
                    $fcmService = app(\App\Services\FCMService::class);
                    $fcmService->sendPaymentCompleted($payment->booking, $payment);
                    Log::info('FCM payment completed notification sent (callback)', [
                        'booking_id' => $payment->booking_id,
                        'payment_id' => $payment->id,
                    ]);
                } catch (\Exception $fcmException) {
                    Log::error('Failed to send FCM payment completed notification', [
                        'error' => $fcmException->getMessage(),
                        'booking_id' => $payment->booking_id,
                    ]);
                }

                Log::info('=== PAYMENT COMPLETED SUCCESSFULLY ===', [
                    'payment_id' => $paymentData['InvoiceId'],
                    'booking_id' => $payment->booking_id,
                    'notification_deleted' => true,
                ]);
            } else {
                // Handle failed payment
                $failureReason = $paymentData['InvoiceError'] ?? 'Payment failed';

                Log::warning('Processing failed payment from webhook', [
                    'payment_id' => $paymentData['InvoiceId'],
                    'invoice_status' => $invoiceStatus,
                    'invoice_error' => $failureReason,
                ]);

                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $failureReason,
                    'gateway_response' => $data,
                ]);

                $payment->booking->update([
                    'payment_status' => 'failed',
                ]);

                // Send FCM push notification for payment failure
                try {
                    $fcmService = app(\App\Services\FCMService::class);
                    $fcmService->sendPaymentFailed($payment->booking, $payment, $failureReason);
                    Log::info('FCM payment failed notification sent', [
                        'booking_id' => $payment->booking_id,
                        'payment_id' => $payment->id,
                    ]);
                } catch (\Exception $fcmException) {
                    Log::error('Failed to send FCM payment failed notification', [
                        'error' => $fcmException->getMessage(),
                        'booking_id' => $payment->booking_id,
                    ]);
                }

                Log::info('=== PAYMENT FAILED ===', [
                    'payment_id' => $paymentData['InvoiceId'],
                    'reason' => $failureReason,
                ]);
            }
        });
    }

    /**
     * Test FCM Payment Completed Notification
     * POST /api/v1/admin/test/fcm/payment-completed
     * Body: { "booking_id": 1 }
     */
    public function testFCMPaymentCompleted(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = \App\Models\Booking::with('payment')->findOrFail($request->booking_id);

        if (!$booking->payment) {
            return response()->json([
                'success' => false,
                'message' => 'No payment found for this booking',
            ], 404);
        }

        try {
            $fcmService = app(\App\Services\FCMService::class);
            $result = $fcmService->sendPaymentCompleted($booking, $booking->payment);

            return response()->json([
                'success' => true,
                'message' => 'FCM payment completed notification sent',
                'data' => [
                    'booking_id' => $booking->id,
                    'payment_id' => $booking->payment->id,
                    'topic' => "user_{$booking->client_id}_booking_{$booking->id}_payment",
                    'fcm_result' => $result,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send FCM notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test FCM Payment Failed Notification
     * POST /api/v1/admin/test/fcm/payment-failed
     * Body: { "booking_id": 1, "error_message": "Card declined" }
     */
    public function testFCMPaymentFailed(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'error_message' => 'nullable|string',
        ]);

        $booking = \App\Models\Booking::with('payment')->findOrFail($request->booking_id);

        if (!$booking->payment) {
            return response()->json([
                'success' => false,
                'message' => 'No payment found for this booking',
            ], 404);
        }

        $errorMessage = $request->error_message ?? 'Test payment failure';

        try {
            $fcmService = app(\App\Services\FCMService::class);
            $result = $fcmService->sendPaymentFailed($booking, $booking->payment, $errorMessage);

            return response()->json([
                'success' => true,
                'message' => 'FCM payment failed notification sent',
                'data' => [
                    'booking_id' => $booking->id,
                    'payment_id' => $booking->payment->id,
                    'topic' => "user_{$booking->client_id}_booking_{$booking->id}_payment",
                    'error_message' => $errorMessage,
                    'fcm_result' => $result,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send FCM notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test FCM Payment Timeout Notification
     * POST /api/v1/admin/test/fcm/payment-timeout
     * Body: { "booking_id": 1 }
     */
    public function testFCMPaymentTimeout(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $booking = \App\Models\Booking::with('payment')->findOrFail($request->booking_id);

        if (!$booking->payment) {
            return response()->json([
                'success' => false,
                'message' => 'No payment found for this booking',
            ], 404);
        }

        try {
            $fcmService = app(\App\Services\FCMService::class);
            $result = $fcmService->sendPaymentTimeout($booking, $booking->payment);

            return response()->json([
                'success' => true,
                'message' => 'FCM payment timeout notification sent',
                'data' => [
                    'booking_id' => $booking->id,
                    'payment_id' => $booking->payment->id,
                    'topic' => "user_{$booking->client_id}_booking_{$booking->id}_payment",
                    'fcm_result' => $result,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send FCM notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}