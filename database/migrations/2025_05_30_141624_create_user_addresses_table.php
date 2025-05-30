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
            Schema::create('user_addresses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('label')->nullable();
                $table->string('recipient_name');
                $table->string('phone_number');
                $table->text('address_line1');
                $table->string('address_line2')->nullable();
                $table->string('village_subdistrict')->nullable();
                $table->string('district');
                $table->string('city_regency');
                $table->string('province');
                $table->string('postal_code', 10);
                $table->string('country')->default('Indonesia');
                $table->text('notes')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_addresses');
    }
};
