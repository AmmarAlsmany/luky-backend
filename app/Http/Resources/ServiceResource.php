<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'description' => $this->description,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'price' => (float) $this->price,
            'price_display' => number_format($this->price, 2) . ' SAR',
            'available_at_home' => $this->available_at_home,
            'location_type' => $this->available_at_home ? 'both' : 'salon', // For mobile app compatibility
            'home_service_price' => $this->home_service_price ? (float) $this->home_service_price : null,
            'home_service_price_display' => $this->home_service_price ? number_format($this->home_service_price, 2) . ' SAR' : null,
            'duration_minutes' => $this->duration_minutes,
            'duration_display' => $this->getDurationDisplay(),
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured ?? false,
            'sort_order' => $this->sort_order,
            'average_rating' => $this->average_rating ? (float) $this->average_rating : null,
            'total_bookings' => $this->total_bookings ?? 0,

            // Media fields
            'image_url' => $this->image_url,
            'gallery' => $this->gallery ?? [],

            // Category info
            'category_id' => $this->category_id,
            'provider_service_category_id' => $this->provider_service_category_id,
            'category' => $this->whenLoaded('category', function () {
                return new ServiceCategoryResource($this->category);
            }),

            'provider' => $this->whenLoaded('provider', function () {
                return [
                    'id' => $this->provider->id,
                    'business_name' => $this->provider->business_name,
                    'average_rating' => (float) $this->provider->average_rating,
                ];
            }),

            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    protected function getDurationDisplay()
    {
        $hours = floor($this->duration_minutes / 60);
        $minutes = $this->duration_minutes % 60;

        if ($hours > 0 && $minutes > 0) {
            return "{$hours}h {$minutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$minutes}m";
        }
    }
}
