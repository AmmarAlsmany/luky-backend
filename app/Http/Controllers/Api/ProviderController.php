<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ServiceProvider;
use App\Models\ProviderDocument;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\ServiceProviderResource;
use App\Http\Resources\ServiceResource;

class ProviderController extends Controller
{
    /**
     * Register as provider (after user registration)
     * This creates the provider profile for an existing user
     */
    public function registerProvider(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Check if admin is creating for someone else
        $isAdminCreating = $currentUser->hasRole('admin') && $request->has('phone');

        if ($isAdminCreating) {
            // Admin creating a new provider
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'required|string|unique:users,phone',
                'business_name' => 'required|string|max:255',
                'business_type' => 'required|in:salon,clinic,makeup_artist,hair_stylist,individual,company,establishment',
                'description' => 'nullable|string|max:1000',
                'city_id' => 'required|exists:cities,id',
                'address' => 'nullable|string|max:500',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'working_hours' => 'nullable|array',
                'off_days' => 'nullable|array',
            ]);

            DB::beginTransaction();
            try {
                // Create new user
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'],
                    'user_type' => 'provider',
                    'status' => 'pending',
                    'city_id' => $validated['city_id'],
                ]);

                $user->assignRole('provider');

                // Create provider profile
                $provider = ServiceProvider::create([
                    'user_id' => $user->id,
                    'business_name' => $validated['business_name'],
                    'business_type' => $validated['business_type'],
                    'description' => $validated['description'] ?? '',
                    'address' => $validated['address'] ?? '',
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'working_hours' => $validated['working_hours'] ?? [],
                    'off_days' => $validated['off_days'] ?? [],
                    'verification_status' => 'pending',
                    'commission_rate' => 15.00,
                    'is_active' => false,
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Provider created successfully by admin.',
                    'data' => [
                        'provider' => new ServiceProviderResource($provider->load(['user', 'city'])),
                    ]
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        // Original flow: existing user upgrading to provider
        $user = $currentUser;

        // Check if user is already a provider
        if ($user->user_type === 'provider' && $user->providerProfile) {
            throw ValidationException::withMessages([
                'message' => ['You are already registered as a provider.']
            ]);
        }

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|in:salon,clinic,makeup_artist,hair_stylist',
            'description' => 'required|string|max:1000',
            'city_id' => 'required|exists:cities,id',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'working_hours' => 'required|array',
            'off_days' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // Update user type to provider
            $user->update(['user_type' => 'provider']);
            $user->assignRole('provider');

            // Create provider profile
            $provider = ServiceProvider::create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'business_type' => $validated['business_type'],
                'description' => $validated['description'],
                'city_id' => $validated['city_id'],
                'address' => $validated['address'],
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'working_hours' => $validated['working_hours'],
                'off_days' => $validated['off_days'] ?? [],
                'verification_status' => 'pending',
                'commission_rate' => 15.00, // Default 15%
                'is_active' => false, // Inactive until approved
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Provider registration submitted successfully. Please upload required documents.',
                'data' => [
                    'provider' => new ServiceProviderResource($provider->load(['user', 'city'])),
                    'next_step' => 'upload_documents'
                ]
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Upload provider documents
     */
    public function uploadDocuments(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found. Please register as provider first.']
            ]);
        }

        $validated = $request->validate([
            'document_type' => 'required|in:freelance_license,commercial_register,municipal_license,national_id,agreement_contract',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
        ]);

        // Store the document
        $file = $request->file('document');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('provider_documents/' . $provider->id, $fileName, 'public');

        // Create document record
        $document = ProviderDocument::create([
            'provider_id' => $provider->id,
            'document_type' => $validated['document_type'],
            'file_path' => $filePath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'verification_status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => [
                'document_id' => $document->id,
                'document_type' => $document->document_type,
                'file_name' => $document->original_name,
                'verification_status' => $document->verification_status
            ]
        ]);
    }

    /**
     * Get provider profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile()->with(['city', 'services', 'documents'])->first();

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider profile not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new ServiceProviderResource($provider)
        ]);
    }

    /**
     * Update provider profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $validated = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string|max:1000',
            'address' => 'sometimes|string|max:500',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'working_hours' => 'sometimes|array',
            'off_days' => 'sometimes|array',
        ]);

        $provider->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Provider profile updated successfully',
            'data' => new ServiceProviderResource($provider->fresh())
        ]);
    }

    /**
     * Upload provider gallery images
     */
    public function uploadGallery(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $validated = $request->validate([
            'images' => 'required|array|max:10',
            'images.*' => 'required|image|mimes:jpg,jpeg,png|max:2048' // 2MB per image
        ]);

        $uploadedImages = [];

        foreach ($request->file('images') as $image) {
            $media = $provider->addMedia($image)
                ->toMediaCollection('gallery');

            $uploadedImages[] = [
                'id' => $media->id,
                'url' => $media->getUrl(),
                'name' => $media->name
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Images uploaded successfully',
            'data' => [
                'uploaded_count' => count($uploadedImages),
                'images' => $uploadedImages
            ]
        ]);
    }

    /**
     * Upload provider logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $validated = $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png|max:1024' // 1MB
        ]);

        // Delete existing logo
        $provider->clearMediaCollection('logo');

        // Upload new logo
        $media = $provider->addMedia($request->file('logo'))
            ->toMediaCollection('logo');

        return response()->json([
            'success' => true,
            'message' => 'Logo uploaded successfully',
            'data' => [
                'logo_url' => $media->getUrl()
            ]
        ]);
    }

    /**
     * Get provider services
     */
    public function getServices(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $services = $provider->services()
            ->with('category')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ServiceResource::collection($services),
            'total' => $services->count()
        ]);
    }

    /**
     * Create new service
     */
    public function createService(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        if ($provider->verification_status !== 'approved') {
            throw ValidationException::withMessages([
                'message' => ['Your provider account must be approved before adding services.']
            ]);
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:service_categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:15|max:480', // 15 min to 8 hours
            'available_at_home' => 'sometimes|boolean',
            'home_service_price' => 'required_if:available_at_home,true|nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validated['available_at_home'] && !$validated['home_service_price']) {
            throw ValidationException::withMessages([
                'home_service_price' => ['Home service price is required when home service is enabled.']
            ]);
        }

        $service = $provider->services()->create([
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'available_at_home' => $validated['available_at_home'] ?? false,
            'home_service_price' => $validated['home_service_price'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $provider->services()->count() + 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully',
            'data' => new ServiceResource($service->load('category'))
        ], 201);
    }

    /**
     * Update service
     */
    public function updateService(Request $request, int $serviceId): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $service = $provider->services()->findOrFail($serviceId);

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:service_categories,id',
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:15|max:480',
            'is_active' => 'sometimes|boolean',
        ]);

        $service->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully',
            'data' => new ServiceResource($service->fresh()->load('category'))
        ]);
    }

    /**
     * Delete service
     */
    public function deleteService(Request $request, int $serviceId): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $service = $provider->services()->findOrFail($serviceId);

        // Check if service has active bookings
        $hasActiveBookings = $service->bookingItems()
            ->whereHas('booking', function ($query) {
                $query->whereIn('status', ['pending', 'confirmed']);
            })
            ->exists();

        if ($hasActiveBookings) {
            throw ValidationException::withMessages([
                'message' => ['Cannot delete service with active bookings. Please deactivate it instead.']
            ]);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully'
        ]);
    }

    /**
     * Get provider analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $period = $request->get('period', 'month'); // day, week, month, year

        $startDate = match ($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $bookingsQuery = $provider->bookings()->where('created_at', '>=', $startDate);

        $analytics = [
            'total_bookings' => $bookingsQuery->count(),
            'completed_bookings' => (clone $bookingsQuery)->where('status', 'completed')->count(),
            'cancelled_bookings' => (clone $bookingsQuery)->where('status', 'cancelled')->count(),
            'total_revenue' => (clone $bookingsQuery)->where('status', 'completed')->sum('total_amount'),
            'commission_paid' => (clone $bookingsQuery)->where('status', 'completed')->sum('commission_amount'),
            'average_rating' => $provider->average_rating,
            'total_reviews' => $provider->total_reviews,
            'active_services' => $provider->services()->where('is_active', true)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
            'period' => $period
        ]);
    }
}
