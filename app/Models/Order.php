<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str; // Pastikan Str diimport

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'buyer_id',
        'shipping_address_id',
        'shipping_courier_id',
        'order_number',
        'status',
        'total_amount', // Pastikan ini ada di $fillable
        'shipping_cost',
        'shipping_tracking_number',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            // Auto-generate order_number jika kosong
            if (empty($order->order_number)) {
                $order->order_number = 'ORD-' . strtoupper(Str::random(8));
            }

            // Set total_amount default ke 0.00 jika belum di-set
            if (!isset($order->total_amount)) {
                $order->total_amount = 0.00;
            }
        });
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'shipping_address_id');
    }

    public function shippingCourier(): BelongsTo
    {
        return $this->belongsTo(ShippingCourier::class, 'shipping_courier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function getGrandTotalAttribute(): float
    {
        return (float) $this->total_amount + (float) $this->shipping_cost;
    }
}
