<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProviderContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'contract_number',
        'start_date',
        'end_date',
        'commission_rate',
        'payment_terms',
        'notes',
        'contract_file',
        'status',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'commission_rate' => 'decimal:2',
    ];

    /**
     * Get the provider that owns the contract
     */
    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    /**
     * Get the admin who created the contract
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if contract is active
     */
    public function isActive()
    {
        return $this->status === 'active' && 
               ($this->end_date === null || $this->end_date->isFuture());
    }

    /**
     * Check if contract is expired
     */
    public function isExpired()
    {
        return $this->end_date !== null && $this->end_date->isPast();
    }
}
