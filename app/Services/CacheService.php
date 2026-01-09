<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CacheService
{
    /**
     * Cache durations in seconds
     */
    const CACHE_FOREVER = 86400 * 30; // 30 days
    const CACHE_DAY = 86400; // 24 hours
    const CACHE_HOUR = 3600; // 1 hour
    const CACHE_MINUTES_30 = 1800; // 30 minutes
    const CACHE_MINUTES_15 = 900; // 15 minutes
    const CACHE_MINUTES_5 = 300; // 5 minutes

    /**
     * Cache key prefixes
     */
    const PREFIX_SETTINGS = 'settings:';
    const PREFIX_CATEGORIES = 'categories:';
    const PREFIX_CITIES = 'cities:';
    const PREFIX_BANNERS = 'banners:';
    const PREFIX_PROVIDERS = 'providers:';
    const PREFIX_SERVICES = 'services:';

    /**
     * Get all service categories (DEPRECATED - Service Categories removed from system)
     * Returns empty collection for backward compatibility
     */
    public static function getServiceCategories(): mixed
    {
        // Service Categories have been removed from the system
        // Returning empty collection for backward compatibility
        return collect([]);
    }

    /**
     * Get all cities (cached with lock protection)
     */
    public static function getCities(): mixed
    {
        $key = self::PREFIX_CITIES . 'active';

        return self::rememberWithLock(
            $key,
            self::CACHE_DAY,
            fn() => \App\Models\City::where('is_active', true)
                ->orderBy('name_en')
                ->get()
        );
    }

    /**
     * Get app setting by key (cached)
     */
    public static function getAppSetting(string $key, $default = null): mixed
    {
        return Cache::remember(
            self::PREFIX_SETTINGS . $key,
            self::CACHE_HOUR,
            fn() => \App\Models\AppSetting::where('key', $key)->value('value') ?? $default
        );
    }

    /**
     * Get all app settings (cached)
     */
    public static function getAllAppSettings(): array
    {
        return Cache::remember(
            self::PREFIX_SETTINGS . 'all',
            self::CACHE_HOUR,
            fn() => \App\Models\AppSetting::all()
                ->mapWithKeys(function ($setting) {
                    $value = match($setting->type) {
                        'integer' => (int) $setting->value,
                        'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
                        'decimal', 'float' => (float) $setting->value,
                        'json' => json_decode($setting->value, true),
                        default => $setting->value,
                    };
                    return [$setting->key => $value];
                })
                ->toArray()
        );
    }

    /**
     * Get active banners for home (cached for 5 minutes)
     */
    public static function getHomeBanners(): mixed
    {
        return Cache::remember(
            self::PREFIX_BANNERS . 'home:active',
            self::CACHE_MINUTES_5,
            function () {
                $now = now();
                return \DB::table('banners')
                    ->where('is_active', true)
                    ->whereDate('start_date', '<=', $now)
                    ->whereDate('end_date', '>=', $now)
                    ->whereIn('display_location', ['home', 'all'])
                    ->orderBy('display_order', 'asc')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        );
    }

    /**
     * Get featured providers (cached for 15 minutes)
     */
    public static function getFeaturedProviders(int $cityId = null, int $limit = 10): mixed
    {
        $cacheKey = self::PREFIX_PROVIDERS . 'featured:' . ($cityId ?? 'all') . ':' . $limit;

        return Cache::remember(
            $cacheKey,
            self::CACHE_MINUTES_15,
            function () use ($cityId, $limit) {
                $query = \App\Models\ServiceProvider::with(['user', 'city'])
                    ->where('is_featured', true)
                    ->where('verification_status', 'approved')
                    ->where('is_active', true);

                if ($cityId) {
                    $query->where('city_id', $cityId);
                }

                return $query->orderByDesc('average_rating')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Get trending services (cached for 30 minutes)
     */
    public static function getTrendingServices(int $cityId = null, int $limit = 10): mixed
    {
        $cacheKey = self::PREFIX_SERVICES . 'trending:' . ($cityId ?? 'all') . ':' . $limit;

        return Cache::remember(
            $cacheKey,
            self::CACHE_MINUTES_30,
            function () use ($cityId, $limit) {
                $query = \App\Models\Service::with(['provider.city'])
                    ->where('is_active', true)
                    ->whereHas('provider', function ($q) {
                        $q->where('verification_status', 'approved')
                            ->where('is_active', true);
                    })
                    ->whereHas('bookingItems.booking', function ($q) {
                        $q->where('created_at', '>=', now()->subDays(30))
                            ->whereIn('status', ['confirmed', 'completed']);
                    });

                if ($cityId) {
                    $query->whereHas('provider', function ($q) use ($cityId) {
                        $q->where('city_id', $cityId);
                    });
                }

                return $query->withCount(['bookingItems' => function ($q) {
                    $q->whereHas('booking', function ($bookingQuery) {
                        $bookingQuery->where('created_at', '>=', now()->subDays(30))
                            ->whereIn('status', ['confirmed', 'completed']);
                    });
                }])
                    ->orderByDesc('booking_items_count')
                    ->limit($limit)
                    ->get();
            }
        );
    }

    /**
     * Clear all application caches
     * Note: Uses individual cache clearing instead of tags for compatibility
     * with all cache drivers (tags only work with Redis/Memcached)
     */
    public static function clearAll(): void
    {
        self::clearCategories();
        self::clearCities();
        self::clearSettings();
        self::clearBanners();
        self::clearProviders();
        self::clearServices();
    }

    /**
     * Clear categories cache
     */
    public static function clearCategories(): void
    {
        Cache::forget(self::PREFIX_CATEGORIES . 'active');
    }

    /**
     * Clear cities cache
     */
    public static function clearCities(): void
    {
        Cache::forget(self::PREFIX_CITIES . 'active');
    }

    /**
     * Clear settings cache
     */
    public static function clearSettings(): void
    {
        Cache::flush();
    }

    /**
     * Clear banners cache
     */
    public static function clearBanners(): void
    {
        Cache::forget(self::PREFIX_BANNERS . 'home:active');
    }

    /**
     * Clear provider caches
     */
    public static function clearProviders(): void
    {
        // Clear all provider-related cache keys
        $cities = \App\Models\City::pluck('id');
        foreach ($cities as $cityId) {
            Cache::forget(self::PREFIX_PROVIDERS . 'featured:' . $cityId . ':10');
        }
        Cache::forget(self::PREFIX_PROVIDERS . 'featured:all:10');
    }

    /**
     * Clear service caches
     */
    public static function clearServices(): void
    {
        // Clear all service-related cache keys
        $cities = \App\Models\City::pluck('id');
        foreach ($cities as $cityId) {
            Cache::forget(self::PREFIX_SERVICES . 'trending:' . $cityId . ':10');
        }
        Cache::forget(self::PREFIX_SERVICES . 'trending:all:10');
    }

    /**
     * Cache helper with lock protection (prevents cache stampede)
     *
     * @param string $key Cache key
     * @param int $ttl Time to live in seconds
     * @param callable $callback Function to execute if cache miss
     * @return mixed
     */
    protected static function rememberWithLock(string $key, int $ttl, callable $callback): mixed
    {
        // Try to get from cache first
        $value = Cache::get($key);

        if ($value !== null) {
            return $value;
        }

        // Use lock to prevent cache stampede
        $lock = Cache::lock($key . ':lock', 10);

        try {
            // Wait up to 5 seconds to acquire lock
            $lock->block(5);

            // Double-check cache after acquiring lock
            $value = Cache::get($key);

            if ($value === null) {
                // Execute callback to get fresh data
                $value = $callback();

                // Store in cache
                Cache::put($key, $value, $ttl);
            }

            return $value;
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            // Couldn't acquire lock in time, execute callback directly
            return $callback();
        } finally {
            $lock?->release();
        }
    }

    /**
     * Warm up all caches (useful after deployment)
     */
    public static function warmUp(): array
    {
        $results = [];

        try {
            self::getServiceCategories();
            $results[] = 'Service categories cached';
        } catch (\Exception $e) {
            $results[] = 'Failed to cache categories: ' . $e->getMessage();
        }

        try {
            self::getCities();
            $results[] = 'Cities cached';
        } catch (\Exception $e) {
            $results[] = 'Failed to cache cities: ' . $e->getMessage();
        }

        try {
            self::getAllAppSettings();
            $results[] = 'App settings cached';
        } catch (\Exception $e) {
            $results[] = 'Failed to cache settings: ' . $e->getMessage();
        }

        try {
            self::getHomeBanners();
            $results[] = 'Banners cached';
        } catch (\Exception $e) {
            $results[] = 'Failed to cache banners: ' . $e->getMessage();
        }

        try {
            self::getFeaturedProviders();
            $results[] = 'Featured providers cached';
        } catch (\Exception $e) {
            $results[] = 'Failed to cache providers: ' . $e->getMessage();
        }

        try {
            self::getTrendingServices();
            $results[] = 'Trending services cached';
        } catch (\Exception $e) {
            $results[] = 'Failed to cache services: ' . $e->getMessage();
        }

        return $results;
    }
}
