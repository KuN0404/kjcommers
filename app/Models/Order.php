<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany; // Pastikan ini di-import
use App\Models\OrderItem; // Pastikan ini di-import
use App\Models\User; // Pastikan ini di-import

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['buyer_id', 'order_number', 'status', 'total_amount'];

    // Eager load items and buyer (jika masih digunakan)
    // protected $with = ['buyer', 'orderItems.product'];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    /**
     * Definisikan relasi orderItems.
     * Pastikan return type hint HasMany dan model OrderItem sudah benar.
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id'); // 'order_id' adalah foreign key default, bisa juga tidak ditulis jika standar
    }
}
