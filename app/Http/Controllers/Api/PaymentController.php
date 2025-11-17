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
        $webhookSecret = config('services.myfatoorah.webhook_secret');

        if ($webhookSecret && $webhookSecret !== 'your_webhook_secret') {
            $signature = $request->header('X-Webhook-Signature') ?? $request->header('Signature');
            $payload = $request->getContent();
            $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

            if (!hash_equals($expectedSignature, $signature ?? '')) {
                Log::warning('Invalid webhook signature from MyFatoorah', [
                    'ip' => $request->ip(),
                    'received_signature' => $signature,
                    'expected_signature' => substr($expectedSignature, 0, 20) . '...',
                ]);
                return response()->json(['success' => false, 'message' => 'Invalid signature'], 401);
            }
        }

        // MyFatoorah sends data in various formats depending on webhook version
        // Try to extract invoice/payment ID from different possible locations
        $data = $request->all();
        $paymentId = $data['Data']['InvoiceId']
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

        // Process payment status update
        try {
            $result = $this->myFatoorah->getPaymentStatus($paymentId);

            if ($result['success']) {
                $this->processPaymentStatus($result['data']);

                Log::info('=== WEBHOOK PROCESSED SUCCESSFULLY ===', [
                    'payment_id' => $paymentId,
                ]);
            }

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
     */
    public function payWithWallet(Request $request): JsonResponse
    {
        Log::info('=== WALLET PAYMENT STARTED ===');
        Log::info('Request data:', $request->all());

        $request->validate([
            'booking_id' => 'required|exists:bookings,id',
        ]);

        $user = $request->user();
        $booking = Booking::with(['client', 'provider'])->findOrFail($request->booking_id);

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
                'message' => 'Payment already processed for this booking.'
            ], 400);
        }

        // Check if user has sufficient balance (if wallet balance exists)
        // Note: Add wallet_balance column to users table if not exists
        $userBalance = $user->wallet_balance ?? 0;
        if ($userBalance < $booking->total_amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient wallet balance.',
                'current_balance' => $userBalance,
                'required_amount' => $booking->total_amount
            ], 400);
        }

        DB::beginTransaction();
        try {
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
                    'balance_before' => $userBalance,
                    'balance_after' => $userBalance - $booking->total_amount,
                ],
                'paid_at' => now(),
            ]);

            // Update booking to paid status
            $booking->update([
                'payment_status' => 'paid',
                'payment_method' => 'wallet',
                'payment_reference' => $payment->payment_id,
            ]);

            // Mark payment request notification as read so it disappears from notification list
            \App\Models\Notification::where('user_id', $booking->client_id)
                ->where('type', 'booking_accepted')
                ->where('data->booking_id', $booking->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);

            Log::info('Payment notification marked as read', [
                'booking_id' => $booking->id,
                'client_id' => $booking->client_id,
            ]);

            DB::commit();

            Log::info('=== WALLET PAYMENT COMPLETED ===', [
                'booking_id' => $booking->id,
                'amount' => $booking->total_amount,
                'new_balance' => $userBalance - $booking->total_amount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment completed successfully',
                'data' => [
                    'booking_id' => $booking->id,
                    'payment_id' => $payment->id,
                    'amount_paid' => $booking->total_amount,
                    'new_balance' => $userBalance - $booking->total_amount,
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
     * Helper: Process payment status
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

                // Mark payment request notification as read so it disappears
                \App\Models\Notification::where('user_id', $payment->booking->client_id)
                    ->where('type', 'booking_accepted')
                    ->where('data->booking_id', $payment->booking_id)
                    ->where('is_read', false)
                    ->update(['is_read' => true]);

                Log::info('=== PAYMENT COMPLETED SUCCESSFULLY ===', [
                    'payment_id' => $paymentData['InvoiceId'],
                    'booking_id' => $payment->booking_id,
                    'notification_marked_read' => true,
                ]);
            } else {
                // Handle failed payment
                Log::warning('Processing failed payment from webhook', [
                    'payment_id' => $paymentData['InvoiceId'],
                    'invoice_status' => $invoiceStatus,
                    'invoice_error' => $paymentData['InvoiceError'] ?? 'No error message',
                ]);

                $payment->update([
                    'status' => 'failed',
                    'failure_reason' => $paymentData['InvoiceError'] ?? 'Payment failed',
                    'gateway_response' => $data,
                ]);

                $payment->booking->update([
                    'payment_status' => 'failed',
                ]);

                Log::info('=== PAYMENT FAILED ===', [
                    'payment_id' => $paymentData['InvoiceId'],
                    'reason' => $paymentData['InvoiceError'] ?? 'Unknown',
                ]);
            }
        });
    }
}