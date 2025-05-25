<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ShippingAddress extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'order_id', 'recipient_name', 'address_line1', 'address_line2', 'city', 'state', 'postal_code', 'country', 'phone'];

    // Eager load user and order
    protected $with = ['user'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
