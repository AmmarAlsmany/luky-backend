<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'booking_number' => $this->booking_number,
            'booking_date' => $this->booking_date->format('Y-m-d'),
            'start_time' => $this->start_time->format('H:i'),
            'end_time' => $this->end_time->format('H:i'),
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            
            'client' => $this->whenLoaded('client', function () {
                return [
                    'id' => $this->client->id,
                    'name' => $this->client->name,
                    'phone' => $this->client->phone,
                ];
            }),
            
            'provider' => $this->whenLoaded('provider', function () {
                return [
                    'id' => $this->provider->id,
                    'business_name' => $this->provider->business_name,
                    'business_type' => $this->provider->business_type,
                    'phone' => $this->provider->user->phone ?? null,
                    'address' => $this->provider->address,
                ];
            }),
            
            'services' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'service_name' => $item->service->name,
                        'quantity' => $item->quantity,
                        'location' => $item->service_location,
                        'unit_price' => (float) $item->unit_price,
                        'total_price' => (float) $item->total_price,
                    ];
                });
            }),
            
            'pricing' => [
                'subtotal' => (float) $this->subtotal,
                'tax_amount' => (float) $this->tax_amount,
                'discount_amount' => (float) $this->discount_amount,
                'total_amount' => (float) $this->total_amount,
            ],
            
            'client_address' => $this->client_address,
            'client_latitude' => $this->client_latitude,
            'client_longitude' => $this->client_longitude,
            'notes' => $this->notes,
            'cancellation_reason' => $this->cancellation_reason,
            'cancelled_by' => $this->cancelled_by,

            'confirmed_at' => $this->confirmed_at?->format('Y-m-d H:i:s'),
            'payment_deadline' => $this->payment_deadline?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}