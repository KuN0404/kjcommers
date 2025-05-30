<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
        {
            Schema::create('cart_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cart_id')->constrained('carts')->onDelete('cascade');
                $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
                $table->unsignedInteger('quantity')->default(1);
                // Opsional: menyimpan harga saat ditambahkan untuk menghindari masalah jika harga produk berubah
                // $table->decimal('price_at_addition', 10, 2)->nullable();
                $table->timestamps();

                // Unique constraint untuk memastikan kombinasi cart_id dan product_id unik
                // sehingga satu produk hanya muncul sekali per keranjang (jumlah diatur via quantity)
                $table->unique(['cart_id', 'product_id']);
            });
        }

        public function down(): void
        {
            Schema::dropIfExists('cart_items');
        }
};
