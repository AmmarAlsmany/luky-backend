<?php

namespace App\Observers;

use App\Models\ServiceProvider;
use App\Services\NotificationService;

class ServiceProviderObserver
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Handle the ServiceProvider "created" event.
     */
    public function created(ServiceProvider $provider): void
    {
        // Notify admins about new provider registration
        $this->notificationService->sendToAdmins(
            'provider_registration',
            'New Provider Registration',
            "New provider '{$provider->business_name}' has registered and is pending approval",
            [
                'provider_id' => $provider->id,
                'business_name' => $provider->business_name,
                'user_id' => $provider->user_id,
            ]
        );

        // Clear provider caches
        \App\Services\CacheService::clearProviders();
    }

    /**
     * Handle the ServiceProvider "updated" event.
     */
    public function updated(ServiceProvider $provider): void
    {
        // Check if approval status changed
        if ($provider->isDirty('approval_status')) {
            if ($provider->approval_status === 'approved') {
                $this->notificationService->sendToAdmins(
                    'provider_approved',
                    'Provider Approved',
                    "Provider '{$provider->business_name}' has been approved",
                    [
                        'provider_id' => $provider->id,
                        'business_name' => $provider->business_name,
                    ]
                );
            } elseif ($provider->approval_status === 'rejected') {
                $this->notificationService->sendToAdmins(
                    'provider_rejected',
                    'Provider Rejected',
                    "Provider '{$provider->business_name}' has been rejected",
                    [
                        'provider_id' => $provider->id,
                        'business_name' => $provider->business_name,
                    ]
                );
            }
        }

        // Clear provider caches if important fields changed
        if ($provider->isDirty(['is_featured', 'average_rating', 'is_active', 'verification_status', 'city_id'])) {
            \App\Services\CacheService::clearProviders();
        }
    }

    /**
     * Handle the ServiceProvider "deleted" event.
     */
    public function deleted(ServiceProvider $provider): void
    {
        \App\Services\CacheService::clearProviders();
    }

    /**
     * Handle the ServiceProvider "restored" event.
     */
    public function restored(ServiceProvider $provider): void
    {
        \App\Services\CacheService::clearProviders();
    }
}
