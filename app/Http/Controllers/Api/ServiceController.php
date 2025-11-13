<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use App\Models\ServiceProvider;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\ServiceCategoryResource;
use App\Http\Resources\ProviderResource;
use App\Http\Resources\ServiceResource;

class ServiceController extends Controller
{
    /**
     * Get all active service categories
     */
    public function categories(): JsonResponse
    {
        $categories = ServiceCategory::active()->get();

        return response()->json([
            'success' => true,
            'data' => ServiceCategoryResource::collection($categories),
            'total' => $categories->count()
        ]);
    }

    /**
     * Get providers with filtering and pagination
     */
    public function providers(Request $request): JsonResponse
    {
        $query = ServiceProvider::with(['user', 'city'])
            ->approved()
            ->active();

        // City filtering
        if ($request->has('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        // Business type filtering
        if ($request->has('business_type')) {
            $query->where('business_type', $request->business_type);
        }

        // Featured providers first
        if ($request->get('featured_first', false)) {
            $query->orderByDesc('is_featured');
        }

        // Location-based filtering (if coordinates provided)
        if ($request->has(['latitude', 'longitude'])) {
            $lat = $request->latitude;
            $lng = $request->longitude;
            $radius = $request->get('radius', 10); // Default 10km radius

            $query->selectRaw("
                *, 
                (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * 
                cos(radians(longitude) - radians(?)) + sin(radians(?)) * 
                sin(radians(latitude)))) AS distance
            ", [$lat, $lng, $lat])
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
        }

        // Sorting options
        switch ($request->get('sort', 'rating')) {
            case 'rating':
                $query->orderByDesc('average_rating');
                break;
            case 'newest':
                $query->orderByDesc('created_at');
                break;
            case 'name':
                $query->orderBy('business_name');
                break;
        }

        // Add default sorting
        $query->orderByDesc('is_featured')
            ->orderByDesc('average_rating');

        $perPage = min($request->get('per_page', 15), 50); // Max 50 per page
        $providers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProviderResource::collection($providers->items()),
            'pagination' => [
                'current_page' => $providers->currentPage(),
                'last_page' => $providers->lastPage(),
                'per_page' => $providers->perPage(),
                'total' => $providers->total(),
                'from' => $providers->firstItem(),
                'to' => $providers->lastItem()
            ]
        ]);
    }

    /**
     * Get single provider details
     */
    public function providerDetails(string $id): JsonResponse
    {
        $provider = ServiceProvider::with([
            'user',
            'city',
            'services' => function ($query) {
                $query->where('is_active', true)
                      ->with('category')  // ← Load category for each service
                      ->orderBy('sort_order');
            }
        ])
            ->approved()
            ->active()
            ->findOrFail((int)$id);

        return response()->json([
            'success' => true,
            'data' => new ProviderResource($provider)
        ]);
    }

