<?php
// app/Http/Resources/UserResource.php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'age' => $this->date_of_birth ? $this->date_of_birth->age : null,
            'gender' => $this->gender,
            'profile_image_url' => $this->avatar_url,

            // City information
            'city' => $this->whenLoaded('city', function () {
                return [
                    'id' => $this->city->id,
                    'name_ar' => $this->city->name_ar,
                    'name_en' => $this->city->name_en,
                ];
            }),
            'city_id' => $this->city_id,
            
            // Location data
            'address' => $this->address,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            
            // Account status
            'is_active' => $this->is_active,
            'phone_verified' => !is_null($this->phone_verified_at),
            'phone_verified_at' => $this->phone_verified_at?->format('Y-m-d H:i:s'),
            'last_login_at' => $this->last_login_at?->format('Y-m-d H:i:s'),
            
            // Provider profile if exists
            'provider_profile' => $this->whenLoaded('providerProfile', function () {
                return [
                    'id' => $this->providerProfile->id,
                    'business_name' => $this->providerProfile->business_name,
                    'business_type' => $this->providerProfile->business_type,
                    'verification_status' => $this->providerProfile->verification_status,
                    'is_verified' => $this->providerProfile->verification_status === 'approved',
                    'is_pending' => $this->providerProfile->verification_status === 'pending',
                    'is_rejected' => $this->providerProfile->verification_status === 'rejected',
                    'rejection_reason' => $this->providerProfile->verification_status === 'rejected' ? $this->providerProfile->rejection_reason : null,
                    'address' => $this->providerProfile->address,
                    'average_rating' => (float) $this->providerProfile->average_rating,
                    'total_reviews' => $this->providerProfile->total_reviews,
                    'is_featured' => $this->providerProfile->is_featured,
                ];
            }),
            
            // Timestamps
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}