<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Service extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'provider_id',
        'category_id',
        'name',
        'name_ar',
        'name_en',
        'description',
        'description_ar',
        'description_en',
        'price',
        'available_at_home',
        'home_service_price',
        'duration_minutes',
        'is_active',
        'is_featured',
        'average_rating',
        'total_bookings',
        'image_url',
        'gallery',
        'sort_order'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'home_service_price' => 'decimal:2',
        'available_at_home' => 'boolean',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'duration_minutes' => 'integer',
        'average_rating' => 'decimal:2',
        'total_bookings' => 'integer',
        'gallery' => 'array',  // Cast JSON to array
    ];

    protected $appends = [
        'images',
        'thumbs',  // Add thumbnail URLs to API responses
    ];

    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function bookingItems()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function getPriceForLocation(string $location): float
    {
        if ($location === 'home' && $this->available_at_home) {
            return (float) $this->home_service_price;
        }
        return (float) $this->price;
    }

    /**
     * Register media collections for service images
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('service_images')
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg']);
    }

    /**
     * Register media conversions for automatic image optimization
     * This creates optimized versions when images are uploaded
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Optimized version - Maximum 1200px, compressed to 85% quality
        // This reduces file size by 80-95% while maintaining good quality
        $this->addMediaConversion('optimized')
            ->width(1200)
            ->height(1200)
            ->sharpen(10)
            ->quality(85)
            ->format('jpg')
            ->performOnCollections('service_images')
            ->nonQueued(); // Process immediately

        // Thumbnail version - 400px for list views
        // Super fast loading for service cards
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(10)
            ->quality(80)
            ->format('jpg')
            ->performOnCollections('service_images')
            ->nonQueued();
    }

    /**
     * Get service images URLs
     * Returns optimized versions by default for better performance
     */
    public function getImagesAttribute()
    {
        return $this->getMedia('service_images')->map(function ($media) {
            // Return optimized version if available, otherwise original
            return $media->hasGeneratedConversion('optimized')
                ? $media->getUrl('optimized')
                : $media->getUrl();
        })->toArray();
    }

    /**
     * Get thumbnail URLs for list views
     * Much faster loading than full images
     */
    public function getThumbsAttribute()
    {
        return $this->getMedia('service_images')->map(function ($media) {
            return $media->hasGeneratedConversion('thumb')
                ? $media->getUrl('thumb')
                : $media->getUrl();
        })->toArray();
    }

    /**
     * Get first service image URL (for backward compatibility)
     */
    public function getImageUrlAttribute()
    {
        $firstImage = $this->getFirstMedia('service_images');
        if ($firstImage) {
            return $firstImage->getUrl();
        }
        return null;
    }

    /**
     * Get gallery images (for backward compatibility)
     */
    public function getGalleryAttribute()
    {
        return $this->images;
    }
}
