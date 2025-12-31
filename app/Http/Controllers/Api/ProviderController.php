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
use Illuminate\Validation\Rule;
use App\Http\Resources\ServiceProviderResource;
use App\Http\Resources\ServiceResource;
use App\Models\ProviderPendingChange;
use App\Services\NotificationService;
use App\Rules\ValidEmail;

class ProviderController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
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
                'email' => ['required', 'email', 'unique:users,email', new ValidEmail()],
                'phone' => 'required|string|unique:users,phone',
                'business_name' => 'required|string|max:255',
                'provider_category_id' => 'required|exists:provider_categories,id',
                'description' => 'nullable|string|max:1000',
                'city_id' => 'required|exists:cities,id',
                'address' => 'required|string|max:500',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'working_hours' => 'nullable|array',
                'off_days' => 'nullable|array',
                'license_number' => 'nullable|string|max:255',
                'commercial_register' => 'nullable|string|max:255',
                'municipal_license' => 'nullable|string|max:255',
            ]);

            DB::beginTransaction();
            try {
                // Get provider category to set business_type for backward compatibility
                $providerCategory = \App\Models\ProviderCategory::findOrFail($validated['provider_category_id']);
                $businessType = strtolower(str_replace(' ', '_', $providerCategory->name_en));

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
                    'business_type' => $businessType,
                    'provider_category_id' => $validated['provider_category_id'],
                    'description' => $validated['description'] ?? '',
                    'city_id' => $validated['city_id'],
                    'address' => $validated['address'] ?? '',
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'working_hours' => $validated['working_hours'] ?? [],
                    'off_days' => $validated['off_days'] ?? [],
                    'license_number' => $validated['license_number'] ?? null,
                    'commercial_register' => $validated['commercial_register'] ?? null,
                    'municipal_license' => $validated['municipal_license'] ?? null,
                    'verification_status' => 'pending',
                    'commission_rate' => 15.00,
                    'is_active' => false,
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Provider created successfully by admin.',
                    'data' => [
                        'provider' => new ServiceProviderResource($provider->load(['user', 'city', 'providerCategory'])),
                    ]
                ], 201);
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        // Original flow: existing user upgrading to provider
        $user = $currentUser;

        // Check if user already has a provider profile
        if ($user->providerProfile) {
            // User already has a provider profile
            // Check if it's complete or incomplete
            $provider = $user->providerProfile;

            // If provider profile exists and has all required data, they're already registered
            if ($provider->business_name && $provider->address && $provider->city_id) {
                return response()->json([
                    'success' => true,
                    'message' => 'You are already registered as a provider.',
                    'data' => [
                        'provider' => new ServiceProviderResource($provider->load(['user', 'city', 'providerCategory'])),
                        'next_step' => $provider->verification_status === 'pending' ? 'upload_documents' : 'complete'
                    ]
                ], 200);
            }

            // If provider profile exists but incomplete, we'll update it below
        }

        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'provider_category_id' => 'required|exists:provider_categories,id',
            'description' => 'nullable|string|max:1000',
            'city_id' => 'required|exists:cities,id',
            'address' => 'required|string|max:500', // Required - clients need to find provider
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'working_hours' => 'required|array', // Required - needed for booking availability
            'working_hours.*.day' => 'required|string',
            'working_hours.*.open' => 'required|date_format:H:i',
            'working_hours.*.close' => 'required|date_format:H:i',
            'off_days' => 'nullable|array',
            'license_number' => 'nullable|string|max:255',
            'commercial_register' => 'nullable|string|max:255',
            'municipal_license' => 'nullable|string|max:255',
            'acknowledgment_accepted' => 'required|boolean|accepted',
            'undertaking_accepted' => 'required|boolean|accepted',
        ]);

        DB::beginTransaction();
        try {
            // Get provider category to set business_type for backward compatibility
            $providerCategory = \App\Models\ProviderCategory::findOrFail($validated['provider_category_id']);
            $businessType = strtolower(str_replace(' ', '_', $providerCategory->name_en));

            // Update user type to provider if not already
            if ($user->user_type !== 'provider') {
                $user->update(['user_type' => 'provider']);
            }

            // Assign provider role if not already assigned
            if (!$user->hasRole('provider')) {
                $user->assignRole('provider');
            }

            // Check if provider profile exists (incomplete registration)
            if ($user->providerProfile) {
                // Update existing incomplete provider profile
                $provider = $user->providerProfile;
                $provider->update([
                    'business_name' => $validated['business_name'],
                    'business_type' => $businessType,
                    'provider_category_id' => $validated['provider_category_id'],
                    'description' => $validated['description'] ?? '',
                    'city_id' => $validated['city_id'],
                    'address' => $validated['address'],
                    'latitude' => $validated['latitude'] ?? null,
                    'longitude' => $validated['longitude'] ?? null,
                    'working_hours' => $validated['working_hours'],
                    'off_days' => $validated['off_days'] ?? [],
                    'license_number' => $validated['license_number'] ?? null,
                    'commercial_register' => $validated['commercial_register'] ?? null,
                    'municipal_license' => $validated['municipal_license'] ?? null,
                    'acknowledgment_accepted' => $validated['acknowledgment_accepted'],
                    'undertaking_accepted' => $validated['undertaking_accepted'],
                    'agreements_accepted_at' => now(),
                ]);

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Provider registration updated successfully. Please upload required documents.',
                    'data' => [
                        'provider' => new ServiceProviderResource($provider->load(['user', 'city', 'providerCategory'])),
                        'next_step' => 'upload_documents'
                    ]
                ], 200);
            }

            // Create new provider profile
            $provider = ServiceProvider::create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'business_type' => $businessType,
                'provider_category_id' => $validated['provider_category_id'],
                'description' => $validated['description'] ?? '',
                'city_id' => $validated['city_id'],
                'address' => $validated['address'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'working_hours' => $validated['working_hours'],
                'off_days' => $validated['off_days'] ?? [],
                'license_number' => $validated['license_number'] ?? null,
                'commercial_register' => $validated['commercial_register'] ?? null,
                'municipal_license' => $validated['municipal_license'] ?? null,
                'acknowledgment_accepted' => $validated['acknowledgment_accepted'],
                'undertaking_accepted' => $validated['undertaking_accepted'],
                'agreements_accepted_at' => now(),
                'verification_status' => 'pending',
                'commission_rate' => 15.00, // Default 15%
                'is_active' => false, // Inactive until approved
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Provider registration submitted successfully. Please upload required documents.',
                'data' => [
                    'provider' => new ServiceProviderResource($provider->load(['user', 'city', 'providerCategory'])),
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

        \Log::info('=== DOCUMENT UPLOAD REQUEST ===', [
            'user_id' => $user->id,
            'provider_id' => $provider->id ?? 'N/A',
            'has_file' => $request->hasFile('document'),
            'document_type' => $request->input('document_type'),
        ]);

        if (!$provider) {
            \Log::error('Provider profile not found for user: ' . $user->id);
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found. Please register as provider first.']
            ]);
        }

        $validated = $request->validate([
            'document_type' => 'required|string|max:100', // Allow any document type for multiple files
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max
        ]);

        // Store the document
        $file = $request->file('document');
        $fileName = time() . '_' . $file->getClientOriginalName();
        $filePath = $file->storeAs('provider_documents/' . $provider->id, $fileName, 'public');

        \Log::info('File stored successfully', [
            'file_name' => $fileName,
            'file_path' => $filePath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

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

        \Log::info('Document record created', [
            'document_id' => $document->id,
            'document_type' => $document->document_type,
            'provider_id' => $provider->id,
            'total_documents' => ProviderDocument::where('provider_id', $provider->id)->count(),
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
        $provider = $user->providerProfile()->with(['city', 'services', 'documents', 'providerCategory'])->first();

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
     * Get pending profile changes for this provider
     */
    public function getPendingChanges(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            return response()->json([
                'success' => false,
                'message' => 'Provider profile not found'
            ], 404);
        }

        $pendingChanges = ProviderPendingChange::where('provider_id', $provider->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($change) {
                return [
                    'id' => $change->id,
                    'changed_fields' => $change->changed_fields,
                    'old_values' => $change->old_values,
                    'status' => $change->status,
                    'rejection_reason' => $change->rejection_reason,
                    'admin_notes' => $change->admin_notes,
                    'created_at' => $change->created_at,
                    'reviewed_at' => $change->reviewed_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $pendingChanges,
            'has_pending' => $pendingChanges->where('status', 'pending')->isNotEmpty(),
        ]);
    }

    /**
     * Update provider profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        // Log incoming request data
        \Log::info('=== UPDATE PROVIDER PROFILE REQUEST ===', [
            'user_id' => $user->id,
            'provider_id' => $provider->id ?? 'N/A',
            'request_data' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $validated = $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:provider_categories,id',
            'description' => 'sometimes|string|max:1000',
            'city_id' => 'sometimes|exists:cities,id',
            'address' => 'sometimes|string|max:500',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'working_hours' => 'sometimes|array',
            'off_days' => 'sometimes|array',
            'license_number' => 'sometimes|nullable|string|max:255',
            'commercial_register' => 'sometimes|nullable|string|max:255',
            'municipal_license' => 'sometimes|nullable|string|max:255',
        ]);

        // If category is being updated, rename to provider_category_id
        if (isset($validated['category_id'])) {
            $validated['provider_category_id'] = $validated['category_id'];
            unset($validated['category_id']);
        }

        \Log::info('=== VALIDATED DATA ===', [
            'validated' => $validated,
            'provider_before' => $provider->toArray(),
        ]);

        // Apply changes directly without requiring approval
        $provider->update($validated);

        \Log::info('=== PROFILE UPDATED SUCCESSFULLY ===', [
            'provider_id' => $provider->id,
            'updated_fields' => $validated,
            'provider_after' => $provider->fresh()->toArray(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'requires_approval' => false,
            'data' => [
                'provider' => new ServiceProviderResource($provider->fresh()),
            ]
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
            'logo' => 'required|image|mimes:jpg,jpeg,png|max:2048' // 2MB
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
     * Upload provider building image
     */
    public function uploadBuildingImage(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $validated = $request->validate([
            'building_image' => 'required|image|mimes:jpg,jpeg,png|max:5120' // 5MB
        ]);

        // Delete existing building image
        $provider->clearMediaCollection('building_image');

        // Upload new building image
        $media = $provider->addMedia($request->file('building_image'))
            ->toMediaCollection('building_image');

        return response()->json([
            'success' => true,
            'message' => 'Building image uploaded successfully',
            'data' => [
                'building_image_url' => $media->getUrl()
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
            'category_id' => 'nullable|exists:service_categories,id',
            'provider_service_category_id' => [
                'required',
                Rule::exists('provider_service_categories', 'id')->where(function ($query) use ($provider) {
                    $query->where('provider_id', $provider->id);
                })
            ],
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:15|max:480', // 15 min to 8 hours
            'available_at_home' => 'sometimes|boolean',
            'home_service_price' => 'required_if:available_at_home,true|nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:5120', // 5MB max per image
        ]);

        if ($validated['available_at_home'] && !$validated['home_service_price']) {
            throw ValidationException::withMessages([
                'home_service_price' => ['Home service price is required when home service is enabled.']
            ]);
        }

        $service = $provider->services()->create([
            'category_id' => $validated['category_id'] ?? null,
            'provider_service_category_id' => $validated['provider_service_category_id'],
            'name' => $validated['name'],
            'name_en' => $validated['name_en'] ?? null,
            'name_ar' => $validated['name_ar'] ?? null,
            'description' => $validated['description'] ?? null,
            'description_en' => $validated['description_en'] ?? null,
            'description_ar' => $validated['description_ar'] ?? null,
            'price' => $validated['price'],
            'available_at_home' => $validated['available_at_home'] ?? false,
            'home_service_price' => $validated['home_service_price'] ?? null,
            'duration_minutes' => $validated['duration_minutes'],
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $provider->services()->count() + 1,
        ]);

        // Handle image uploads
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $service->addMedia($image)
                    ->toMediaCollection('service_images');
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully',
            'data' => new ServiceResource($service->fresh()->load('category'))
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
            'provider_service_category_id' => [
                'sometimes',
                Rule::exists('provider_service_categories', 'id')->where(function ($query) use ($provider) {
                    $query->where('provider_id', $provider->id);
                })
            ],
            'name' => 'sometimes|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'name_ar' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'price' => 'sometimes|numeric|min:0',
            'duration_minutes' => 'sometimes|integer|min:15|max:480',
            'available_at_home' => 'sometimes|boolean',
            'home_service_price' => 'nullable|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'images' => 'nullable|array|max:3',
            'images.*' => 'image|mimes:jpeg,png,jpg|max:5120', // 5MB max per image
        ]);

        $service->update($validated);

        // Handle image uploads
        if ($request->hasFile('images')) {
            // Clear existing images and add new ones
            $service->clearMediaCollection('service_images');
            foreach ($request->file('images') as $image) {
                $service->addMedia($image)
                    ->toMediaCollection('service_images');
            }
        }

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

        // Support custom date ranges or period-based filtering
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = \Carbon\Carbon::parse($request->get('start_date'))->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->get('end_date'))->endOfDay();
            $period = 'custom';
        } else {
            $period = $request->get('period', 'month'); // day, week, month, year
            $startDate = match ($period) {
                'day' => now()->startOfDay(),
                'week' => now()->startOfWeek(),
                'month' => now()->startOfMonth(),
                'year' => now()->startOfYear(),
                default => now()->startOfMonth(),
            };
            $endDate = now()->endOfDay();
        }

        // Optimize: Use single query with aggregates instead of multiple queries
        $bookingStats = $provider->bookings()
            ->selectRaw("
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as total_revenue,
                SUM(CASE WHEN status = 'completed' THEN commission_amount ELSE 0 END) as commission_paid
            ")
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->first();

        // Get counts in parallel using cache where possible
        $unreadNotifications = \App\Models\Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        $activeServices = \Cache::remember(
            "provider:{$provider->id}:active_services_count",
            300, // 5 minutes
            fn() => $provider->services()->where('is_active', true)->count()
        );

        $analytics = [
            'total_bookings' => (int) $bookingStats->total_bookings,
            'pending_bookings' => (int) $bookingStats->pending_bookings,
            'confirmed_bookings' => (int) $bookingStats->confirmed_bookings,
            'completed_bookings' => (int) $bookingStats->completed_bookings,
            'cancelled_bookings' => (int) $bookingStats->cancelled_bookings,
            'total_revenue' => (float) $bookingStats->total_revenue,
            'commission_paid' => (float) $bookingStats->commission_paid,
            'average_rating' => $provider->average_rating,
            'total_reviews' => $provider->total_reviews,
            'active_services' => $activeServices,
            'unread_notifications' => $unreadNotifications,
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
            'period' => $period
        ]);
    }

    /**
     * Get daily booking statistics for chart
     */
    public function getDailyBookingStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        // Get date range (default to current week)
        if ($request->has('start_date') && $request->has('end_date')) {
            $startDate = \Carbon\Carbon::parse($request->get('start_date'))->startOfDay();
            $endDate = \Carbon\Carbon::parse($request->get('end_date'))->endOfDay();
        } else {
            $startDate = now()->startOfWeek();
            $endDate = now()->endOfWeek();
        }

        // Get daily booking counts
        $dailyStats = $provider->bookings()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Generate all dates in range with counts
        $stats = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayName = $currentDate->format('l'); // Monday, Tuesday, etc.

            $stats[] = [
                'date' => $dateStr,
                'day' => $dayName,
                'count' => $dailyStats->get($dateStr)->count ?? 0,
            ];

            $currentDate->addDay();
        }

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Update provider bank information
     */
    public function updateBankInfo(Request $request)
    {
        try {
            // Get authenticated user
            $user = auth()->user();

            \Log::info('=== UPDATE BANK INFO REQUEST ===', [
                'user_id' => $user->id,
                'request_data' => $request->all(),
            ]);

            // Get provider profile
            $provider = $user->serviceProvider;
            if (!$provider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider profile not found.'
                ], 404);
            }

            // Validate request
            $validated = $request->validate([
                'account_title' => 'required|string|max:255',
                'account_number' => 'required|string|max:255',
                'iban' => 'required|string|max:255',
                'currency' => 'required|string|max:10',
            ]);

            \Log::info('=== VALIDATION PASSED ===', [
                'validated_data' => $validated,
            ]);

            // Update provider bank information
            $provider->update($validated);

            \Log::info('=== BANK INFO UPDATED ===', [
                'provider_id' => $provider->id,
                'bank_info' => $validated,
            ]);

            // Send notification to provider
            $this->notificationService->sendBankInfoUpdated(
                $user->id,
                $provider->business_name
            );

            return response()->json([
                'success' => true,
                'message' => 'Bank information updated successfully.',
                'data' => new ServiceProviderResource($provider->fresh())
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('=== VALIDATION ERROR ===', [
                'errors' => $e->errors(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('=== ERROR UPDATING BANK INFO ===', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating bank information.'
            ], 500);
        }
    }

    /**
     * Get clients who have completed bookings with this provider
     * Endpoint: GET /provider/clients
     */
    public function getClients(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        // Get unique clients who have completed or confirmed bookings with this provider
        // Excludes: pending, cancelled, rejected bookings
        $clients = \App\Models\User::whereHas('bookings', function ($query) use ($provider) {
            $query->where('provider_id', $provider->id)
                  ->whereIn('status', ['completed', 'confirmed']);
        })
        ->with(['bookings' => function ($query) use ($provider) {
            $query->where('provider_id', $provider->id)
                  ->whereIn('status', ['completed', 'confirmed'])
                  ->latest()
                  ->take(1);
        }])
        ->get()
        ->map(function ($client) {
            $lastBooking = $client->bookings->first();
            return [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'avatar' => $client->avatar_url,
                'total_bookings' => $client->bookings->count(),
                'last_booking_date' => $lastBooking ? $lastBooking->booking_date : null,
                'created_at' => $client->created_at->format('Y-m-d H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $clients,
        ]);
    }

    /**
     * Send notification to selected clients
     * Endpoint: POST /provider/send-notification
     */
    public function sendNotificationToClients(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $validated = $request->validate([
            'client_ids' => 'required|array|min:1',
            'client_ids.*' => 'exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
        ]);

        $successCount = 0;
        $failedCount = 0;

        foreach ($validated['client_ids'] as $clientId) {
            try {
                // Send notification using the NotificationService
                $this->notificationService->send(
                    $clientId,
                    'promotional',
                    $validated['title'],
                    $validated['message'],
                    [
                        'provider_id' => $provider->id,
                        'provider_name' => $provider->business_name,
                    ]
                );
                $successCount++;
            } catch (\Exception $e) {
                \Log::error('Failed to send notification to client ' . $clientId, [
                    'error' => $e->getMessage()
                ]);
                $failedCount++;
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Notification sent to $successCount client(s).",
            'data' => [
                'sent' => $successCount,
                'failed' => $failedCount,
            ]
        ]);
    }

    /**
     * Get provider's available balance for withdrawal
     */
    public function getAvailableBalance(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        // Get total revenue from completed bookings
        $totalRevenue = \App\Models\Booking::where('provider_id', $provider->id)
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // Calculate total commission
        $commissionRate = $provider->commission_rate ?? 15; // Default 15%
        $totalCommission = $totalRevenue * ($commissionRate / 100);

        // Get total withdrawn amount (completed withdrawals only)
        $totalWithdrawn = \App\Models\WithdrawalRequest::where('provider_id', $provider->id)
            ->where('status', 'completed')
            ->sum('amount');

        // Get pending withdrawal requests
        $pendingWithdrawals = \App\Models\WithdrawalRequest::where('provider_id', $provider->id)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('amount');

        // Calculate available balance
        $netRevenue = $totalRevenue - $totalCommission;
        $availableBalance = $netRevenue - $totalWithdrawn - $pendingWithdrawals;

        // Get minimum withdrawal amount from settings
        $minimumWithdrawal = \App\Models\AppSetting::get('minimum_withdrawal_amount', 100);

        return response()->json([
            'success' => true,
            'data' => [
                'total_revenue' => (float) $totalRevenue,
                'commission_rate' => (float) $commissionRate,
                'total_commission' => (float) $totalCommission,
                'net_revenue' => (float) $netRevenue,
                'total_withdrawn' => (float) $totalWithdrawn,
                'pending_withdrawals' => (float) $pendingWithdrawals,
                'available_balance' => (float) $availableBalance,
                'minimum_withdrawal' => (float) $minimumWithdrawal,
                'can_withdraw' => $availableBalance >= $minimumWithdrawal,
            ]
        ]);
    }

    /**
     * Request a withdrawal
     */
    public function requestWithdrawal(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        // Get available balance
        $balanceResponse = $this->getAvailableBalance($request);
        $balanceData = $balanceResponse->getData()->data;

        // Validate amount
        if ($validated['amount'] > $balanceData->available_balance) {
            throw ValidationException::withMessages([
                'amount' => ['Withdrawal amount exceeds available balance.']
            ]);
        }

        if ($validated['amount'] < $balanceData->minimum_withdrawal) {
            throw ValidationException::withMessages([
                'amount' => ["Minimum withdrawal amount is {$balanceData->minimum_withdrawal} SAR."]
            ]);
        }

        // Get bank info
        if (!$provider->account_title || !$provider->account_number) {
            throw ValidationException::withMessages([
                'bank_info' => ['Please complete your bank account information first.']
            ]);
        }

        // Calculate commission
        $commissionAmount = $validated['amount'] * ($provider->commission_rate ?? 15) / 100;
        $netAmount = $validated['amount'] - $commissionAmount;

        // Create withdrawal request
        $withdrawal = \App\Models\WithdrawalRequest::create([
            'provider_id' => $provider->id,
            'amount' => $validated['amount'],
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'status' => 'pending',
            'bank_account_title' => $provider->account_title,
            'bank_account_number' => $provider->account_number,
            'bank_iban' => $provider->iban,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Send notification to provider and admins
        $this->notificationService->sendWithdrawalRequestSubmitted(
            $user->id,
            $provider->business_name,
            $withdrawal->amount,
            $withdrawal->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request submitted successfully',
            'data' => [
                'id' => $withdrawal->id,
                'amount' => $withdrawal->amount,
                'net_amount' => $withdrawal->net_amount,
                'status' => $withdrawal->status,
                'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    /**
     * Get provider's withdrawal history
     */
    public function getWithdrawalHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $provider = $user->providerProfile;

        if (!$provider) {
            throw ValidationException::withMessages([
                'message' => ['Provider profile not found.']
            ]);
        }

        $query = \App\Models\WithdrawalRequest::where('provider_id', $provider->id);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $withdrawals = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawals' => $withdrawals->getCollection()->map(function ($withdrawal) {
                    return [
                        'id' => $withdrawal->id,
                        'amount' => (float) $withdrawal->amount,
                        'commission_amount' => (float) $withdrawal->commission_amount,
                        'net_amount' => (float) $withdrawal->net_amount,
                        'status' => $withdrawal->status,
                        'bank_account_title' => $withdrawal->bank_account_title,
                        'bank_account_number' => substr($withdrawal->bank_account_number, -4), // Last 4 digits only
                        'notes' => $withdrawal->notes,
                        'admin_notes' => $withdrawal->admin_notes,
                        'rejection_reason' => $withdrawal->rejection_reason,
                        'transaction_reference' => $withdrawal->transaction_reference,
                        'approved_at' => $withdrawal->approved_at?->format('Y-m-d H:i:s'),
                        'processed_at' => $withdrawal->processed_at?->format('Y-m-d H:i:s'),
                        'completed_at' => $withdrawal->completed_at?->format('Y-m-d H:i:s'),
                        'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
                'pagination' => [
                    'current_page' => $withdrawals->currentPage(),
                    'last_page' => $withdrawals->lastPage(),
                    'total' => $withdrawals->total(),
                ]
            ]
        ]);
    }
}