    /**
     * Search providers and services with advanced filtering
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'sometimes|string|min:2',
            'city_id' => 'sometimes|exists:cities,id',
            'business_type' => 'sometimes|in:salon,clinic,makeup_artist,hair_stylist',
            'category_id' => 'sometimes|exists:service_categories,id',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'min_rating' => 'sometimes|numeric|min:0|max:5',
            'home_service' => 'sometimes|boolean',
            'salon_service' => 'sometimes|boolean',
            'trending' => 'sometimes|boolean',
            'sort_by' => 'sometimes|in:price_asc,price_desc,rating,distance',
        ]);

        $searchTerm = $request->input('query');
        $cityId = $request->input('city_id');
        $businessType = $request->input('business_type');

        // Search providers
        $providersQuery = ServiceProvider::with(['user', 'city'])
            ->approved()
            ->active();

        // Text search (if query provided)
        if ($searchTerm) {
            $providersQuery->where(function ($q) use ($searchTerm) {
                $q->where('business_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // City filter
        if ($cityId) {
            $providersQuery->where('city_id', $cityId);
        }

        // Business type filter
        if ($businessType) {
            $providersQuery->where('business_type', $businessType);
        }

        // Category filter
        if ($request->has('category_id')) {
            $providersQuery->whereHas('services', function ($q) use ($request) {
                $q->where('category_id', $request->category_id);
            });
        }

        // Minimum rating filter
        if ($request->has('min_rating')) {
            $providersQuery->where('average_rating', '>=', $request->min_rating);
        }

        // Price range filter (based on provider's services)
        if ($request->has('min_price') || $request->has('max_price')) {
            $providersQuery->whereHas('services', function ($q) use ($request) {
                if ($request->has('min_price')) {
                    $q->where('price', '>=', $request->min_price);
                }
                if ($request->has('max_price')) {
                    $q->where('price', '<=', $request->max_price);
                }
            });
        }

        // Home service filter
        if ($request->has('home_service') && $request->home_service) {
            $providersQuery->whereHas('services', function ($q) {
                $q->where('available_at_home', true);
            });
        }

        // Salon service filter (services NOT available at home = available at salon/clinic location)
        if ($request->has('salon_service') && $request->salon_service) {
            $providersQuery->whereHas('services', function ($q) {
                $q->where(function($query) {
                    $query->where('available_at_home', false)
                          ->orWhereNull('available_at_home');
                });
            });
        }

        // Trending filter (providers with most bookings in last 30 days)
        if ($request->has('trending') && $request->trending) {
            $providersQuery->whereHas('bookings', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30))
                    ->whereIn('status', ['confirmed', 'completed']);
            })
            ->withCount(['bookings' => function ($q) {
                $q->where('created_at', '>=', now()->subDays(30))
                    ->whereIn('status', ['confirmed', 'completed']);
            }])
            ->orderByDesc('bookings_count');
        }

        // Sorting
        switch ($request->get('sort_by')) {
            case 'rating':
                $providersQuery->orderByDesc('average_rating');
                break;
            case 'price_asc':
                $providersQuery->withMin('services', 'price')
                    ->orderBy('services_min_price', 'asc');
                break;
            case 'price_desc':
                $providersQuery->withMax('services', 'price')
                    ->orderBy('services_max_price', 'desc');
                break;
            case 'distance':
                // Distance sorting requires coordinates
                if ($request->has(['latitude', 'longitude'])) {
                    $lat = $request->latitude;
                    $lng = $request->longitude;
                    $providersQuery->selectRaw("
                        *,
                        (6371 * acos(cos(radians(?)) * cos(radians(latitude)) *
                        cos(radians(longitude) - radians(?)) + sin(radians(?)) *
                        sin(radians(latitude)))) AS distance
                    ", [$lat, $lng, $lat])
                        ->orderBy('distance');
                } else {
                    $providersQuery->orderByDesc('average_rating');
                }
                break;
            default:
                $providersQuery->orderByDesc('average_rating');
        }

        // Default sorting if no trending or sort_by specified
        if (!$request->has('trending') && !$request->has('sort_by')) {
            $providersQuery->orderByDesc('is_featured')
                ->orderByDesc('average_rating');
        }

        $perPage = min($request->get('per_page', 20), 50);
        $providers = $providersQuery->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ProviderResource::collection($providers->items()),
            'query' => $searchTerm,
            'filters_applied' => [
                'city_id' => $cityId,
                'business_type' => $businessType,
                'category_id' => $request->category_id,
                'min_price' => $request->min_price,
                'max_price' => $request->max_price,
                'min_rating' => $request->min_rating,
                'home_service' => $request->home_service,
                'salon_service' => $request->salon_service,
                'trending' => $request->trending,
                'sort_by' => $request->sort_by,
            ],
            'pagination' => [
                'current_page' => $providers->currentPage(),
                'last_page' => $providers->lastPage(),
                'per_page' => $providers->perPage(),
                'total' => $providers->total(),
            ]
        ]);
    }

    /**
     * Get all services with advanced filtering
     */
    public function getAllServices(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'sometimes|exists:service_categories,id',
            'provider_id' => 'sometimes|exists:service_providers,id',
            'city_id' => 'sometimes|exists:cities,id',
            'min_price' => 'sometimes|numeric|min:0',
            'max_price' => 'sometimes|numeric|min:0',
            'available_at_home' => 'sometimes|boolean',
            'sort' => 'sometimes|in:price_low,price_high,duration,popular',
        ]);

        $query = Service::with(['provider.city', 'category'])
            ->where('is_active', true)
            ->whereHas('provider', function ($q) {
                $q->where('verification_status', 'approved')
                    ->where('is_active', true);
            });

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by provider
        if ($request->has('provider_id')) {
            $query->where('provider_id', $request->provider_id);
        }

        // Filter by city
        if ($request->has('city_id')) {
            $query->whereHas('provider', function ($q) use ($request) {
                $q->where('city_id', $request->city_id);
            });
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Filter by home service availability
        if ($request->has('available_at_home')) {
            $query->where('available_at_home', $request->available_at_home);
        }

        // Sorting
        switch ($request->get('sort', 'popular')) {
            case 'price_low':
                $query->orderBy('price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('price', 'desc');
                break;
            case 'duration':
                $query->orderBy('duration_minutes', 'asc');
                break;
            case 'popular':
                $query->withCount('bookingItems')
                    ->orderByDesc('booking_items_count');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $perPage = min($request->get('per_page', 20), 50);
        $services = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ServiceResource::collection($services->items()),
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'per_page' => $services->perPage(),
                'total' => $services->total(),
            ]
        ]);
    }

    /**
     * Search services with advanced query
     */
    public function searchServices(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2',
            'category_id' => 'sometimes|exists:service_categories,id',
            'city_id' => 'sometimes|exists:cities,id',
            'business_type' => 'sometimes|in:salon,clinic,makeup_artist,hair_stylist',
            'available_at_home' => 'sometimes|boolean',
        ]);

        $searchTerm = $request->query;

        $query = Service::with(['provider.city', 'category'])
            ->where('is_active', true)
            ->whereHas('provider', function ($q) {
                $q->where('verification_status', 'approved')
                    ->where('is_active', true);
            })
            ->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ILIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'ILIKE', "%{$searchTerm}%");
            });

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by city
        if ($request->has('city_id')) {
            $query->whereHas('provider', function ($q) use ($request) {
                $q->where('city_id', $request->city_id);
            });
        }

        // Filter by business type
        if ($request->has('business_type')) {
            $query->whereHas('provider', function ($q) use ($request) {
                $q->where('business_type', $request->business_type);
            });
        }

        // Filter by home availability
        if ($request->has('available_at_home')) {
            $query->where('available_at_home', $request->available_at_home);
        }

        $perPage = min($request->get('per_page', 20), 50);
        $services = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ServiceResource::collection($services->items()),
            'query' => $searchTerm,
            'filters_applied' => [
                'category_id' => $request->category_id,
                'city_id' => $request->city_id,
                'business_type' => $request->business_type,
                'available_at_home' => $request->available_at_home,
            ],
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'total' => $services->total(),
            ]
        ]);
    }

    /**
     * Get popular services by category
     */
    public function popularServicesByCategory(Request $request, int $categoryId): JsonResponse
    {
        $category = ServiceCategory::findOrFail($categoryId);

        $services = Service::with(['provider.city', 'category'])
            ->where('category_id', $categoryId)
            ->where('is_active', true)
            ->whereHas('provider', function ($q) {
                $q->where('verification_status', 'approved')
                    ->where('is_active', true);
            })
            ->withCount('bookingItems')
            ->orderByDesc('booking_items_count')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'category' => [
                'id' => $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
            ],
            'data' => ServiceResource::collection($services),
            'total' => $services->count()
        ]);
    }

    /**
     * Get service price range by category
     */
    public function categoryPriceRange(Request $request, int $categoryId): JsonResponse
    {
        $category = ServiceCategory::findOrFail($categoryId);

        $priceStats = Service::where('category_id', $categoryId)
            ->where('is_active', true)
            ->whereHas('provider', function ($q) {
                $q->where('verification_status', 'approved')
                    ->where('is_active', true);
            })
            ->selectRaw('
            MIN(price) as min_price,
            MAX(price) as max_price,
            AVG(price) as avg_price,
            COUNT(*) as total_services
        ')
            ->first();

        return response()->json([
            'success' => true,
            'category' => [
                'id' => $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
            ],
            'price_range' => [
                'min' => (float) $priceStats->min_price,
                'max' => (float) $priceStats->max_price,
                'average' => round((float) $priceStats->avg_price, 2),
                'total_services' => $priceStats->total_services,
            ]
        ]);
    }

    /**
     * Get services grouped by category
     */
    public function servicesGroupedByCategory(Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => 'sometimes|exists:cities,id',
            'provider_id' => 'sometimes|exists:service_providers,id',
        ]);

        $categories = ServiceCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $result = [];

        foreach ($categories as $category) {
            $servicesQuery = Service::with(['provider'])
                ->where('category_id', $category->id)
                ->where('is_active', true)
                ->whereHas('provider', function ($q) {
                    $q->where('verification_status', 'approved')
                        ->where('is_active', true);
                });

            // Apply filters
            if ($request->has('city_id')) {
                $servicesQuery->whereHas('provider', function ($q) use ($request) {
                    $q->where('city_id', $request->city_id);
                });
            }

            if ($request->has('provider_id')) {
                $servicesQuery->where('provider_id', $request->provider_id);
            }

            $services = $servicesQuery->limit(5)->get();

            if ($services->isNotEmpty()) {
                $result[] = [
                    'category' => [
                        'id' => $category->id,
                        'name_ar' => $category->name_ar,
                        'name_en' => $category->name_en,
                        'icon' => $category->icon,
                        'color' => $category->color,
                    ],
                    'services' => ServiceResource::collection($services),
                    'total_services' => $servicesQuery->count(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Get trending services (most booked in last 30 days)
     */
    public function trendingServices(Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => 'sometimes|exists:cities,id',
            'limit' => 'sometimes|integer|min:1|max:50',
        ]);

        $query = Service::with(['provider.city', 'category'])
            ->where('is_active', true)
            ->whereHas('provider', function ($q) {
                $q->where('verification_status', 'approved')
                    ->where('is_active', true);
            })
            ->whereHas('bookingItems.booking', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30))
                    ->whereIn('status', ['confirmed', 'completed']);
            });

        // Filter by city if provided
        if ($request->has('city_id')) {
            $query->whereHas('provider', function ($q) use ($request) {
                $q->where('city_id', $request->city_id);
            });
        }

        $limit = $request->get('limit', 10);

        $services = $query->withCount(['bookingItems' => function ($q) {
            $q->whereHas('booking', function ($bookingQuery) {
                $bookingQuery->where('created_at', '>=', now()->subDays(30))
                    ->whereIn('status', ['confirmed', 'completed']);
            });
        }])
            ->orderByDesc('booking_items_count')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => ServiceResource::collection($services),
            'period' => 'last_30_days',
            'total' => $services->count()
        ]);
    }

    /**
     * Get home service available services only
     */
    public function homeServices(Request $request): JsonResponse
    {
        $request->validate([
            'city_id' => 'sometimes|exists:cities,id',
            'category_id' => 'sometimes|exists:service_categories,id',
        ]);

        $query = Service::with(['provider.city', 'category'])
            ->where('is_active', true)
            ->where('available_at_home', true)
            ->whereHas('provider', function ($q) {
                $q->where('verification_status', 'approved')
                    ->where('is_active', true);
            });

        // Filter by city
        if ($request->has('city_id')) {
            $query->whereHas('provider', function ($q) use ($request) {
                $q->where('city_id', $request->city_id);
            });
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $perPage = min($request->get('per_page', 20), 50);
        $services = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ServiceResource::collection($services->items()),
            'pagination' => [
                'current_page' => $services->currentPage(),
                'last_page' => $services->lastPage(),
                'total' => $services->total(),
            ]
        ]);
    }
}
