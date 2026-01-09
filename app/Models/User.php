<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Services\PhoneNumberService;
use App\Models\Review;
use App\Models\ServiceCategory;

class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'user_type',
        'date_of_birth',
        'gender',
        'city_id',
        'latitude',
        'longitude',
        'address',
        'is_active',
        'phone_verified_at',
        'email_verified_at',
        'last_login_at',
        'status',
        'created_by',
        'avatar',
        'wallet_balance',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'date_of_birth' => 'date',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function providerProfile()
    {
        return $this->hasOne(ServiceProvider::class);
    }

    /**
     * Alias for providerProfile
     */
    public function serviceProvider()
    {
        return $this->hasOne(ServiceProvider::class);
    }

    public function clientBookings()
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    /**
     * Alias for clientBookings (for admin queries)
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    /**
     * Get conversations where user is the client
     */
    public function clientConversations()
    {
        return $this->hasMany(Conversation::class, 'client_id');
    }

    /**
     * Get all messages sent by this user
     */
    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function favorites()
    {
        return $this->hasMany(UserFavorite::class);
    }

    public function favoriteProviders()
    {
        return $this->belongsToMany(ServiceProvider::class, 'user_favorites', 'user_id', 'provider_id')
            ->withTimestamps();
    }

    /**
     * Get reviews where this user is the provider
     * For users with user_type = 'provider'
     */
    public function receivedReviews()
    {
        // First get the provider profile, then get reviews
        return $this->hasManyThrough(
            Review::class,
            ServiceProvider::class,
            'user_id', // Foreign key on service_providers table
            'provider_id', // Foreign key on reviews table
            'id', // Local key on users table
            'id' // Local key on service_providers table
        );
    }

    /**
     * Get the primary category for provider users
     */
    public function primaryCategory()
    {
        return $this->hasOneThrough(
            ServiceCategory::class,
            ServiceProvider::class,
            'user_id',
            'id',
            'id',
            'category_id'
        );
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function walletTransactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function walletDeposits()
    {
        return $this->hasMany(WalletDeposit::class);
    }

    // Scopes
    public function scopeClients($query)
    {
        return $query->where('user_type', 'client');
    }

    public function scopeProviders($query)
    {
        return $query->where('user_type', 'provider');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Set the phone attribute and normalize it
     */
    public function setPhoneAttribute($value)
    {
        if ($value) {
            $phoneService = new PhoneNumberService();
            $this->attributes['phone'] = $phoneService->normalize($value);
        }
    }

    /**
     * Get formatted phone number for display
     */
    public function getFormattedPhoneAttribute()
    {
        $phoneService = new PhoneNumberService();
        return $phoneService->formatForDisplay($this->phone);
    }

    /**
     * Register media collections for user avatar
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile() // Only one avatar per user
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg']);
    }

    /**
     * Register media conversions for automatic avatar optimization
     * Optimizes avatar images to reduce size and improve loading speed
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Optimized version - 300x300px for profile display
        // Perfect size for avatars, reduces file size significantly
        $this->addMediaConversion('optimized')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->quality(85)
            ->format('jpg')
            ->performOnCollections('avatar')
            ->nonQueued(); // Process immediately

        // Thumbnail version - 100x100px for small displays (lists, etc.)
        $this->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->sharpen(10)
            ->quality(80)
            ->format('jpg')
            ->performOnCollections('avatar')
            ->nonQueued();
    }

    /**
     * Get avatar URL with gender-based default
     * Returns optimized version if available for better performance
     */
    public function getAvatarUrlAttribute()
    {
        // First check if using Spatie Media Library
        $media = $this->getFirstMedia('avatar');
        if ($media) {
            // Return optimized version if available, otherwise original
            return $media->hasGeneratedConversion('optimized')
                ? $media->getUrl('optimized')
                : $media->getUrl();
        }

        // Fallback to legacy avatar field for backwards compatibility
        if ($this->avatar && \Storage::disk('public')->exists($this->avatar)) {
            return \Storage::url($this->avatar);
        }

        // Return null for mobile apps - let them show their own default avatars
        // This avoids SVG compatibility issues with Flutter's Image widget
        return null;
    }
}
