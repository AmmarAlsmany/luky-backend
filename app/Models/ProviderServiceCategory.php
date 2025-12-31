<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderServiceCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'color',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'provider_id' => 'integer',
    ];

    protected $appends = [
        'service_count'
    ];

    // Relationships
    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'provider_service_category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public function scopeForProvider($query, $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    // Accessor for service_count
    public function getServiceCountAttribute()
    {
        return $this->services()->count();
    }

    /**
     * Check if category can be deleted (has no services)
     */
    public function canDelete(): bool
    {
        return $this->services()->count() === 0;
    }

    /**
     * Get next sort order for provider
     */
    public static function getNextSortOrder($providerId): int
    {
        return self::where('provider_id', $providerId)->max('sort_order') + 1;
    }
}
