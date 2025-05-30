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
            if (Schema::hasTable('orders')) {
                Schema::table('orders', function (Blueprint $table) {
                    if (!Schema::hasColumn('orders', 'shipping_address_id')) {
                        $table->foreignId('shipping_address_id')->nullable()->after('buyer_id')->constrained('user_addresses')->nullOnDelete();
                    }
                    if (!Schema::hasColumn('orders', 'shipping_courier_id')) {
                        $table->foreignId('shipping_courier_id')->nullable()->after('shipping_address_id')->constrained('shipping_couriers')->nullOnDelete();
                    }
                    if (!Schema::hasColumn('orders', 'shipping_cost')) {
                        $table->decimal('shipping_cost', 12, 2)->default(0.00)->after('total_amount');
                    }
                    if (!Schema::hasColumn('orders', 'shipping_tracking_number')) {
                        $table->string('shipping_tracking_number')->nullable()->after('shipping_cost');
                    }
                });
            }
        }
        public function down(): void
        {
            if (Schema::hasTable('orders')) {
                Schema::table('orders', function (Blueprint $table) {
                    if (Schema::hasColumn('orders', 'shipping_tracking_number')) $table->dropColumn('shipping_tracking_number');
                    if (Schema::hasColumn('orders', 'shipping_cost')) $table->dropColumn('shipping_cost');
                    if (Schema::hasColumn('orders', 'shipping_courier_id')) {
                        $table->dropForeign(['shipping_courier_id']);
                        $table->dropColumn('shipping_courier_id');
                    }
                    if (Schema::hasColumn('orders', 'shipping_address_id')) {
                        $table->dropForeign(['shipping_address_id']);
                        $table->dropColumn('shipping_address_id');
                    }
                });
            }
        }
};