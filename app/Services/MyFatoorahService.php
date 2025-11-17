<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MyFatoorahService
{
    protected ?string $apiKey;
    protected ?string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.myfatoorah.api_key');
        $this->apiUrl = config('services.myfatoorah.api_url');

        // Log warning if configuration is missing
        if (!$this->apiKey || !$this->apiUrl) {
            Log::warning('MyFatoorah configuration is incomplete', [
                'has_api_key' => !empty($this->apiKey),
                'has_api_url' => !empty($this->apiUrl),
            ]);
        }
    }

    /**
     * Initiate payment session
     */
    public function initiatePayment(array $data)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/v2/InitiatePayment', [
                'InvoiceAmount' => $data['amount'],
                'CurrencyIso' => $data['currency'] ?? 'SAR',
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('MyFatoorah InitiatePayment failed', [
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to initiate payment',
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah InitiatePayment exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute payment
     */
    public function executePayment(array $data)
    {
        try {
            // Sanitize and validate customer data
            $customerMobile = $this->sanitizePhoneNumber($data['customer_mobile']);
            $customerEmail = $this->sanitizeEmail($data['customer_email'] ?? '');

            Log::info('Sanitized customer data', [
                'original_mobile' => $data['customer_mobile'],
                'sanitized_mobile' => $customerMobile,
                'original_email' => $data['customer_email'] ?? '',
                'sanitized_email' => $customerEmail,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/v2/ExecutePayment', [
                'PaymentMethodId' => $data['payment_method_id'],
                'InvoiceValue' => $data['amount'],
                'CustomerName' => $data['customer_name'],
                'CustomerMobile' => $customerMobile,
                'CustomerEmail' => $customerEmail,
                'Language' => $data['language'] ?? 'ar',
                'MobileCountryCode' => '+966',
                'DisplayCurrencyIso' => 'SAR',
                'CustomerReference' => $data['booking_id'],
                'UserDefinedField' => json_encode([
                    'booking_id' => $data['booking_id'],
                    'client_id' => $data['client_id'],
                ]),
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('MyFatoorah ExecutePayment failed', [
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to execute payment',
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah ExecutePayment exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus($paymentId)
    {
        try {
            Log::info('Calling MyFatoorah GetPaymentStatus API', [
                'payment_id' => $paymentId,
                'api_url' => $this->apiUrl . '/v2/GetPaymentStatus',
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/v2/GetPaymentStatus', [
                'KeyType' => 'PaymentId',
                'Key' => $paymentId,
            ]);

            Log::info('MyFatoorah GetPaymentStatus raw response', [
                'status_code' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->json(),
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('MyFatoorah GetPaymentStatus failed', [
                'status_code' => $response->status(),
                'response' => $response->json(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get payment status',
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah GetPaymentStatus exception', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Process refund
     */
    public function refundPayment($paymentId, $amount, $comment = '')
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/v2/MakeRefund', [
                'KeyType' => 'PaymentId',
                'Key' => $paymentId,
                'RefundChargeOnCustomer' => false,
                'ServiceChargeOnCustomer' => false,
                'Amount' => $amount,
                'Comment' => $comment,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to process refund',
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah Refund exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get available payment methods
     */
    public function getPaymentMethods($amount)
    {
        try {
            Log::info('MyFatoorah getPaymentMethods called', [
                'amount' => $amount,
                'api_url' => $this->apiUrl,
                'api_key_length' => strlen($this->apiKey)
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/v2/InitiatePayment', [
                'InvoiceAmount' => $amount,
                'CurrencyIso' => 'SAR',
            ]);

            Log::info('MyFatoorah API Response', [
                'status' => $response->status(),
                'body' => $response->json(),
                'headers' => $response->headers(),
                'error_message' => $response->json()['Message'] ?? null
            ]);

            if ($response->successful()) {
                $data = $response->json();

                // Return all available payment methods
                $paymentMethods = collect($data['Data']['PaymentMethods'] ?? [])
                    ->values()
                    ->all();

                Log::info('Filtered payment methods:', ['count' => count($paymentMethods)]);

                return [
                    'success' => true,
                    'data' => $paymentMethods
                ];
            }

            Log::error('MyFatoorah API failed', [
                'status' => $response->status(),
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to get payment methods',
                'error' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah GetPaymentMethods exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Sanitize phone number to meet MyFatoorah requirements
     * Max 11 characters, numbers only
     */
    private function sanitizePhoneNumber(?string $phone): string
    {
        if (empty($phone)) {
            return '0000000000'; // Default fallback
        }

        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove country code if present (e.g., +966 or 966)
        if (str_starts_with($phone, '966')) {
            $phone = substr($phone, 3);
        }

        // Ensure it starts with 0 for Saudi numbers
        if (!str_starts_with($phone, '0')) {
            $phone = '0' . $phone;
        }

        // Truncate to 11 characters max
        return substr($phone, 0, 11);
    }

    /**
     * Sanitize email to meet MyFatoorah requirements
     * Must be a valid email format
     */
    private function sanitizeEmail(?string $email): string
    {
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'customer@luky.app'; // Default fallback email
        }

        return $email;
    }

    /**
     * Send Payment (Direct Payment)
     * For direct payment without redirect
     */
    public function sendPayment(array $data)
    {
        try {
            $payload = [
                'PaymentMethodId' => $data['payment_method_id'],
                'InvoiceValue' => $data['amount'],
                'CustomerName' => $data['customer_name'],
                'DisplayCurrencyIso' => $data['currency'] ?? 'SAR',
                'MobileCountryCode' => $data['mobile_country_code'] ?? '+966',
                'CustomerMobile' => $this->sanitizePhoneNumber($data['customer_mobile']),
                'CustomerEmail' => $this->sanitizeEmail($data['customer_email'] ?? null),
                'Language' => $data['language'] ?? 'ar',
                'CustomerReference' => $data['reference'] ?? null,
            ];

            // Add invoice items if provided
            if (isset($data['invoice_items'])) {
                $payload['InvoiceItems'] = $data['invoice_items'];
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/v2/SendPayment', $payload);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            Log::error('MyFatoorah SendPayment failed', [
                'response' => $response->json()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to send payment',
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah SendPayment exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get invoice by ID
     */
    public function getInvoice($invoiceId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/v2/GetPaymentStatus', [
                'KeyType' => 'InvoiceId',
                'Key' => $invoiceId,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get invoice',
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah GetInvoice exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel payment/invoice
     */
    public function cancelPayment($paymentId, $comment = '')
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/v2/CancelToken', [
                'KeyType' => 'PaymentId',
                'Key' => $paymentId,
                'Comment' => $comment,
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to cancel payment',
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah CancelPayment exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get account balance
     */
    public function getAccountBalance()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->get($this->apiUrl . '/v2/GetAccountBalance');

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json()
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get account balance',
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            Log::error('MyFatoorah GetAccountBalance exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to MyFatoorah API
     */
    public function testConnection()
    {
        try {
            // Try to initiate a small payment to test connection
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/v2/InitiatePayment', [
                'InvoiceAmount' => 1,
                'CurrencyIso' => 'SAR',
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'data' => [
                        'api_url' => $this->apiUrl,
                        'connected' => true,
                        'response_time' => $response->transferStats?->getTransferTime() ?? 0
                    ]
                ];
            }

            return [
                'success' => false,
                'message' => 'Connection failed: ' . ($response->json()['Message'] ?? 'Unknown error'),
                'data' => $response->json()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }
}
