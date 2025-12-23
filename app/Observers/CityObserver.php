<?php

namespace App\Observers;

use App\Models\City;
use App\Services\CacheService;

class CityObserver
{
    /**
     * Handle the City "created" event.
     */
    public function created(City $city): void
    {
        CacheService::clearCities();
    }

    /**
     * Handle the City "updated" event.
     */
    public function updated(City $city): void
    {
        CacheService::clearCities();
    }

    /**
     * Handle the City "deleted" event.
     */
    public function deleted(City $city): void
    {
        CacheService::clearCities();
    }

    /**
     * Handle the City "restored" event.
     */
    public function restored(City $city): void
    {
        CacheService::clearCities();
    }
}
