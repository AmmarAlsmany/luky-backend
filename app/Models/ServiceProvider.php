<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ServiceProvider extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

    protected $fillable = [
        'user_id',
        'business_name',
        'business_type',
        'description',
        'license_number',
        'commercial_register',
        'municipal_license',
        'verification_status',
        'rejection_reason',
        'working_hours',
        'off_days',
        'average_rating',
        'total_reviews',
        'commission_rate',
        'is_featured',
        'is_active',
        'city_id',
        'latitude',
        'longitude',
        'address',
        'verified_at',
        'account_title',
        'account_number',
        'iban',
        'currency'
    ];

    protected $casts = [
        'working_hours' => 'array',
        'off_days' => 'array',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'average_rating' => 'decimal:2',
        'total_reviews' => 'integer',
        'commission_rate' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'verified_at' => 'datetime',
    ];

    protected $appends = [
        'logo_url',
        'building_image_url',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'provider_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'provider_id');
    }

    // public function availability()
    // {
    //     return $this->hasMany(ProviderAvailability::class, 'provider_id');
    // }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'provider_id');
    }

    public function documents()
    {
        return $this->hasMany(ProviderDocument::class, 'provider_id');
    }

    /**
     * Get contracts for this provider
     */
    public function contracts()
    {
        return $this->hasMany(ProviderContract::class, 'provider_id');
    }

    /**
     * Get conversations for this provider
     */
    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'provider_id');
    }

    /**
     * Get payment settings for this provider
     */
    public function paymentSettings()
    {
        return $this->hasOne(ProviderPaymentSetting::class, 'provider_id');
    }

    // public function favoriteBy()
    // {
    //     return $this->hasMany(UserFavorite::class, 'provider_id');
    // }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('verification_status', 'approved');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeByBusinessType($query, $type)
    {
        return $query->where('business_type', $type);
    }

    // Media Collections
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png']);

        $this->addMediaCollection('logo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png']);

        $this->addMediaCollection('building_image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png']);
    }

    /**
     * Register media conversions for automatic image optimization
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Logo - Optimized at 400x400 (perfect for avatars)
        $this->addMediaConversion('optimized')
            ->width(400)
            ->height(400)
            ->sharpen(10)
            ->quality(85)
            ->format('jpg')
            ->performOnCollections('logo')
            ->nonQueued();

        // Building image - Max 1200px
        $this->addMediaConversion('optimized')
            ->width(1200)
            ->height(800)
            ->sharpen(10)
            ->quality(85)
            ->format('jpg')
            ->performOnCollections('building_image')
            ->nonQueued();

        // Gallery - Optimized version
        $this->addMediaConversion('optimized')
            ->width(1200)
            ->height(1200)
            ->sharpen(10)
            ->quality(85)
            ->format('jpg')
            ->performOnCollections('gallery')
            ->nonQueued();

        // Gallery - Thumbnail
        $this->addMediaConversion('thumb')
            ->width(400)
            ->height(400)
            ->sharpen(10)
            ->quality(80)
            ->format('jpg')
            ->performOnCollections('gallery')
            ->nonQueued();
    }

    /**
     * Get logo URL with default business avatar
     * Returns optimized version for better performance
     */
    public function getLogoUrlAttribute()
    {
        // Check if provider has uploaded logo
        $logo = $this->getFirstMedia('logo');
        if ($logo) {
            // Return optimized version if available
            return $logo->hasGeneratedConversion('optimized')
                ? $logo->getUrl('optimized')
                : $logo->getUrl();
        }

        // Return null to let mobile app handle default image
        return null;
    }

    /**
     * Get building image URL
     */
    public function getBuildingImageUrlAttribute()
    {
        $buildingImage = $this->getFirstMedia('building_image');
        if ($buildingImage) {
            return $buildingImage->getUrl();
        }

        return null;
    }

    /**
     * Get pending profile changes for this provider
     */
    public function pendingChanges()
    {
        return $this->hasMany(ProviderPendingChange::class, 'provider_id');
    }
}
