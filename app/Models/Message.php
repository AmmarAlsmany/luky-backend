<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Message extends Model implements HasMedia
{
    use InteractsWithMedia;
    protected $fillable = [
        'conversation_id',
        'sender_id',
        'sender_type',
        'message_type',
        'content',
        'image_path',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    protected $appends = [
        'image_url',
        'sender_name',
        'sender_avatar',
    ];

    /**
     * Get the conversation this message belongs to
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * Get the sender (user, provider, or admin) of this message
     * This handles polymorphic sender types based on sender_type field
     */
    public function sender()
    {
        // Based on sender_type, return the appropriate relationship
        switch ($this->sender_type) {
            case 'provider':
                return $this->belongsTo(ServiceProvider::class, 'sender_id');
            case 'client':
            case 'admin':
            default:
                return $this->belongsTo(User::class, 'sender_id');
        }
    }

    /**
     * Get sender name regardless of type
     */
    public function getSenderNameAttribute(): string
    {
        if ($this->sender_type === 'provider') {
            $provider = ServiceProvider::find($this->sender_id);
            return $provider ? $provider->business_name : 'Unknown Provider';
        } else {
            $user = User::find($this->sender_id);
            return $user ? $user->name : 'Unknown User';
        }
    }

    /**
     * Get sender avatar regardless of type
     */
    public function getSenderAvatarAttribute(): ?string
    {
        if ($this->sender_type === 'provider') {
            $provider = ServiceProvider::find($this->sender_id);
            return $provider ? $provider->logo_url : null;
        } else {
            $user = User::find($this->sender_id);
            return $user ? $user->avatar_url : null;
        }
    }

    /**
     * Register media collections for chat images
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('chat_image')
            ->singleFile() // Only one image per message
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/jpg', 'image/gif']);
    }

    /**
     * Register media conversions for automatic image optimization
     * Optimizes chat images to reduce size and improve loading speed
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Optimized version - Maximum 1200px, compressed to 85% quality
        // Reduces file size by 80-95% while maintaining good quality for chat
        $this->addMediaConversion('optimized')
            ->width(1200)
            ->height(1200)
            ->sharpen(10)
            ->quality(85)
            ->format('jpg')
            ->performOnCollections('chat_image')
            ->nonQueued(); // Process immediately

        // Thumbnail version - 300px for message previews
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->quality(80)
            ->format('jpg')
            ->performOnCollections('chat_image')
            ->nonQueued();
    }

    /**
     * Get the full image URL
     * Returns optimized version if available for better performance
     */
    public function getImageUrlAttribute(): ?string
    {
        // First check if using Spatie Media Library
        $media = $this->getFirstMedia('chat_image');
        if ($media) {
            // Return optimized version if available, otherwise original
            return $media->hasGeneratedConversion('optimized')
                ? $media->getUrl('optimized')
                : $media->getUrl();
        }

        // Fallback to legacy image_path for backwards compatibility
        if (!$this->image_path) {
            return null;
        }

        // If using local storage
        if (config('filesystems.default') === 'local') {
            return url('storage/' . $this->image_path);
        }

        // If using S3 or other cloud storage
        return \Storage::url($this->image_path);
    }

    /**
     * Scope to get unread messages
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope to get messages for a conversation
     */
    public function scopeForConversation($query, int $conversationId)
    {
        return $query->where('conversation_id', $conversationId);
    }

    /**
     * Mark message as read
     */
    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        }
    }
}
