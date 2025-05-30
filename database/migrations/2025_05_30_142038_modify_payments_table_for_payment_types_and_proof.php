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
            if (Schema::hasTable('payments')) {
                Schema::table('payments', function (Blueprint $table) {
                    // Menghapus kolom payment_method lama jika ada.
                    // Jika Anda punya data di kolom ini, pastikan sudah dimigrasikan atau tidak masalah jika hilang.
                    if (Schema::hasColumn('payments', 'payment_method')) {
                        $table->dropColumn('payment_method');
                    }

                    if (!Schema::hasColumn('payments', 'payment_type_id')) {
                        $table->foreignId('payment_type_id')->nullable()->after('order_id')->constrained('payment_types')->nullOnDelete();
                    }
                    if (!Schema::hasColumn('payments', 'proof_of_payment_url')) {
                        $table->string('proof_of_payment_url')->nullable()->after('status');
                    }
                    if (!Schema::hasColumn('payments', 'payment_notes_from_user')) {
                        $table->text('payment_notes_from_user')->nullable()->after('proof_of_payment_url');
                    }
                });
            }
        }
        public function down(): void
        {
            if (Schema::hasTable('payments')) {
                Schema::table('payments', function (Blueprint $table) {
                    if (Schema::hasColumn('payments', 'payment_notes_from_user')) $table->dropColumn('payment_notes_from_user');
                    if (Schema::hasColumn('payments', 'proof_of_payment_url')) $table->dropColumn('proof_of_payment_url');
                    if (Schema::hasColumn('payments', 'payment_type_id')) {
                        $table->dropForeign(['payment_type_id']);
                        $table->dropColumn('payment_type_id');
                    }
                    // Jika Anda ingin mengembalikan kolom payment_method lama saat rollback
                    // if (!Schema::hasColumn('payments', 'payment_method')) {
                    //     $table->enum('payment_method', ['e_wallet', 'bank_transfer'])->after('order_id')->nullable();
                    // }
                });
            }
        }
};