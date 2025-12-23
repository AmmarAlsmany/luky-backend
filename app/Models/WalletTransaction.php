<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'description',
        'reference_number',
        'related_id',
        'related_type',
        'balance_before',
        'balance_after',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the transaction
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for deposit transactions
     */
    public function scopeDeposits($query)
    {
        return $query->where('type', 'deposit');
    }

    /**
     * Scope for payment transactions
     */
    public function scopePayments($query)
    {
        return $query->where('type', 'payment');
    }

    /**
     * Scope for refund transactions
     */
    public function scopeRefunds($query)
    {
        return $query->where('type', 'refund');
    }

    /**
     * Scope for withdrawal transactions
     */
    public function scopeWithdrawals($query)
    {
        return $query->where('type', 'withdrawal');
    }
}
