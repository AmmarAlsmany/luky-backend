<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ServiceProvider;
use App\Models\ProviderDocument;
use App\Models\Booking;
use App\Models\City;
use App\Models\Service;
use App\Services\SmsService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\ProviderPendingChange;

class ProviderController extends Controller
{
    protected SmsService $smsService;
    protected NotificationService $notificationService;

    public function __construct(SmsService $smsService, NotificationService $notificationService)
    {
        $this->smsService = $smsService;
        $this->notificationService = $notificationService;
    }
    /**
     * Display provider list
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $status = $request->input('status');
        $businessType = $request->input('business_type');
        $cityId = $request->input('city_id');

        $query = ServiceProvider::with(['user', 'city', 'contracts' => function($q) {
                $q->where('status', 'active')->latest();
            }])
            ->withCount(['bookings as total_bookings'])
            ->withSum(['bookings as total_revenue' => function ($query) {
                $query->where('status', 'completed');
            }], 'total_amount');

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Status filter (is_active for providers)
        if ($status) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Business type filter
        if ($businessType) {
            $query->where('business_type', $businessType);
        }

        // City filter
        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        $providers = $query->paginate($perPage);

        // Transform provider data
        $providers->getCollection()->transform(function ($provider) {
            $user = $provider->user;
            $activeContract = $provider->contracts->first();

            return [
                'id' => $provider->id,
                'user_id' => $provider->user_id,
                'name' => $user->name ?? 'N/A',
                'email' => $user->email ?? 'N/A',
                'phone' => $user->phone ?? 'N/A',
                'status' => $provider->is_active ? 'active' : 'inactive',
                'avatar_url' => $user->avatar ?? null,
                'logo_url' => $provider->logo_url,
                'business_name' => $provider->business_name ?? null,
                'business_type' => $provider->business_type ?? 'individual',
                'verification_status' => $provider->verification_status ?? 'pending',
                'rating' => $provider->average_rating ?? 0,
                'total_reviews' => $provider->total_reviews ?? 0,
                'total_bookings' => $provider->total_bookings ?? 0,
                'total_revenue' => $provider->total_revenue ?? 0,
                'address' => $provider->address ?? null,
                'city' => $provider->city ? [
                    'id' => $provider->city->id,
                    'name_en' => $provider->city->name_en ?? '',
                    'name_ar' => $provider->city->name_ar ?? '',
                ] : null,
                'city_name' => $provider->city ? (app()->getLocale() === 'ar' ? $provider->city->name_ar : $provider->city->name_en) : 'N/A',
                'contract' => $activeContract ? [
                    'contract_number' => $activeContract->contract_number,
                    'start_date' => $activeContract->start_date->format('Y-m-d'),
                    'end_date' => $activeContract->end_date?->format('Y-m-d'),
                    'status' => $activeContract->status,
                ] : null,
                'created_at' => $provider->created_at,
                'updated_at' => $provider->updated_at,
            ];
        });

        // Get statistics
        $stats = [
            'total' => ServiceProvider::count(),
            'active' => ServiceProvider::where('is_active', true)->count(),
            'inactive' => ServiceProvider::where('is_active', false)->count(),
            'pending' => ServiceProvider::where('verification_status', 'pending')->count(),
            'approved' => ServiceProvider::where('verification_status', 'approved')->count(),
            'rejected' => ServiceProvider::where('verification_status', 'rejected')->count(),
            'total_revenue' => Booking::where('status', 'completed')->sum('total_amount') ?? 0,
        ];

        // Get cities for filter
        $cities = City::select('id', 'name_en', 'name_ar')->get()->toArray();

        $filters = compact('status', 'businessType', 'cityId', 'search');
        $pagination = [
            'current_page' => $providers->currentPage(),
            'last_page' => $providers->lastPage(),
            'per_page' => $providers->perPage(),
            'total' => $providers->total(),
            'from' => $providers->firstItem(),
            'to' => $providers->lastItem(),
        ];

        return view('provider.list', [
            'providers' => $providers->items(),
            'pagination' => $pagination,
            'stats' => $stats,
            'filters' => $filters,
            'cities' => $cities
        ]);
    }

    /**
     * Display pending providers (awaiting approval)
     */
    public function pending()
    {
        $providers = ServiceProvider::with(['user', 'city', 'documents', 'media'])
            ->where('verification_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();

        // Transform for blade template
        $providers = $providers->map(function ($provider) {
            $user = $provider->user;

            // Collect ALL files: documents + logo + building + gallery
            $allFiles = [];

            // 1. Add license/registration documents from provider_documents table
            foreach ($provider->documents as $doc) {
                $allFiles[] = [
                    'id' => $doc->id,
                    'name' => $doc->original_name ?? basename($doc->file_path),
                    'file_name' => $doc->original_name,
                    'url' => asset('storage/' . $doc->file_path),
                    'file_url' => asset('storage/' . $doc->file_path),
                    'type' => ucfirst(str_replace('_', ' ', $doc->document_type)),
                    'document_type' => $doc->document_type,
                    'verification_status' => $doc->verification_status,
                    'mime_type' => $doc->mime_type,
                ];
            }

            // 2. Add logo from media library
            $logo = $provider->getFirstMedia('logo');
            if ($logo) {
                $allFiles[] = [
                    'id' => 'logo_' . $logo->id,
                    'name' => $logo->file_name,
                    'file_name' => $logo->file_name,
                    'url' => $logo->getUrl(),
                    'file_url' => $logo->getUrl(),
                    'type' => 'Logo',
                    'document_type' => 'logo',
                    'verification_status' => 'pending',
                    'mime_type' => $logo->mime_type,
                ];
            }

            // 3. Add building image from media library
            $buildingImage = $provider->getFirstMedia('building_image');
            if ($buildingImage) {
                $allFiles[] = [
                    'id' => 'building_' . $buildingImage->id,
                    'name' => $buildingImage->file_name,
                    'file_name' => $buildingImage->file_name,
                    'url' => $buildingImage->getUrl(),
                    'file_url' => $buildingImage->getUrl(),
                    'type' => 'Building Image',
                    'document_type' => 'building_image',
                    'verification_status' => 'pending',
                    'mime_type' => $buildingImage->mime_type,
                ];
            }

            // 4. Add gallery images from media library
            $galleryImages = $provider->getMedia('gallery');
            foreach ($galleryImages as $index => $galleryImage) {
                $allFiles[] = [
                    'id' => 'gallery_' . $galleryImage->id,
                    'name' => $galleryImage->file_name,
                    'file_name' => $galleryImage->file_name,
                    'url' => $galleryImage->getUrl(),
                    'file_url' => $galleryImage->getUrl(),
                    'type' => 'Gallery Photo ' . ($index + 1),
                    'document_type' => 'gallery',
                    'verification_status' => 'pending',
                    'mime_type' => $galleryImage->mime_type,
                ];
            }

            // Debug: Log all files count
            \Log::info('Provider ' . $provider->id . ' has ' . count($allFiles) . ' total files (documents + media)');

            return [
                'id' => $provider->id,
                'user_id' => $provider->user_id,
                'name' => $user->name ?? 'N/A',
                'email' => $user->email ?? 'N/A',
                'phone' => $user->phone ?? 'N/A',
                'avatar_url' => $user->avatar ?? null,
                'avatar' => $user->avatar ?? null,
                'logo_url' => $provider->logo_url ?? null,
                'business_name' => $provider->business_name,
                'business_type' => $provider->business_type,
                'verification_status' => $provider->verification_status,
                'city' => $provider->city,
                'created_at' => $provider->created_at,
                'documents' => $allFiles, // Now includes ALL files: documents + logo + building + gallery
                'providerProfile' => [
                    'logo_url' => $provider->logo_url ?? null,
                ],
            ];
        });

        return view('provider.pending', compact('providers'));
    }

    /**
     * Show create provider form
     */
    public function create()
    {
        $cities = City::select('id', 'name_en', 'name_ar')->get();
        $categories = \App\Models\ProviderCategory::where('is_active', true)->orderBy('sort_order')->get();
        return view('provider.create', compact('cities', 'categories'));
    }

    /**
     * Store new provider
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'required|string|unique:users,phone',
            'business_name' => 'required|string|max:255',
            'provider_category_id' => 'required|exists:provider_categories,id',
            'description' => 'nullable|string|max:1000',
            'city_id' => 'required|exists:cities,id',
            'address' => 'nullable|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'working_hours' => 'nullable|array',
            'off_days' => 'nullable|array',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png|max:2048', // 2MB max
            'building_image' => 'nullable|image|mimes:jpeg,jpg,png|max:5120', // 5MB max
            'documents.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max per file
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date|after:contract_start_date',
            'payment_terms' => 'nullable|string|max:1000',
            'contract_notes' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Get provider category
            $providerCategory = \App\Models\ProviderCategory::findOrFail($validated['provider_category_id']);
            $businessType = strtolower($providerCategory->name_en); // Use category name as business type

            // Create new user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'user_type' => 'provider',
                'city_id' => $validated['city_id'],
            ]);

            $user->assignRole('provider');

            // Filter out empty off_days (null or empty strings)
            $offDays = [];
            if (!empty($validated['off_days'])) {
                $offDays = array_filter($validated['off_days'], function($date) {
                    return !empty($date) && $date !== null;
                });
                $offDays = array_values($offDays); // Re-index array
            }

            // Create provider profile
            $provider = ServiceProvider::create([
                'user_id' => $user->id,
                'business_name' => $validated['business_name'],
                'business_type' => $businessType, // Mapped from category
                'description' => $validated['description'] ?? '',
                'city_id' => $validated['city_id'],
                'address' => $validated['address'] ?? '',
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'working_hours' => $validated['working_hours'] ?? [],
                'off_days' => $offDays,
                'verification_status' => 'pending',
                'commission_rate' => 15.00,
                'is_active' => false,
            ]);

            // Handle document uploads
            if ($request->has('documents')) {
                $documentTypes = ['freelance_license', 'commercial_register', 'municipal_license', 'national_id', 'agreement_contract'];

                foreach ($documentTypes as $docType) {
                    if ($request->hasFile("documents.{$docType}")) {
                        $file = $request->file("documents.{$docType}");
                        $fileName = time() . '_' . $docType . '_' . $file->getClientOriginalName();
                        $filePath = $file->storeAs('provider_documents/' . $provider->id, $fileName, 'public');

                        // Create document record
                        ProviderDocument::create([
                            'provider_id' => $provider->id,
                            'document_type' => $docType,
                            'file_path' => $filePath,
                            'original_name' => $file->getClientOriginalName(),
                            'mime_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(),
                            'verification_status' => 'pending',
                        ]);
                    }
                }
            }

            // Create provider contract (if start date is provided)
            if (!empty($validated['contract_start_date'])) {
                $contractNumber = 'CON-' . strtoupper(uniqid());
                \App\Models\ProviderContract::create([
                    'provider_id' => $provider->id,
                    'contract_number' => $contractNumber,
                    'start_date' => $validated['contract_start_date'],
                    'end_date' => $validated['contract_end_date'] ?? null,
                    'commission_rate' => 15.00,
                    'payment_terms' => $validated['payment_terms'] ?? null,
                    'notes' => $validated['contract_notes'] ?? null,
                    'status' => 'active',
                    'created_by' => auth()->id(),
                ]);
            }

            DB::commit();

            // Handle media uploads AFTER commit to ensure proper saving
            // Handle logo upload
            if ($request->hasFile('logo')) {
                $provider->addMediaFromRequest('logo')->toMediaCollection('logo');
            }

            // Handle building image upload
            if ($request->hasFile('building_image')) {
                $provider->addMediaFromRequest('building_image')->toMediaCollection('building_image');
            }

            return redirect()->route('providers.pending')
                ->with('success', 'Provider created successfully and pending approval.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to create provider: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display provider details
     */
    public function show($id)
    {
        $providerProfile = ServiceProvider::with(['user', 'city', 'services.category', 'documents', 'contracts' => function($q) {
            $q->latest();
        }])->findOrFail($id);
        $provider = $providerProfile->user;

        // Get active contract
        $activeContract = $providerProfile->contracts->where('status', 'active')->first();
        $allContracts = $providerProfile->contracts->map(function($contract) {
            return [
                'id' => $contract->id,
                'contract_number' => $contract->contract_number,
                'start_date' => $contract->start_date->format('Y-m-d'),
                'end_date' => $contract->end_date?->format('Y-m-d'),
                'commission_rate' => $contract->commission_rate,
                'payment_terms' => $contract->payment_terms,
                'notes' => $contract->notes,
                'status' => $contract->status,
                'created_at' => $contract->created_at,
            ];
        })->toArray();

        // Get provider statistics
        $totalBookings = Booking::where('provider_id', $id)->count();
        $completedBookings = Booking::where('provider_id', $id)->where('status', 'completed')->count();
        $cancelledBookings = Booking::where('provider_id', $id)->where('status', 'cancelled')->count();
        $pendingBookings = Booking::where('provider_id', $id)->whereIn('status', ['pending', 'confirmed'])->count();

        $totalRevenue = Booking::where('provider_id', $id)
            ->where('status', 'completed')
            ->sum('total_amount');

        // Calculate monthly gain (current month revenue)
        $monthlyGain = Booking::where('provider_id', $id)
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_amount');

        $providerData = [
            'id' => $providerProfile->id,
            'user_id' => $provider->id ?? null,
            'name' => $provider->name ?? 'N/A',
            'email' => $provider->email ?? 'N/A',
            'phone' => $provider->phone ?? 'N/A',
            'status' => $providerProfile->is_active ? 'active' : 'inactive',
            'avatar_url' => $provider->avatar ?? null,
            'business_name' => $providerProfile->business_name,
            'business_type' => $providerProfile->business_type,
            'description' => $providerProfile->description,
            'license_number' => $providerProfile->license_number,
            'verification_status' => $providerProfile->verification_status,
            'rejection_reason' => $providerProfile->rejection_reason,
            'rating' => $providerProfile->average_rating ?? 0,
            'total_reviews' => $providerProfile->total_reviews ?? 0,
            'logo_url' => $providerProfile->logo_url,
            'building_image_url' => $providerProfile->building_image_url,
            'address' => $providerProfile->address,
            'latitude' => $providerProfile->latitude,
            'longitude' => $providerProfile->longitude,
            'working_hours' => $providerProfile->working_hours ?? [],
            'off_days' => $providerProfile->off_days ?? [],
            'commission_rate' => $providerProfile->commission_rate ?? 15,
            'account_title' => $providerProfile->account_title,
            'account_number' => $providerProfile->account_number,
            'iban' => $providerProfile->iban,
            'currency' => $providerProfile->currency,
            'is_active' => $providerProfile->is_active,
            'city' => $providerProfile->city ? [
                'id' => $providerProfile->city->id,
                'name_en' => $providerProfile->city->name_en ?? '',
                'name_ar' => $providerProfile->city->name_ar ?? '',
            ] : null,
            'documents' => $this->getAllProviderFiles($providerProfile),
            'contract' => $activeContract ? [
                'contract_number' => $activeContract->contract_number,
                'start_date' => $activeContract->start_date->format('Y-m-d'),
                'end_date' => $activeContract->end_date?->format('Y-m-d'),
                'commission_rate' => $activeContract->commission_rate,
                'payment_terms' => $activeContract->payment_terms,
                'notes' => $activeContract->notes,
                'status' => $activeContract->status,
            ] : null,
            'all_contracts' => $allContracts,
            'total_bookings' => $totalBookings,
            'completed_bookings' => $completedBookings,
            'cancelled_bookings' => $cancelledBookings,
            'pending_bookings' => $pendingBookings,
            'total_revenue' => $totalRevenue,
            'monthly_gain' => $monthlyGain,
            'created_at' => $provider->created_at,
            'updated_at' => $provider->updated_at,
            // Add statistics nested structure for view compatibility
            'statistics' => [
                'total_bookings' => $totalBookings,
                'completed_bookings' => $completedBookings,
                'cancelled_bookings' => $cancelledBookings,
                'pending_bookings' => $pendingBookings,
                'total_revenue' => $totalRevenue,
                'monthly_gain' => $monthlyGain,
            ],
        ];

        // Get provider services
        $services = $providerProfile->services->map(function ($service) {
            return [
                'id' => $service->id,
                'name' => $service->name_en ?? $service->name_ar ?? $service->name ?? 'N/A',
                'name_en' => $service->name_en ?? $service->name ?? 'N/A',
                'name_ar' => $service->name_ar ?? $service->name ?? 'N/A',
                'description' => $service->description ?? '',
                'description_en' => $service->description_en ?? $service->description ?? '',
                'description_ar' => $service->description_ar ?? $service->description ?? '',
                'price' => $service->price ?? 0,
                'duration' => $service->duration_minutes ?? 0,
                'duration_minutes' => $service->duration_minutes ?? 0,
                'available_at_home' => $service->available_at_home ?? false,
                'home_service_price' => $service->home_service_price ?? null,
                'provider_service_category_id' => $service->provider_service_category_id ?? null,
                'provider_service_category' => $service->providerServiceCategory ? [
                    'id' => $service->providerServiceCategory->id,
                    'name_en' => $service->providerServiceCategory->name_en ?? '',
                    'name_ar' => $service->providerServiceCategory->name_ar ?? '',
                ] : null,
                'is_active' => $service->is_active ?? true,
                'is_featured' => $service->is_featured ?? false,
                'currency' => 'SAR',
                'created_at' => $service->created_at,
            ];
        })->toArray();

        // Get provider reviews
        $reviews = \App\Models\Review::where('provider_id', $id)
            ->with(['client', 'booking'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Calculate profit by provider service category (revenue per service category)
        $profitByCategory = DB::table('booking_items')
            ->join('bookings', 'booking_items.booking_id', '=', 'bookings.id')
            ->join('services', 'booking_items.service_id', '=', 'services.id')
            ->leftJoin('provider_service_categories', 'services.provider_service_category_id', '=', 'provider_service_categories.id')
            ->where('bookings.provider_id', $id)
            ->where('bookings.status', 'completed')
            ->select(
                DB::raw('COALESCE(provider_service_categories.name_en, "Uncategorized") as name'),
                DB::raw('SUM(booking_items.total_price) as amount')
            )
            ->groupBy('provider_service_categories.id', 'provider_service_categories.name_en')
            ->get();

        // Calculate total for percentages
        $totalCategoryRevenue = $profitByCategory->sum('amount');

        // Map with percentages
        $profitByCategory = $profitByCategory->map(function($item) use ($totalCategoryRevenue) {
            $percentage = $totalCategoryRevenue > 0 ? ($item->amount / $totalCategoryRevenue) * 100 : 0;
            return [
                'name' => $item->name,
                'revenue' => $item->amount, // Use 'revenue' key as expected by view
                'percentage' => round($percentage, 1),
            ];
        })->toArray();

        // Prepare revenue data for charts
        $revenue = [
            'total_revenue' => $totalRevenue,
            'monthly_revenue' => $monthlyGain,
            'yearly_revenue' => Booking::where('provider_id', $id)
                ->where('status', 'completed')
                ->whereYear('created_at', now()->year)
                ->sum('total_amount'),
            'categories' => $profitByCategory, // Add categories for profit by category chart
        ];

        return view('provider.details', [
            'provider' => $providerData,
            'services' => $services,
            'revenue' => $revenue,
            'reviews' => $reviews,
        ]);
    }

    /**
     * Update provider status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $providerProfile = ServiceProvider::with('user')->findOrFail($id);

        if (!$providerProfile->user) {
            return response()->json([
                'success' => false,
                'message' => 'Provider user account not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Update user status and is_active
            $providerProfile->user->update([
                'status' => $validated['status'],
                'is_active' => $validated['status'] === 'active',
            ]);

            // Update ServiceProvider status
            $providerUpdate = [
                'is_active' => $validated['status'] === 'active',
            ];

            // If activating a rejected provider, approve them and clear rejection
            if ($validated['status'] === 'active' && $providerProfile->verification_status === 'rejected') {
                $providerUpdate['verification_status'] = 'approved';
                $providerUpdate['rejection_reason'] = null;
                $providerUpdate['verified_at'] = now();
            }
            // If deactivating an approved provider, don't change verification status
            // Just set is_active to false

            $providerProfile->update($providerUpdate);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Provider status updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify provider (approve/reject)
     */
    public function verify(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'notes' => 'nullable|string|max:500',
        ]);

        // Find the provider by ServiceProvider ID (not user ID)
        $providerProfile = ServiceProvider::with('user')->findOrFail($id);

        if (!$providerProfile->user) {
            return redirect()->back()->with('error', 'Provider user account not found');
        }

        $provider = $providerProfile->user;

        DB::beginTransaction();
        try {
            if ($validated['action'] === 'approve') {
                $providerProfile->update([
                    'verification_status' => 'approved',
                    'rejection_reason' => null,
                    'is_active' => true,
                    'verified_at' => now(),
                ]);
                // Status is already set via is_active above

                // Send push notification via FCM
                $this->notificationService->sendProviderApproved(
                    $provider->id,
                    $providerProfile->business_name
                );

                $message = 'Provider approved successfully';
            } else {
                // Log rejection details
                \Log::info('=== PROVIDER REJECTED ===', [
                    'provider_id' => $providerProfile->id,
                    'user_id' => $provider->id,
                    'business_name' => $providerProfile->business_name,
                    'email' => $provider->email,
                    'phone' => $provider->phone,
                    'rejection_reason' => $validated['notes'] ?? 'Application rejected',
                    'admin_id' => auth()->user()->id,
                ]);

                // Update provider profile with rejection details
                $providerProfile->update([
                    'verification_status' => 'rejected',
                    'rejection_reason' => $validated['notes'] ?? 'Application rejected',
                    'is_active' => false,
                    'verified_at' => null,
                ]);

                // Deactivate user account so they can't use the app
                $provider->update([
                    'status' => 'inactive',
                    'is_active' => false,
                ]);

                // Send rejection notification
                $this->notificationService->sendProviderRejected(
                    $provider->id,
                    $providerProfile->business_name,
                    $validated['notes'] ?? ''
                );

                $message = 'Provider rejected. Account is now inactive. Provider can login to see rejection reason.';
            }

            DB::commit();

            return redirect()->route('providers.pending')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()
                ->with('error', 'Failed to process verification: ' . $e->getMessage());
        }
    }

    /**
     * Send notification to provider
     */
    public function sendNotification(Request $request, $id)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:500',
            'type' => 'nullable|string|in:general,promotional,informational,alert',
            'notification_type' => 'nullable|string|in:general,promotional,informational,alert', // Alternative param name from JS
        ]);

        $provider = ServiceProvider::with('user')->findOrFail($id);

        if (!$provider->user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider user account not found',
                ], 404);
            }
            return redirect()->back()->with('error', 'Provider user account not found');
        }

        try {
            // Support both 'type' and 'notification_type' parameter names
            $notificationType = $validated['type'] ?? $validated['notification_type'] ?? 'general';

            // Create notification in database
            \App\Models\Notification::create([
                'user_id' => $provider->user_id,
                'type' => $notificationType,
                'title' => $validated['title'],
                'body' => $validated['message'],
                'data' => [
                    'sent_by' => 'admin',
                    'sent_at' => now()->toISOString(),
                ],
                'is_read' => false,
                'is_sent' => true,
            ]);

            // TODO: Implement push notification via Firebase/OneSignal if configured
            // Example: dispatch(new SendPushNotification($provider->user, $validated['title'], $validated['message']));

            $message = 'Notification sent successfully to ' . $provider->business_name;

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                ]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send notification: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to send notification: ' . $e->getMessage());
        }
    }

    /**
     * Send message to provider
     */
    public function sendMessage(Request $request, $id)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:500',
        ]);

        $provider = ServiceProvider::with('user')->findOrFail($id);

        if (!$provider->user) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Provider user account not found',
                ], 404);
            }
            return redirect()->back()->with('error', 'Provider user account not found');
        }

        try {
            // Send SMS
            $smsSent = false;
            try {
                $smsSent = $this->smsService->send($provider->user->phone, $validated['message']);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('SMS send failed to provider: ' . $e->getMessage());
            }

            // Create notification for the admin message
            \App\Models\Notification::create([
                'user_id' => $provider->user_id,
                'type' => 'sms',
                'title' => 'SMS Message from Admin',
                'body' => $validated['message'],
                'data' => [
                    'sender' => 'admin',
                    'sender_id' => auth()->id(),
                    'sender_name' => auth()->user()->name ?? 'Admin',
                    'sent_at' => now()->toISOString(),
                    'phone' => $provider->user->phone,
                    'provider_id' => $provider->id,
                    'business_name' => $provider->business_name,
                ],
                'is_read' => false,
                'is_sent' => $smsSent,
            ]);

            $message = $smsSent 
                ? 'SMS sent successfully to ' . $provider->business_name . ' (' . $provider->user->phone . ')'
                : 'Message queued to be sent to ' . $provider->business_name;

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'sms_sent' => $smsSent
                ]);
            }

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send message: ' . $e->getMessage(),
                ], 500);
            }
            return redirect()->back()->with('error', 'Failed to send message: ' . $e->getMessage());
        }
    }

    /**
     * Verify individual document
     */
    public function verifyDocument(Request $request, $providerId, $documentId)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $provider = ServiceProvider::findOrFail($providerId);
        $document = ProviderDocument::where('provider_id', $providerId)
            ->where('id', $documentId)
            ->firstOrFail();

        DB::beginTransaction();
        try {
            if ($validated['action'] === 'approve') {
                $document->update([
                    'verification_status' => 'approved',
                    'rejection_reason' => null,
                    'verified_at' => now(),
                ]);

                $message = 'Document approved successfully';
            } else {
                $document->update([
                    'verification_status' => 'rejected',
                    'rejection_reason' => $validated['rejection_reason'] ?? 'Document rejected by admin',
                    'verified_at' => null,
                ]);

                $message = 'Document rejected';
            }

            // Check if all documents are approved, then auto-approve provider
            $allDocumentsApproved = $provider->documents()
                ->where('verification_status', '!=', 'approved')
                ->count() === 0;

            if ($allDocumentsApproved && $provider->documents()->count() > 0) {
                $provider->update([
                    'verification_status' => 'approved',
                    'is_active' => true,
                    'verified_at' => now(),
                ]);

                if ($provider->user) {
                    $provider->user->update(['status' => 'active']);
                }

                // Send approval notification
                \App\Models\Notification::create([
                    'user_id' => $provider->user_id,
                    'type' => 'approval',
                    'title' => 'Provider Account Approved',
                    'body' => 'All your documents have been verified. Your provider account is now active!',
                    'data' => ['auto_approved' => true],
                    'is_read' => false,
                    'is_sent' => true,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'all_approved' => $allDocumentsApproved ?? false,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify document: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export providers to CSV/Excel
     */
    public function export(Request $request)
    {
        $format = $request->input('format', 'csv');
        $search = $request->input('search');
        $status = $request->input('status');
        $businessType = $request->input('business_type');
        $cityId = $request->input('city_id');

        $query = ServiceProvider::with(['user', 'city'])
            ->withCount(['bookings as total_bookings'])
            ->withSum(['bookings as total_revenue' => function ($query) {
                $query->where('status', 'completed');
            }], 'total_amount');

        // Apply same filters as index
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'LIKE', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'LIKE', "%{$search}%")
                        ->orWhere('email', 'LIKE', "%{$search}%")
                        ->orWhere('phone', 'LIKE', "%{$search}%");
                  });
            });
        }

        if ($status) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if ($businessType) {
            $query->where('business_type', $businessType);
        }

        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        $providers = $query->get();

        if ($format === 'csv') {
            $filename = 'providers-' . date('Y-m-d-His') . '.csv';
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            $callback = function () use ($providers) {
                $file = fopen('php://output', 'w');
                // Add BOM for UTF-8
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
                
                // Headers
                fputcsv($file, [
                    'ID',
                    'Name',
                    'Email',
                    'Phone',
                    'Business Name',
                    'Business Type',
                    'Status',
                    'Verification Status',
                    'City',
                    'Rating',
                    'Total Bookings',
                    'Total Revenue (SAR)',
                    'Joined Date',
                ]);

                foreach ($providers as $provider) {
                    $user = $provider->user;
                    fputcsv($file, [
                        $provider->id,
                        $user->name ?? 'N/A',
                        $user->email ?? 'N/A',
                        $user->phone ?? 'N/A',
                        $provider->business_name ?? '',
                        ucfirst(str_replace('_', ' ', $provider->business_type ?? 'individual')),
                        $provider->is_active ? 'Active' : 'Inactive',
                        ucfirst($provider->verification_status ?? 'pending'),
                        $provider->city ? (app()->getLocale() === 'ar' ? $provider->city->name_ar : $provider->city->name_en) : 'N/A',
                        number_format($provider->average_rating ?? 0, 2),
                        $provider->total_bookings ?? 0,
                        number_format($provider->total_revenue ?? 0, 2),
                        $provider->created_at ? $provider->created_at->format('Y-m-d H:i:s') : '',
                    ]);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        // For now, only CSV is supported
        return redirect()->back()->with('error', 'Unsupported export format');
    }

    /**
     * Delete provider
     */
    public function destroy($id)
    {
        $providerProfile = ServiceProvider::with('user')->findOrFail($id);

        DB::beginTransaction();
        try {
            // Soft delete the provider profile
            $providerProfile->delete();

            // Optionally soft delete the user account as well
            if ($providerProfile->user) {
                $providerProfile->user->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Provider deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete provider: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show pending profile changes page
     */
    public function pendingChanges()
    {
        try {
            $pendingChanges = ProviderPendingChange::with(['provider.user'])
                ->where('status', 'pending')
                ->orderBy('created_at', 'asc')
                ->get()
                ->map(function ($change) {
                    return [
                        'id' => $change->id,
                        'provider_id' => $change->provider_id,
                        'provider_name' => $change->provider && $change->provider->user ? $change->provider->user->name : 'N/A',
                        'business_name' => $change->provider ? $change->provider->business_name : 'N/A',
                        'changed_fields' => $change->changed_fields,
                        'old_values' => $change->old_values,
                        'status' => $change->status,
                        'created_at' => $change->created_at,
                        'updated_at' => $change->updated_at,
                    ];
                });

            return view('provider.pending-changes', [
                'pendingChanges' => $pendingChanges
            ]);
        } catch (\Exception $e) {
            \Log::error('Error loading pending changes: ' . $e->getMessage());
            return view('provider.pending-changes', [
                'pendingChanges' => []
            ]);
        }
    }

    /**
     * Approve pending profile change
     */
    public function approvePendingChange(Request $request, $id)
    {
        $admin = $request->user();

        DB::beginTransaction();
        try {
            $change = ProviderPendingChange::with('provider')->findOrFail($id);

            if ($change->status !== 'pending') {
                return redirect()->route('providers.pendingChanges')
                    ->with('error', 'This change request has already been processed.');
            }

            // Apply the changes to the provider profile
            $change->provider->update($change->changed_fields);

            // Mark the change as approved
            $change->update([
                'status' => 'approved',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'admin_notes' => $request->input('admin_notes'),
            ]);

            DB::commit();

            \Log::info('=== PROVIDER CHANGE APPROVED ===', [
                'change_id' => $change->id,
                'provider_id' => $change->provider_id,
                'approved_by' => $admin->id,
            ]);

            // Send notification to provider
            $this->notificationService->sendProfileChangeApproved(
                $change->provider->user_id,
                $change->provider->business_name
            );

            return redirect()->route('providers.pendingChanges')
                ->with('success', 'Profile changes approved successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error approving pending change: ' . $e->getMessage());
            return redirect()->route('providers.pendingChanges')
                ->with('error', 'An error occurred while approving the changes');
        }
    }

    /**
     * Reject pending profile change
     */
    public function rejectPendingChange(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000'
        ]);

        $admin = $request->user();

        try {
            $change = ProviderPendingChange::with('provider')->findOrFail($id);

            if ($change->status !== 'pending') {
                return redirect()->route('providers.pendingChanges')
                    ->with('error', 'This change request has already been processed.');
            }

            $change->update([
                'status' => 'rejected',
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'rejection_reason' => $request->input('rejection_reason'),
                'admin_notes' => $request->input('admin_notes'),
            ]);

            \Log::info('=== PROVIDER CHANGE REJECTED ===', [
                'change_id' => $change->id,
                'provider_id' => $change->provider_id,
                'rejected_by' => $admin->id,
            ]);

            // Send notification to provider
            $this->notificationService->sendProfileChangeRejected(
                $change->provider->user_id,
                $change->provider->business_name,
                $request->input('rejection_reason')
            );

            return redirect()->route('providers.pendingChanges')
                ->with('success', 'Profile changes rejected successfully');
        } catch (\Exception $e) {
            \Log::error('Error rejecting pending change: ' . $e->getMessage());
            return redirect()->route('providers.pendingChanges')
                ->with('error', 'An error occurred while rejecting the changes');
        }
    }

    /**
     * Get all files for a provider (documents + gallery + logo + building image)
     */
    private function getAllProviderFiles(ServiceProvider $provider): array
    {
        $allFiles = [];

        // 1. Add documents from provider_documents table
        foreach ($provider->documents as $doc) {
            $allFiles[] = [
                'id' => $doc->id,
                'name' => $doc->original_name ?? basename($doc->file_path),
                'file_name' => $doc->original_name,
                'url' => asset('storage/' . $doc->file_path),
                'file_url' => asset('storage/' . $doc->file_path),
                'type' => ucfirst(str_replace('_', ' ', $doc->document_type)),
                'document_type' => $doc->document_type,
                'verification_status' => $doc->verification_status,
                'mime_type' => $doc->mime_type,
            ];
        }

        // 2. Add gallery images from media library
        $galleryImages = $provider->getMedia('gallery');
        foreach ($galleryImages as $index => $galleryImage) {
            $allFiles[] = [
                'id' => 'gallery_' . $galleryImage->id,
                'name' => $galleryImage->file_name,
                'file_name' => $galleryImage->file_name,
                'url' => $galleryImage->getUrl(),
                'file_url' => $galleryImage->getUrl(),
                'type' => 'Gallery Photo ' . ($index + 1),
                'document_type' => 'gallery',
                'verification_status' => 'approved',
                'mime_type' => $galleryImage->mime_type,
            ];
        }

        return $allFiles;
    }
}
