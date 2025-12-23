<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ServiceCategory extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'icon',
        'color',
        'image_url',
        'sort_order',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Relationships
    public function services()
    {
        return $this->hasMany(Service::class, 'category_id');
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
            ->singleFile() // Only one icon per category
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/svg+xml']);
    }

    /**
     * Register media conversions for automatic icon optimization
     * Optimizes category icons to reduce size and improve loading speed
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Optimized version - 200x200px for category display
        // Perfect size for category icons in grids
        $this->addMediaConversion('optimized')
            ->width(200)
            ->height(200)
            ->sharpen(10)
            ->quality(90)
            ->format('png') // PNG for better quality with transparency
            ->performOnCollections('category_icon')
            ->nonQueued(); // Process immediately

        // Thumbnail version - 80x80px for small displays
        $this->addMediaConversion('thumb')
            ->width(80)
            ->height(80)
            ->sharpen(10)
            ->quality(85)
            ->format('png')
            ->performOnCollections('category_icon')
            ->nonQueued();
    }

    /**
     * Get category icon URL
     * Returns optimized version if available for better performance
     */
    public function getIconUrlAttribute()
    {
        // First check if using Spatie Media Library
        $media = $this->getFirstMedia('category_icon');
        if ($media) {
            // Return optimized version if available, otherwise original
            return $media->hasGeneratedConversion('optimized')
                ? $media->getUrl('optimized')
                : $media->getUrl();
        }

        // Fallback to legacy image_url field for backwards compatibility
        if ($this->image_url) {
            return $this->image_url;
        }

        // No icon available
        return null;
    }

    /**
     * Get business type from category name
     * Maps category to business_type for backward compatibility
     */
    public function getBusinessType()
    {
        $mapping = [
            'salon' => 'salon',
            'clinic' => 'clinic',
            'makeup artist' => 'makeup_artist',
            'hair stylist' => 'hair_stylist',
        ];

        $categoryName = strtolower($this->name_en ?? '');

        foreach ($mapping as $key => $value) {
            if (str_contains($categoryName, $key)) {
                return $value;
            }
        }

        // Default to salon if no match
        return 'salon';
    }
}

