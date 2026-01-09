<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\OtpVerification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\UserResource;
use App\Services\PhoneNumberService;
use App\Rules\SaudiPhoneNumber;
use App\Rules\ValidEmail;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    protected PhoneNumberService $phoneService;

    public function __construct(PhoneNumberService $phoneService)
    {
        $this->phoneService = $phoneService;
    }

    /**
     * Check if phone number exists and return user type
     * PUBLIC ENDPOINT - No authentication required
     */
    public function checkPhone(Request $request): JsonResponse
    {
        $phoneRule = new SaudiPhoneNumber();

        $request->validate([
            'phone' => [
                'required',
                'string',
                $phoneRule
            ]
        ]);

        // Normalize the phone number
        $normalizedPhone = $this->phoneService->normalize($request->phone);

        // Check if user exists
        $user = User::where('phone', $normalizedPhone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'exists' => false,
                'message' => 'Phone number not registered'
            ]);
        }

        return response()->json([
            'success' => true,
            'exists' => true,
            'user_type' => $user->user_type,
            'is_active' => $user->is_active
        ]);
    }

    /**
     * Register new user after OTP verification
     */
    public function register(Request $request): JsonResponse
    {
        $phoneRule = new SaudiPhoneNumber();

        // Normalize the phone number for validation
        $normalizedPhone = $this->phoneService->normalize($request->phone);

        // Check if there's a soft-deleted account with this phone
        $deletedUser = User::onlyTrashed()->where('phone', $normalizedPhone)->first();

        // If soft-deleted account exists, permanently delete it to free up the phone number
        if ($deletedUser) {
            // Permanently delete provider profile if exists
            if ($deletedUser->providerProfile) {
                $deletedUser->providerProfile()->forceDelete();
            }
            // Permanently delete user
            $deletedUser->forceDelete();
        }

        // Check if user already exists with this phone (from previous registration step)
        $existingUser = User::where('phone', $normalizedPhone)->first();

        // Verify OTP token is valid
        $otpVerification = OtpVerification::where('phone', $normalizedPhone)
            ->where('type', 'registration')
            ->where('is_verified', true)
            ->where('verified_at', '>=', now()->subMinutes(30))
            ->first();

        if (!$otpVerification && !$existingUser) {
            throw ValidationException::withMessages([
                'otp_token' => ['Invalid or expired OTP verification.']
            ]);
        }

        // Build validation rules
        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', new ValidEmail()],
            'phone' => ['required', 'string', $phoneRule],
            'user_type' => 'required|in:client,provider',
            'city_id' => 'required|exists:cities,id',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female',
            'otp_token' => 'required|integer',
            'address' => 'sometimes|string|max:500',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180'
        ];

        // Only add unique constraints if this is a new user or email changed
        if (!$existingUser) {
            $validationRules['phone'][] = 'unique:users,phone';
            $validationRules['email'][] = 'unique:users,email';
        } else {
            // If user exists, only check uniqueness if email is being changed
            if ($request->email && $request->email !== $existingUser->email) {
                $validationRules['email'][] = 'unique:users,email';
            }
        }

        $request->validate($validationRules);

        // If user exists (editing during registration), update them
        if ($existingUser) {
            $existingUser->update([
                'name' => $request->name,
                'email' => $request->email,
                'city_id' => $request->city_id,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);

            $user = $existingUser;
            $token = $user->createToken('mobile-app', ['*'], now()->addDays(30))->plainTextToken;
        } else {
            // Create new user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $normalizedPhone,
                'user_type' => $request->user_type,
                'city_id' => $request->city_id,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'phone_verified_at' => now(),
                'is_active' => true
            ]);

            // Assign role based on user type
            $user->assignRole($request->user_type);

            // Create API token
            $token = $user->createToken('mobile-app', ['*'], now()->addDays(30))->plainTextToken;

            // Mark OTP as used
            if ($otpVerification) {
                $otpVerification->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'User registered successfully',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 30 * 24 * 60 * 60 // 30 days in seconds
            ]
        ], 201);
    }

    /**
     * Get user profile
     */
    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();

        // Eager load provider profile if user is a provider
        if ($user->user_type === 'provider') {
            $user->load('providerProfile');
        }

        return response()->json([
            'success' => true,
            'data' => new UserResource($user)
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'unique:users,email,' . $user->id, new ValidEmail()],
            'city_id' => 'sometimes|exists:cities,id',
            'date_of_birth' => 'sometimes|date|before:today',
            'gender' => 'sometimes|in:male,female',
            'address' => 'sometimes|string|max:500',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180'
        ]);

        $user->update($request->only([
            'name',
            'email',
            'city_id',
            'date_of_birth',
            'gender',
            'address',
            'latitude',
            'longitude'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => new UserResource($user->fresh())
        ]);
    }

    /**
     * Upload or update user avatar with automatic optimization
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
        ]);

        try {
            $user = $request->user();

            // Store and optimize avatar using Spatie Media Library
            // This automatically creates optimized (300x300 @ 85%) and thumb (100x100 @ 80%) versions
            $user->addMediaFromRequest('avatar')
                ->toMediaCollection('avatar');

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'avatar_url' => $user->fresh()->avatar_url
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload or update user profile image
     * Alternative endpoint for mobile apps using 'profile_image' field name
     */
    public function uploadProfileImage(Request $request): JsonResponse
    {
        $request->validate([
            'profile_image' => 'required|image|mimes:jpeg,png,jpg|max:5120', // 5MB max
        ]);

        try {
            $user = $request->user();

            // Delete old avatar if exists
            $user->clearMediaCollection('avatar');

            // Store and optimize profile image using Spatie Media Library
            // This automatically creates optimized (300x300 @ 85%) and thumb (100x100 @ 80%) versions
            $user->addMediaFromRequest('profile_image')
                ->toMediaCollection('avatar');

            return response()->json([
                'success' => true,
                'message' => 'Profile image updated successfully',
                'data' => new UserResource($user->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete user avatar
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Delete avatar from media library
            $user->clearMediaCollection('avatar');

            return response()->json([
                'success' => true,
                'message' => 'Avatar deleted successfully',
                'data' => [
                    'avatar_url' => $user->fresh()->avatar_url // Returns default avatar
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if provider has active bookings
        if ($user->user_type === 'provider' && $user->providerProfile) {
            $activeBookings = $user->providerProfile->bookings()
                ->whereIn('status', ['pending', 'confirmed'])
                ->count();

            if ($activeBookings > 0) {
                throw ValidationException::withMessages([
                    'message' => ['Cannot delete account with active bookings. Please complete or cancel all bookings first.']
                ]);
            }
        }

        // Check if client has active bookings
        if ($user->user_type === 'client') {
            $activeBookings = $user->clientBookings()
                ->whereIn('status', ['pending', 'confirmed'])
                ->count();

            if ($activeBookings > 0) {
                throw ValidationException::withMessages([
                    'message' => ['Cannot delete account with active bookings. Please complete or cancel all bookings first.']
                ]);
            }
        }

        DB::beginTransaction();
        try {
            // For providers, soft delete provider profile first
            if ($user->providerProfile) {
                $user->providerProfile->delete();
            }

            // Revoke all tokens
            $user->tokens()->delete();

            // Soft delete user
            $user->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
