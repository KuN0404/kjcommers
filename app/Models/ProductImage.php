<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage; // Untuk mengakses URL gambar

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'url', // Akan menyimpan path ke file gambar
        'alt_text',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['full_url']; // Untuk mendapatkan URL lengkap gambar

    /**
     * Get the product that owns the image.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the full URL for the image.
     *
     * @return string|null
     */
    public function getFullUrlAttribute(): ?string
    {
        if ($this->url) {
            // Sesuaikan 'public' jika Anda menggunakan disk yang berbeda
            return Storage::disk('public')->url($this->url);
        }
        return null;
    }
}