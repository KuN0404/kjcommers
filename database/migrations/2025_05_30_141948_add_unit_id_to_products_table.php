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
            if (Schema::hasTable('products')) {
                Schema::table('products', function (Blueprint $table) {
                    if (!Schema::hasColumn('products', 'unit_id')) {
                        $table->foreignId('unit_id')->nullable()->after('category_id')->constrained('units')->nullOnDelete();
                    }
                });
            }
        }
        public function down(): void
        {
            if (Schema::hasTable('products') && Schema::hasColumn('products', 'unit_id')) {
                Schema::table('products', function (Blueprint $table) {
                    $table->dropForeign(['unit_id']);
                    $table->dropColumn('unit_id');
                });
            }
        }
};