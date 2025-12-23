<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ServiceProviderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'business_name' => $this->business_name,
            'business_type' => $this->business_type,
            'description' => $this->description,
            'verification_status' => $this->verification_status,
            'is_verified' => $this->verification_status === 'approved',
            'is_pending' => $this->verification_status === 'pending',
            'is_rejected' => $this->verification_status === 'rejected',
            'rejection_reason' => $this->when($this->verification_status === 'rejected', $this->rejection_reason),
            
            'average_rating' => (float) $this->average_rating,
            'total_reviews' => $this->total_reviews,
            'is_featured' => $this->is_featured,
            'is_active' => $this->is_active,
            
            'city' => $this->whenLoaded('city', function () {
                return new CityResource($this->city);
            }),
            'address' => $this->address,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            
            'working_hours' => $this->working_hours,
            'off_days' => $this->off_days,

            'contact' => [
                'phone' => $this->user->phone ?? null,
            ],

            'bank_info' => [
                'account_title' => $this->account_title,
                'account_number' => $this->account_number,
                'iban' => $this->iban,
                'currency' => $this->currency,
            ],
            
            'services' => $this->whenLoaded('services', function () {
                return ServiceResource::collection($this->services);
            }),
            'services_count' => $this->whenLoaded('services', fn() => $this->services->count(), 0),
            
            'documents' => $this->whenLoaded('documents', function () {
                return $this->documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'type' => $doc->document_type,
                        'verification_status' => $doc->verification_status,
                        'uploaded_at' => $doc->created_at->format('Y-m-d H:i:s'),
                    ];
                });
            }),
            
            'logo_url' => $this->getFirstMediaUrl('logo'),
            'gallery' => $this->getMedia('gallery')->map(fn($media) => $media->getUrl()),
            
            'verified_at' => $this->verified_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}