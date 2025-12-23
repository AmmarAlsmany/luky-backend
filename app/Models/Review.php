<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'booking_id',
        'client_id',
        'provider_id',
        'rating',
        'comment',
        'comment_approved',
        'comment_approved_at',
        'comment_approved_by',
        'is_visible',
        'is_flagged',
        'flag_reason',
        'flagged_by',
        'flagged_at',
        'admin_response',
        'responded_by',
        'responded_at',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_visible' => 'boolean',
        'is_flagged' => 'boolean',
        'comment_approved' => 'boolean',
        'flagged_at' => 'datetime',
        'responded_at' => 'datetime',
        'approved_at' => 'datetime',
        'comment_approved_at' => 'datetime',
    ];

    /**
     * Default values for attributes
     */
    protected $attributes = [
        'approval_status' => 'approved', // Auto-approve rating
        'comment_approved' => false, // Comments need admin approval
        'is_visible' => true,
        'is_flagged' => false,
    ];

    /**
     * Get the booking that was reviewed
     */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /**
     * Get the client who wrote the review
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /**
     * Alias for client (for consistency)
     */
    public function user(): BelongsTo
    {
        return $this->client();
    }

    /**
     * Get the provider being reviewed
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    /**
     * Get the service being reviewed (if applicable)
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Scope for visible reviews only
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope for a specific provider
     */
    public function scopeForProvider($query, int $providerId)
    {
        return $query->where('provider_id', $providerId);
    }

    /**
     * Scope for a specific rating
     */
    public function scopeWithRating($query, int $rating)
    {
        return $query->where('rating', $rating);
    }

    /**
     * Scope for approved reviews only
     */
    public function scopeApproved($query)
    {
        return $query->where('approval_status', 'approved');
    }

    /**
     * Scope for pending reviews
     */
    public function scopePending($query)
    {
        return $query->where('approval_status', 'pending');
    }

    /**
     * Scope for rejected reviews
     */
    public function scopeRejected($query)
    {
        return $query->where('approval_status', 'rejected');
    }

    /**
     * Get the admin who approved/rejected the review
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
