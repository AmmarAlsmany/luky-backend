<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProviderCategory extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'icon',
        'color',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function providers()
    {
        return $this->hasMany(ServiceProvider::class, 'provider_category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Register media collections for category icon
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('category_icon')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/svg+xml']);
    }

    /**
     * Register media conversions for automatic icon optimization
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->sharpen(10)
            ->optimize();

        $this->addMediaConversion('icon')
            ->width(200)
            ->height(200)
            ->sharpen(10)
            ->optimize();
    }

    /**
     * Get icon URL
     */
    public function getIconUrlAttribute()
    {
        $media = $this->getFirstMedia('category_icon');
        return $media ? $media->getUrl('icon') : null;
    }
}
