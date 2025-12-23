<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_number',
        'client_id',
        'provider_id',
        'booking_date',
        'start_time',
        'end_time',
        'status',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'commission_amount',
        'payment_status',
        'payment_method',
        'payment_reference',
        'notes',
        'client_address',
        'client_latitude',
        'client_longitude',
        'cancellation_reason',
        'cancelled_by',
        'confirmed_at',
        'payment_deadline',
        'completed_at',
        'cancelled_at',
        'promo_code_id'
    ];

    protected $casts = [
        'booking_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'client_latitude' => 'decimal:8',
        'client_longitude' => 'decimal:8',
        'confirmed_at' => 'datetime',
        'payment_deadline' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    // Relationships
    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function provider()
    {
        return $this->belongsTo(ServiceProvider::class, 'provider_id');
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function review()
    {
        return $this->hasOne(Review::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}