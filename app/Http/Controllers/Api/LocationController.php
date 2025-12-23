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