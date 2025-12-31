<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Resources\CityResource;

class LocationController extends Controller
{
    /**
     * Get all active cities (cached)
     */
    public function cities(Request $request): JsonResponse
    {
        $cities = \App\Services\CacheService::getCities();

        return response()->json([
            'success' => true,
            'data' => CityResource::collection($cities),
            'total' => $cities->count()
        ]);
    }

    /**
     * Get city by ID
     */
    public function cityById(int $id): JsonResponse
    {
        $city = City::active()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new CityResource($city)
        ]);
    }

    /**
     * Search cities by name
     */
    public function searchCities(Request $request): JsonResponse
    {
        $request->validate([
            'query' => 'required|string|min:2'
        ]);

        $searchTerm = $request->query;

        $cities = City::active()
            ->where(function ($q) use ($searchTerm) {
                $q->where('name_ar', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('name_en', 'LIKE', "%{$searchTerm}%");
            })
            ->orderBy('name_en')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => CityResource::collection($cities),
            'total' => $cities->count()
        ]);
    }

    /**
     * Get nearest city from coordinates
     * This helps automatically determine city_id from map selection
     */
    public function getCityFromCoordinates(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180'
        ]);

        $latitude = $request->latitude;
        $longitude = $request->longitude;

        // Major Saudi cities with approximate coordinates
        $cityCoordinates = [
            1 => ['name' => 'Riyadh', 'lat' => 24.7136, 'lng' => 46.6753],
            2 => ['name' => 'Jeddah', 'lat' => 21.4858, 'lng' => 39.1925],
            3 => ['name' => 'Mecca', 'lat' => 21.3891, 'lng' => 39.8579],
            4 => ['name' => 'Medina', 'lat' => 24.5247, 'lng' => 39.5692],
            5 => ['name' => 'Dammam', 'lat' => 26.4207, 'lng' => 50.0888],
            6 => ['name' => 'Khobar', 'lat' => 26.2172, 'lng' => 50.1971],
            7 => ['name' => 'Dhahran', 'lat' => 26.2361, 'lng' => 50.0393],
            8 => ['name' => 'Taif', 'lat' => 21.2703, 'lng' => 40.4158],
            9 => ['name' => 'Buraidah', 'lat' => 26.3260, 'lng' => 43.9750],
            10 => ['name' => 'Tabuk', 'lat' => 28.3838, 'lng' => 36.5550],
            11 => ['name' => 'Khamis Mushait', 'lat' => 18.2970, 'lng' => 42.7357],
            12 => ['name' => 'Hail', 'lat' => 27.5114, 'lng' => 41.6900],
            13 => ['name' => 'Hofuf', 'lat' => 25.3547, 'lng' => 49.5856],
            14 => ['name' => 'Jubail', 'lat' => 27.0174, 'lng' => 49.6584],
            15 => ['name' => 'Hafr Al-Batin', 'lat' => 28.4328, 'lng' => 45.9605],
            16 => ['name' => 'Yanbu', 'lat' => 24.0896, 'lng' => 38.0617],
            17 => ['name' => 'Abha', 'lat' => 18.2164, 'lng' => 42.5053],
            18 => ['name' => 'Arar', 'lat' => 30.9753, 'lng' => 41.0381],
            19 => ['name' => 'Sakaka', 'lat' => 29.9697, 'lng' => 40.2064],
            20 => ['name' => 'Jizan', 'lat' => 16.8892, 'lng' => 42.5511],
            21 => ['name' => 'Al-Qatif', 'lat' => 26.5196, 'lng' => 50.0088],
            22 => ['name' => 'Najran', 'lat' => 17.4917, 'lng' => 44.1277],
            23 => ['name' => 'Al-Kharj', 'lat' => 24.1556, 'lng' => 47.3118],
            24 => ['name' => 'Al-Ahsa', 'lat' => 25.4294, 'lng' => 49.6190],
            25 => ['name' => 'Qassim', 'lat' => 26.3260, 'lng' => 43.9750],
        ];

        // Find nearest city using Haversine formula
        $nearestCityId = 1; // Default to Riyadh
        $minDistance = PHP_FLOAT_MAX;

        foreach ($cityCoordinates as $cityId => $coords) {
            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                $coords['lat'],
                $coords['lng']
            );

            if ($distance < $minDistance) {
                $minDistance = $distance;
                $nearestCityId = $cityId;
            }
        }

        // Get the city from database
        $city = City::find($nearestCityId);

        if (!$city) {
            // Fallback to Riyadh if city not found
            $city = City::find(1);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'city' => new CityResource($city),
                'distance_km' => round($minDistance, 2),
            ]
        ]);
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * Returns distance in kilometers
     */
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Get app settings for mobile configuration
     */
    public function appSettings(): JsonResponse
    {
        // Basic app configuration that mobile apps might need
        $settings = [
            'app_version' => config('app.version', '1.0.0'),
            'min_supported_version' => '1.0.0',
            'maintenance_mode' => false,
            'default_currency' => 'SAR',
            'default_language' => 'ar',
            'supported_languages' => ['ar', 'en'],
            'contact_info' => [
                'support_email' => 'support@luky.sa',
                'support_phone' => '+966800000000',
                'whatsapp' => '+966500000000'
            ],
            'booking_settings' => [
                'min_advance_booking_hours' => 2,
                'max_advance_booking_days' => 30,
                'cancellation_hours' => 24,
                'otp_expiry_minutes' => 10
            ],
            'payment_settings' => [
                'payment_timeout_minutes' => (int) \App\Services\CacheService::getAppSetting('payment_timeout_minutes', 10),
            ],
            'social_media' => [
                'instagram' => 'https://instagram.com/luky.sa',
                'twitter' => 'https://twitter.com/luky_sa',
                'tiktok' => 'https://tiktok.com/@luky.sa'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    /**
     * Get banners for home screen (cached for 5 minutes)
     */
    public function banners(): JsonResponse
    {
        $banners = \App\Services\CacheService::getHomeBanners();

        $bannerData = $banners->map(function ($banner) {
            return [
                'id' => $banner->id,
                'title' => $banner->title,
                'provider_name' => $banner->provider_name,
                'offer_text' => $banner->offer_text,
                'image_url' => $banner->image_url ? url('storage/' . $banner->image_url) : null,
                'link_url' => $banner->link_url,
                'start_date' => $banner->start_date,
                'end_date' => $banner->end_date,
            ];
        });

        // Increment impression count asynchronously (don't block response)
        if ($banners->isNotEmpty()) {
            $bannerIds = $banners->pluck('id')->toArray();
            dispatch(function () use ($bannerIds) {
                \DB::table('banners')
                    ->whereIn('id', $bannerIds)
                    ->increment('impression_count');
            })->afterResponse();
        }

        return response()->json([
            'success' => true,
            'data' => $bannerData,
            'total' => $banners->count()
        ]);
    }
}