<?php
// app/Http/Controllers/Api/Auth/OtpController.php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OtpVerification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Services\SmsService;
use App\Http\Resources\UserResource;
use App\Services\PhoneNumberService;
use App\Rules\SaudiPhoneNumber;

class OtpController extends Controller
{
    protected SmsService $smsService;
    protected PhoneNumberService $phoneService;

    public function __construct(SmsService $smsService, PhoneNumberService $phoneService)
    {
        $this->smsService = $smsService;
        $this->phoneService = $phoneService;
    }

    /**
     * Send OTP to phone number
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => [
                'required',
                'string',
                new SaudiPhoneNumber()
            ],
            'type' => 'required|in:registration,login'
        ]);

        $phone = $this->phoneService->normalize($request->phone);
        $type = $request->type;

        // Get app type from header (provider or client)
        $appType = $request->header('X-App-Type');

        // Check if user exists for login type
        if ($type === 'login') {
            $user = User::where('phone', $phone)->first();
            if (!$user) {
                throw ValidationException::withMessages([
                    'phone' => ['No account found with this phone number.']
                ]);
            }

            // CRITICAL: Validate app type matches user type
            if ($appType && $user->user_type !== $appType) {
                $correctApp = $user->user_type === 'provider' ? 'Provider' : 'Client';
                $currentApp = $appType === 'provider' ? 'Provider' : 'Client';

                throw ValidationException::withMessages([
                    'phone' => ["This account is registered for {$correctApp}s. Please use the Luky {$correctApp} app instead of the {$currentApp} app."]
                ]);
            }

            // Check both is_active flag and status field
            if (!$user->is_active || $user->status !== 'active') {
                $statusMessage = !$user->is_active
                    ? 'Your account has been deactivated. Please contact support.'
                    : 'Your account has been ' . $user->status . '. Please contact support.';

                throw ValidationException::withMessages([
                    'phone' => [$statusMessage]
                ]);
            }
        }

        // Check if user already exists for registration
        if ($type === 'registration') {
            $user = User::where('phone', $phone)->first();
            if ($user) {
                throw ValidationException::withMessages([
                    'phone' => ['An account already exists with this phone number.']
                ]);
            }
        }

        // Rate limiting - max 3 attempts per 15 minutes
        $recentAttempts = OtpVerification::where('phone', $phone)
            ->where('created_at', '>=', now()->subMinutes(15))
            ->count();

        if ($recentAttempts >= 3) {
            throw ValidationException::withMessages([
                'phone' => ['Too many OTP requests. Please try again in 15 minutes.']
            ]);
        }

        // Delete previous unverified OTPs for this phone
        OtpVerification::where('phone', $phone)
            ->where('type', $type)
            ->where('is_verified', false)
            ->delete();

        // Generate OTP - 6 digits
        $otpCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP
        $otpVerification = OtpVerification::create([
            'phone' => $phone,
            'otp_code' => $otpCode,
            'type' => $type,
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0
        ]);

        // Send SMS
        $message = "Your Luky verification code is: {$otpCode}. Valid for 10 minutes. Do not share this code.";
        
        try {
            $this->smsService->send($phone, $message);
        } catch (\Exception $e) {
            // If SMS fails, delete the OTP record
            $otpVerification->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send SMS. Please try again.',
                'error' => 'SMS_SEND_FAILED'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'data' => [
                'phone' => $phone,
                'expires_in' => 600, // 10 minutes in seconds
                'can_resend_after' => 60 // 1 minute
            ]
        ]);
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => [
                'required',
                'string',
                new SaudiPhoneNumber()
            ],
            'otp_code' => 'required|string|size:6',
            'type' => 'required|in:registration,login'
        ]);

        $phone = $this->phoneService->normalize($request->phone);
        $otpCode = $request->otp_code;
        $type = $request->type;

        // Get app type from header (provider or client)
        $appType = $request->header('X-App-Type');

        // Find OTP verification record
        $otpVerification = OtpVerification::where('phone', $phone)
            ->where('otp_code', $otpCode)
            ->where('type', $type)
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpVerification) {
            // Increment attempts for existing records
            OtpVerification::where('phone', $phone)
                ->where('type', $type)
                ->where('is_verified', false)
                ->increment('attempts');

            throw ValidationException::withMessages([
                'otp_code' => ['Invalid or expired OTP code.']
            ]);
        }

        // Check attempt limit
        if ($otpVerification->attempts >= 3) {
            $otpVerification->update(['is_verified' => true]); // Mark as used
            
            throw ValidationException::withMessages([
                'otp_code' => ['Too many failed attempts. Please request a new OTP.']
            ]);
        }

        // Mark as verified
        $otpVerification->update([
            'is_verified' => true,
            'verified_at' => now()
        ]);

        $response = [
            'success' => true,
            'message' => 'OTP verified successfully',
            'data' => [
                'verified' => true,
                'type' => $type
            ]
        ];

        // For login, return user and token immediately
        if ($type === 'login') {
            $user = User::where('phone', $phone)->first();

            // CRITICAL: Double-check app type matches user type (second layer of validation)
            if ($appType && $user->user_type !== $appType) {
                $correctApp = $user->user_type === 'provider' ? 'Provider' : 'Client';
                $currentApp = $appType === 'provider' ? 'Provider' : 'Client';

                throw ValidationException::withMessages([
                    'phone' => ["This account is registered for {$correctApp}s. Please use the Luky {$correctApp} app instead of the {$currentApp} app."]
                ]);
            }

            // Double-check user is still active (in case status changed during OTP flow)
            if (!$user->is_active || $user->status !== 'active') {
                $statusMessage = !$user->is_active
                    ? 'Your account has been deactivated. Please contact support.'
                    : 'Your account has been ' . $user->status . '. Please contact support.';

                throw ValidationException::withMessages([
                    'phone' => [$statusMessage]
                ]);
            }

            $user->update(['last_login_at' => now()]);

            // Create API token
            $token = $user->createToken('mobile-app', ['*'], now()->addDays(30))->plainTextToken;

            $response['data']['user'] = new UserResource($user);
            $response['data']['token'] = $token;
            $response['data']['token_type'] = 'Bearer';
            $response['data']['expires_in'] = 30 * 24 * 60 * 60; // 30 days in seconds
        }

        // For registration, just confirm verification
        if ($type === 'registration') {
            $response['data']['otp_token'] = $otpVerification->id; // Use for registration
        }

        return response()->json($response);
    }

    /**
     * Resend OTP (with rate limiting)
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => [
                'required',
                'string',
                new SaudiPhoneNumber()
            ],
            'type' => 'required|in:registration,login'
        ]);

        $phone = $this->phoneService->normalize($request->phone);

        // Check if user can resend (1 minute cooldown)
        $lastOtp = OtpVerification::where('phone', $phone)
            ->where('type', $request->type)
            ->latest()
            ->first();

        if ($lastOtp && $lastOtp->created_at->diffInSeconds(now()) < 60) {
            $remainingTime = 60 - $lastOtp->created_at->diffInSeconds(now());
            
            throw ValidationException::withMessages([
                'phone' => ["Please wait {$remainingTime} seconds before requesting a new OTP."]
            ]);
        }

        // Use the same logic as sendOtp
        return $this->sendOtp($request);
    }
}