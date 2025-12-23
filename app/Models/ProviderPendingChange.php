<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderPendingChange extends Model
{
    protected $fillable = [
        'provider_id',
        'changed_fields',
        'old_values',
        'status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'admin_notes',
    ];

    protected $casts = [
        'changed_fields' => 'array',
        'old_values' => 'array',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the provider that owns this pending change
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    /**
     * Get the admin who reviewed this change
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope to get only pending changes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get approved changes
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope to get rejected changes
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
