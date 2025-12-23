<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletDeposit;
use App\Services\MyFatoorahService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class WalletController extends Controller
{
    protected $myFatoorah;

    public function __construct(MyFatoorahService $myFatoorah)
    {
        $this->myFatoorah = $myFatoorah;
    }

    /**
     * Get wallet balance
     * GET /v1/wallet
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'balance' => (float) $user->wallet_balance,
                'currency' => 'SAR',
                'updated_at' => $user->updated_at->toIso8601String(),
            ]
        ]);
    }

    /**
     * Get transaction history
     * GET /v1/wallet/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'type' => 'nullable|in:deposit,payment,refund,withdrawal',
        ]);

        $user = $request->user();
        $perPage = $request->input('per_page', 20);

        $query = WalletTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
            ]
        ]);
    }

    /**
     * Initiate wallet deposit
     * POST /v1/wallet/deposit
     */
    public function deposit(Request $request): JsonResponse
    {
        Log::info('=== WALLET DEPOSIT INITIATED ===');
        Log::info('Request data:', $request->all());

        $request->validate([
            'amount' => 'required|numeric|min:10',
        ]);

        $user = $request->user();
        $amount = $request->input('amount');

        DB::beginTransaction();
        try {
            // Call MyFatoorah to initiate payment and get payment methods
            $initiateResult = $this->myFatoorah->initiatePayment([
                'amount' => $amount,
                'currency' => 'SAR',
            ]);

            if (!$initiateResult['success']) {
                DB::rollBack();
                Log::error('MyFatoorah initiate payment failed', $initiateResult);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to initiate deposit',
                ], 500);
            }

            // Get payment method (we'll use the first available one)
            $paymentMethods = $initiateResult['data']['Data']['PaymentMethods'] ?? [];
            if (empty($paymentMethods)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'No payment methods available',
                ], 500);
            }

            $paymentMethodId = $paymentMethods[0]['PaymentMethodId'];

            // Callback URL for when user completes/cancels payment
            $callbackUrl = url('/api/v1/wallet/callback');

            // Execute payment to get payment URL
            $executeResult = $this->myFatoorah->executePayment([
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'customer_name' => $user->name,
                'customer_mobile' => $user->phone,
                'customer_email' => $user->email ?? '',
                'booking_id' => 'WALLET_DEPOSIT',
                'client_id' => $user->id,
                'language' => $request->header('Accept-Language', 'ar'),
            ]);

            if (!$executeResult['success']) {
                DB::rollBack();
                Log::error('MyFatoorah execute payment failed', $executeResult);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create payment URL',
                ], 500);
            }

            $paymentData = $executeResult['data']['Data'];
            $invoiceId = $paymentData['InvoiceId'];
            $paymentUrl = $paymentData['PaymentURL'];

            // Create wallet deposit record
            $deposit = WalletDeposit::create([
                'user_id' => $user->id,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'status' => 'pending',
                'payment_url' => $paymentUrl,
                'request_payload' => $request->all(),
                'response_payload' => $executeResult['data'],
            ]);

            DB::commit();

            Log::info('=== WALLET DEPOSIT CREATED ===', [
                'deposit_id' => $deposit->id,
                'invoice_id' => $invoiceId,
                'amount' => $amount,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_url' => $paymentUrl,
                    'invoice_id' => $invoiceId,
                    'amount' => (float) $amount,
                    'currency' => 'SAR',
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('=== WALLET DEPOSIT EXCEPTION ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process deposit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify wallet deposit payment
     * POST /v1/wallet/verify-payment
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        Log::info('=== WALLET PAYMENT VERIFICATION STARTED ===');
        Log::info('Request data:', $request->all());

        $request->validate([
            'payment_id' => 'required|string',
        ]);

        $user = $request->user();
        $paymentId = $request->input('payment_id');

        try {
            // Get payment status from MyFatoorah
            $result = $this->myFatoorah->getPaymentStatus($paymentId);

            if (!$result['success']) {
                Log::error('Failed to get payment status', $result);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify payment',
                ], 500);
            }

            $paymentData = $result['data']['Data'];
            $invoiceId = $paymentData['InvoiceId'];
            $invoiceStatus = $paymentData['InvoiceStatus'];

            // Find the deposit record
            $deposit = WalletDeposit::where('invoice_id', $invoiceId)
                ->where('user_id', $user->id)
                ->first();

            if (!$deposit) {
                Log::warning('Deposit not found', ['invoice_id' => $invoiceId]);
                return response()->json([
                    'success' => false,
                    'message' => 'Deposit record not found',
                ], 404);
            }

            // If already processed, return the status
            if ($deposit->status !== 'pending') {
                return response()->json([
                    'success' => $deposit->status === 'success',
                    'message' => $deposit->status === 'success'
                        ? 'Payment already processed'
                        : 'Payment verification failed',
                    'data' => [
                        'payment_status' => $deposit->status,
                        'amount' => (float) $deposit->amount,
                        'balance' => (float) $user->fresh()->wallet_balance,
                    ]
                ]);
            }

            DB::beginTransaction();
            try {
                if ($invoiceStatus === 'Paid') {
                    // Get current balance
                    $balanceBefore = $user->wallet_balance;
                    $balanceAfter = $balanceBefore + $deposit->amount;

                    // Create wallet transaction
                    $transaction = WalletTransaction::create([
                        'user_id' => $user->id,
                        'type' => 'deposit',
                        'amount' => $deposit->amount,
                        'description' => 'Wallet deposit via MyFatoorah',
                        'reference_number' => $invoiceId,
                        'related_id' => $deposit->id,
                        'related_type' => 'deposit',
                        'balance_before' => $balanceBefore,
                        'balance_after' => $balanceAfter,
                        'metadata' => [
                            'invoice_id' => $invoiceId,
                            'payment_method' => $paymentData['PaymentGateway'] ?? 'myfatoorah',
                        ],
                    ]);

                    // Update user wallet balance
                    $user->increment('wallet_balance', $deposit->amount);

                    // Update deposit status
                    $deposit->update([
                        'status' => 'success',
                        'response_payload' => $result['data'],
                        'wallet_transaction_id' => $transaction->id,
                        'completed_at' => now(),
                    ]);

                    DB::commit();

                    Log::info('=== WALLET DEPOSIT COMPLETED ===', [
                        'deposit_id' => $deposit->id,
                        'transaction_id' => $transaction->id,
                        'amount' => $deposit->amount,
                        'new_balance' => $balanceAfter,
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Payment verified and wallet credited successfully',
                        'data' => [
                            'transaction_id' => $transaction->id,
                            'amount' => (float) $deposit->amount,
                            'new_balance' => (float) $balanceAfter,
                            'payment_status' => 'success',
                        ]
                    ]);

                } else {
                    // Payment failed
                    $deposit->update([
                        'status' => 'failed',
                        'response_payload' => $result['data'],
                        'completed_at' => now(),
                    ]);

                    DB::commit();

                    Log::warning('=== WALLET DEPOSIT FAILED ===', [
                        'deposit_id' => $deposit->id,
                        'invoice_status' => $invoiceStatus,
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Payment verification failed',
                        'data' => [
                            'payment_status' => 'failed',
                            'error_message' => $paymentData['InvoiceError'] ?? 'Payment was not successful',
                        ]
                    ]);
                }

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('=== WALLET PAYMENT VERIFICATION EXCEPTION ===', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Payment callback - User returns here after payment
     * GET /v1/wallet/callback
     */
    public function callback(Request $request)
    {
        Log::info('=== WALLET PAYMENT CALLBACK RECEIVED ===', [
            'query_params' => $request->all(),
        ]);

        $paymentId = $request->query('paymentId') ?? $request->query('Id');

        if (!$paymentId) {
            return response()->json([
                'success' => false,
                'message' => 'Missing payment ID'
            ], 400);
        }

        try {
            // Get payment status from MyFatoorah
            $result = $this->myFatoorah->getPaymentStatus($paymentId);

            if ($result['success']) {
                $paymentData = $result['data']['Data'];
                $invoiceId = $paymentData['InvoiceId'];

                // Find deposit and process it
                $deposit = WalletDeposit::where('invoice_id', $invoiceId)->first();

                if ($deposit && $deposit->status === 'pending') {
                    $this->processDepositCallback($deposit, $paymentData);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment processed',
                    'data' => [
                        'payment_id' => $paymentId,
                        'status' => $paymentData['InvoiceStatus'] ?? 'unknown'
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment'
            ], 500);

        } catch (\Exception $e) {
            Log::error('=== WALLET CALLBACK EXCEPTION ===', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed'
            ], 500);
        }
    }

    /**
     * Helper: Process deposit from callback
     */
    protected function processDepositCallback(WalletDeposit $deposit, array $paymentData)
    {
        DB::transaction(function () use ($deposit, $paymentData) {
            $invoiceStatus = $paymentData['InvoiceStatus'];

            if ($invoiceStatus === 'Paid') {
                $user = $deposit->user;
                $balanceBefore = $user->wallet_balance;
                $balanceAfter = $balanceBefore + $deposit->amount;

                // Create wallet transaction
                $transaction = WalletTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'deposit',
                    'amount' => $deposit->amount,
                    'description' => 'Wallet deposit via MyFatoorah',
                    'reference_number' => $deposit->invoice_id,
                    'related_id' => $deposit->id,
                    'related_type' => 'deposit',
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'metadata' => [
                        'invoice_id' => $deposit->invoice_id,
                        'payment_method' => $paymentData['PaymentGateway'] ?? 'myfatoorah',
                    ],
                ]);

                // Update user balance
                $user->increment('wallet_balance', $deposit->amount);

                // Update deposit
                $deposit->update([
                    'status' => 'success',
                    'response_payload' => $paymentData,
                    'wallet_transaction_id' => $transaction->id,
                    'completed_at' => now(),
                ]);

                Log::info('Deposit processed from callback', [
                    'deposit_id' => $deposit->id,
                    'amount' => $deposit->amount,
                ]);
            } else {
                $deposit->update([
                    'status' => 'failed',
                    'response_payload' => $paymentData,
                    'completed_at' => now(),
                ]);

                Log::warning('Deposit failed from callback', [
                    'deposit_id' => $deposit->id,
                    'invoice_status' => $invoiceStatus,
                ]);
            }
        });
    }
}
