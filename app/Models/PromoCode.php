<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use App\Models\Service;
use App\Models\ServiceProvider;

class PromoCode extends Model
{
    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'free_service_id',
        'min_booking_amount',
        'max_discount_amount',
        'usage_limit',
        'usage_limit_per_user',
        'used_count',
        'valid_from',
        'valid_until',
        'is_active',
        'applicable_to',
        'applicable_services',
        'applicable_categories',
        'created_by',
        'provider_id',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'min_booking_amount' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'valid_from' => 'date',
        'valid_until' => 'date',
        'is_active' => 'boolean',
        'applicable_services' => 'array',
        'applicable_categories' => 'array',
    ];

    // Relationships
    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function freeService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'free_service_id');
    }

    // Helper methods
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $today = Carbon::today();
        if ($today->lt($this->valid_from) || $today->gt($this->valid_until)) {
            return false;
        }

        // Check if usage limit reached
        if ($this->usage_limit && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    public function canBeUsedByUser(int $userId): bool
    {
        $userUsageCount = $this->usages()
            ->where('user_id', $userId)
            ->count();

        return $userUsageCount < $this->usage_limit_per_user;
    }

    public function isApplicableToService(int $serviceId): bool
    {
        // If no specific services, applicable to all
        if (empty($this->applicable_services)) {
            return true;
        }

        return in_array($serviceId, $this->applicable_services);
    }

    public function calculateDiscount(float $orderAmount): float
    {
        if ($this->discount_type === 'fixed' || $this->discount_type === 'fixed_amount') {
            return min($this->discount_value, $orderAmount);
        }

        if ($this->discount_type === 'free_service') {
            // Get the service price from the relationship
            if ($this->freeService) {
                return min($this->freeService->price, $orderAmount);
            }
            return 0;
        }

        // Percentage discount
        $discount = ($orderAmount * $this->discount_value) / 100;

        // Apply max discount cap if set
        if ($this->max_discount_amount) {
            $discount = min($discount, $this->max_discount_amount);
        }

        return round($discount, 2);
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
