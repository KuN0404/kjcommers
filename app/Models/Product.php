<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'stock',
        'is_active'
    ];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    // Menambahkan 'seller' ke $with karena sering ditampilkan bersama data produk (misalnya di tabel).
    protected $with = ['category', 'productImages', 'seller'];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'price' => 'decimal:2', // Penting untuk harga agar diperlakukan sebagai angka desimal.
        'is_active' => 'boolean', // Mengubah nilai menjadi true/false.
        'stock' => 'integer',   // Memastikan stok adalah angka integer.
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function productImages()
    {
        return $this->hasMany(ProductImage::class);
    }
}