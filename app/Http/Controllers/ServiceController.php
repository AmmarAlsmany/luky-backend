<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceProvider;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Display a listing of services
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $categoryId = $request->input('category_id');
        $providerId = $request->input('provider_id');
        $status = $request->input('status');
        $serviceLocation = $request->input('service_location');

        $query = Service::with(['providerServiceCategory', 'provider.user']);

        // Search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('name_en', 'LIKE', "%{$search}%")
                  ->orWhere('name_ar', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhereHas('provider', function($q) use ($search) {
                      $q->where('business_name', 'LIKE', "%{$search}%");
                  });
            });
        }

        // Category filter (DEPRECATED - ServiceCategory removed)
        // Old category_id filter removed as ServiceCategory system was deprecated

        // Provider filter
        if ($providerId) {
            $query->where('provider_id', $providerId);
        }

        // Service location filter
        if ($serviceLocation) {
            if ($serviceLocation === 'home') {
                $query->where('available_at_home', true);
            } elseif ($serviceLocation === 'center') {
                $query->where(function($q) {
                    $q->whereNull('available_at_home')
                      ->orWhere('available_at_home', false);
                });
            }
        }

        // Status filter
        if ($status !== null) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        $services = $query->paginate(20);

        // Transform services data for view
        $services->getCollection()->transform(function($service) {
            return [
                'id' => $service->id,
                'name' => $service->name_en ?? $service->name ?? 'N/A',
                'name_en' => $service->name_en,
                'name_ar' => $service->name_ar,
                'description' => $service->description_en ?? $service->description,
                'description_ar' => $service->description_ar,
                'description_en' => $service->description_en,
                'price' => $service->price,
                'home_service_price' => $service->home_service_price,
                'duration' => $service->duration_minutes,
                'duration_minutes' => $service->duration_minutes,
                'is_active' => $service->is_active,
                'available_at_home' => $service->available_at_home,
                'home_service_available' => $service->available_at_home,
                'center_service_available' => !$service->available_at_home || $service->available_at_home === false,
                'image' => $service->image_url,
                'images' => $service->images,
                'provider_service_category' => $service->providerServiceCategory ? [
                    'id' => $service->providerServiceCategory->id,
                    'name' => app()->getLocale() === 'ar' ? $service->providerServiceCategory->name_ar : $service->providerServiceCategory->name_en,
                ] : null,
                'provider' => $service->provider ? [
                    'id' => $service->provider->id,
                    'business_name' => $service->provider->business_name,
                ] : null,
            ];
        });

        // Get statistics
        $stats = [
            'total' => Service::count(),
            'active' => Service::where('is_active', true)->count(),
            'inactive' => Service::where('is_active', false)->count(),
            'home_service' => Service::where('available_at_home', true)->count(),
            'center_service' => Service::where(function($q) {
                $q->whereNull('available_at_home')->orWhere('available_at_home', false);
            })->count(),
        ];

        // Categories removed - ServiceCategory system deprecated
        $categories = [];

        // Get providers for filter
        $providers = ServiceProvider::with('user')->get()->map(function($provider) {
            return [
                'id' => $provider->id,
                'name' => $provider->business_name ?? $provider->user->name ?? 'N/A'
            ];
        });

        // Pagination data
        $pagination = [
            'current_page' => $services->currentPage(),
            'last_page' => $services->lastPage(),
            'per_page' => $services->perPage(),
            'total' => $services->total(),
            'from' => $services->firstItem(),
            'to' => $services->lastItem(),
        ];

        // Filters for maintaining state
        $filters = [
            'search' => $search,
            'category_id' => $categoryId,
            'provider_id' => $providerId,
            'status' => $status,
            'service_location' => $serviceLocation,
        ];

        return view('services.list', [
            'services' => $services->items(),
            'categories' => $categories,
            'providers' => $providers,
            'stats' => $stats,
            'pagination' => $pagination,
            'filters' => $filters,
        ]);
    }

    /**
     * Show the form for creating a new service
     */
    public function create(Request $request)
    {
        // ServiceCategory removed - categories no longer used
        $categories = [];
        $providers = ServiceProvider::with('user')->get();
        $selectedProviderId = $request->input('provider_id'); // Pre-select if coming from provider details

        return view('services.create', compact('categories', 'providers', 'selectedProviderId'));
    }

    /**
     * Store a newly created service
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'provider_id' => 'required|exists:service_providers,id',
            // 'category_id' => 'required|exists:service_categories,id', // DEPRECATED - ServiceCategory removed
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'available_at_home' => 'nullable|boolean',
            'home_service_price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'service_images.*' => 'nullable|image|mimes:jpeg,jpg,png|max:5120', // 5MB max per image
        ]);

        // Set default name if bilingual not provided
        $validated['name'] = $validated['name_en'];
        $validated['description'] = $validated['description_en'] ?? '';
        $validated['is_active'] = $request->has('is_active') ? true : false;
        $validated['is_featured'] = $request->has('is_featured') ? true : false;
        $validated['available_at_home'] = $request->has('available_at_home') ? true : false;

        $service = Service::create($validated);

        // Handle service images upload (maximum 3 images)
        if ($request->hasFile('service_images')) {
            $images = array_slice($request->file('service_images'), 0, 3); // Take only first 3
            foreach ($images as $image) {
                $service->addMedia($image)->toMediaCollection('service_images');
            }
        }

        return redirect()->route('services.index')
            ->with('success', 'Service created successfully with ' . $service->getMedia('service_images')->count() . ' image(s)');
    }

    /**
     * Display the specified service
     */
    public function show($id)
    {
        $service = Service::with(['providerServiceCategory', 'provider.user', 'bookingItems'])->findOrFail($id);

        // Get service statistics
        $stats = [
            'total_bookings' => $service->bookingItems()->count(),
            'total_revenue' => $service->bookingItems()->sum('total_price'),
        ];

        return view('services.details', compact('service', 'stats'));
    }

    /**
     * Show the form for editing the specified service
     */
    public function edit($id)
    {
        $service = Service::findOrFail($id);
        // ServiceCategory removed - categories no longer used
        $categories = [];
        $providers = ServiceProvider::with('user')->get();

        return view('services.edit', compact('service', 'categories', 'providers'));
    }

    /**
     * Update the specified service
     */
    public function update(Request $request, $id)
    {
        $service = Service::findOrFail($id);

        $validated = $request->validate([
            'provider_id' => 'required|exists:service_providers,id',
            // 'category_id' => 'required|exists:service_categories,id', // DEPRECATED - ServiceCategory removed
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description_en' => 'nullable|string|max:1000',
            'description_ar' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0',
            'duration_minutes' => 'required|integer|min:15|max:480',
            'available_at_home' => 'nullable|boolean',
            'home_service_price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'service_images.*' => 'nullable|image|mimes:jpeg,jpg,png|max:5120', // 5MB max per image
        ]);

        // Update default name
        $validated['name'] = $validated['name_en'];
        $validated['description'] = $validated['description_en'] ?? '';
        $validated['is_active'] = $request->has('is_active') ? true : false;
        $validated['is_featured'] = $request->has('is_featured') ? true : false;
        $validated['available_at_home'] = $request->has('available_at_home') ? true : false;

        $service->update($validated);

        // Handle service images upload (respect maximum 3 images total)
        if ($request->hasFile('service_images')) {
            $currentCount = $service->getMedia('service_images')->count();
            $maxNew = 3 - $currentCount;

            if ($maxNew > 0) {
                $images = array_slice($request->file('service_images'), 0, $maxNew);
                foreach ($images as $image) {
                    $service->addMedia($image)->toMediaCollection('service_images');
                }
            }
        }

        $imageCount = $service->getMedia('service_images')->count();
        return redirect()->route('services.index')
            ->with('success', 'Service updated successfully with ' . $imageCount . ' image(s)');
    }

    /**
     * Remove the specified service (soft delete)
     */
    public function destroy($id)
    {
        $service = Service::findOrFail($id);

        // Check for active bookings (pending, confirmed, in_progress)
        $activeBookings = $service->bookingItems()
            ->whereHas('booking', function($q) {
                $q->whereIn('status', ['pending', 'confirmed', 'in_progress']);
            })
            ->count();

        if ($activeBookings > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete this service. It has {$activeBookings} active booking(s). Please complete or cancel these bookings first.",
                'error_type' => 'active_bookings',
                'details' => [
                    'active_bookings' => $activeBookings
                ]
            ], 422);
        }

        // Check for any booking history (even completed/cancelled)
        $totalBookings = $service->bookingItems()->count();

        if ($totalBookings > 0) {
            // Service has booking history but no active bookings
            // We'll soft delete to preserve booking history
            $service->delete(); // Soft delete

            return response()->json([
                'success' => true,
                'message' => "Service archived successfully. Booking history ({$totalBookings} booking(s)) has been preserved.",
                'archived' => true
            ]);
        }

        // Service has no bookings - safe to delete
        $service->delete(); // Soft delete

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully',
        ]);
    }

    /**
     * Get services by provider
     */
    public function byProvider($providerId)
    {
        $services = Service::where('provider_id', $providerId)
            ->with(['providerServiceCategory'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['services' => $services]
        ]);
    }

    /**
     * Search services
     */
    public function search(Request $request)
    {
        $query = $request->input('q');

        $services = Service::where('name', 'LIKE', "%{$query}%")
            ->orWhere('description', 'LIKE', "%{$query}%")
            ->with(['providerServiceCategory', 'provider'])
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['services' => $services]
        ]);
    }
}
