<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProviderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'business_type' => $this->business_type,
            'business_type_display' => $this->getBusinessTypeDisplay(),

            // Provider Category - NEW: Show actual category information
            'provider_category_id' => $this->provider_category_id,
            'provider_category' => $this->whenLoaded('providerCategory', function () {
                return [
                    'id' => $this->providerCategory->id,
                    'name_ar' => $this->providerCategory->name_ar,
                    'name_en' => $this->providerCategory->name_en,
                    'description_ar' => $this->providerCategory->description_ar,
                    'description_en' => $this->providerCategory->description_en,
                    'icon' => $this->providerCategory->icon,
                    'color' => $this->providerCategory->color,
                ];
            }),

            'description' => $this->description,
            'average_rating' => (float) $this->average_rating,
            'total_reviews' => $this->total_reviews,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,

            // Location information
            'city' => $this->whenLoaded('city', function () {
                return [
                    'id' => $this->city->id,
                    'name_ar' => $this->city->name_ar,
                    'name_en' => $this->city->name_en,
                ];
            }),
            'address' => $this->address,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            'distance' => $this->when(isset($this->distance), function () {
                return round((float) $this->distance, 2);
            }),
            
            // Contact information
            'contact_info' => [
                'phone' => $this->user->phone ?? null,
            ],
            
            // Working hours
            'working_hours' => $this->working_hours,
            'off_days' => $this->off_days,
            'is_open_now' => $this->isOpenNow(),
            
            // Services
            'services' => $this->whenLoaded('services', function () {
                return $this->services->map(function ($service) {
                    return [
                        'id' => $service->id,
                        'name_ar' => $service->name_ar ?? $service->name,
                        'name_en' => $service->name_en ?? $service->name,
                        'description_ar' => $service->description_ar,
                        'description_en' => $service->description_en,
                        'price' => (float) $service->price,
                        'available_at_home' => $service->available_at_home ?? false,
                        'home_service_price' => $service->home_service_price ? (float) $service->home_service_price : null,
                        'duration_minutes' => $service->duration_minutes,

                        // Old category (legacy support)
                        'category_id' => $service->category_id,
                        'category_name_ar' => $service->category?->name_ar,
                        'category_name_en' => $service->category?->name_en,

                        // Provider Service Category (NEW - custom categories)
                        'provider_service_category_id' => $service->provider_service_category_id,
                        'provider_service_category_name_ar' => $service->providerServiceCategory?->name_ar,
                        'provider_service_category_name_en' => $service->providerServiceCategory?->name_en,

                        'is_active' => $service->is_active,
                        'is_featured' => $service->is_featured ?? false,
                        'average_rating' => $service->average_rating ? (float) $service->average_rating : null,
                        'total_bookings' => $service->total_bookings ?? 0,
                        'image_url' => $service->image_url,
                        'gallery' => $service->gallery ?? [],
                    ];
                });
            }),
            'services_count' => $this->services()->count(),

            // Media - Use model accessors for proper default handling
            'logo_url' => $this->logo_url,
            'building_image_url' => $this->building_image_url,

            // Verification
            'verification_status' => $this->verification_status,
            'is_verified' => $this->verification_status === 'approved',
            'is_pending' => $this->verification_status === 'pending',
            'is_rejected' => $this->verification_status === 'rejected',
            'rejection_reason' => $this->when($this->verification_status === 'rejected', $this->rejection_reason),
            'verified_at' => $this->verified_at?->format('Y-m-d H:i:s'),
            
            'created_at' => $this->created_at->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get business type display name
     */
    protected function getBusinessTypeDisplay()
    {
        $types = [
            'salon' => app()->getLocale() === 'ar' ? 'صالون' : 'Salon',
            'clinic' => app()->getLocale() === 'ar' ? 'عيادة' : 'Clinic',
            'makeup_artist' => app()->getLocale() === 'ar' ? 'ميك أب آرتست' : 'Makeup Artist',
            'hair_stylist' => app()->getLocale() === 'ar' ? 'مصففة شعر' : 'Hair Stylist',
        ];
        
        return $types[$this->business_type] ?? $this->business_type;
    }
    
    /**
     * Check if provider is currently open
     */
    protected function isOpenNow()
    {
        if (!$this->working_hours) {
            return false;
        }
        
        $now = now();
        $currentDay = strtolower($now->format('l')); // monday, tuesday, etc.
        $currentTime = $now->format('H:i');
        
        // Check if today is an off day
        if ($this->off_days && in_array($currentDay, $this->off_days)) {
            return false;
        }
        
        // Check working hours for today
        if (isset($this->working_hours[$currentDay])) {
            $hours = $this->working_hours[$currentDay];
            // Check if start and end times are set
            if (isset($hours['start']) && isset($hours['end'])) {
                return $currentTime >= $hours['start'] && $currentTime <= $hours['end'];
            }
        }

        return false;
    }
}