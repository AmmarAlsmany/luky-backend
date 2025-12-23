<?php

namespace App\Observers;

use App\Models\ServiceCategory;
use App\Services\CacheService;

class ServiceCategoryObserver
{
    /**
     * Handle the ServiceCategory "created" event.
     */
    public function created(ServiceCategory $serviceCategory): void
    {
        CacheService::clearCategories();
    }

    /**
     * Handle the ServiceCategory "updated" event.
     */
    public function updated(ServiceCategory $serviceCategory): void
    {
        CacheService::clearCategories();
    }

    /**
     * Handle the ServiceCategory "deleted" event.
     */
    public function deleted(ServiceCategory $serviceCategory): void
    {
        CacheService::clearCategories();
    }

    /**
     * Handle the ServiceCategory "restored" event.
     */
    public function restored(ServiceCategory $serviceCategory): void
    {
        CacheService::clearCategories();
    }
}
