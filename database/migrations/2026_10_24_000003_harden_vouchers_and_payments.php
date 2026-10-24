<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('status');
            $table->index('expires_at');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('mpesa_receipt');
            $table->index('status');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_mpesa_receipt_unique');
            $table->dropIndex(['status']);
            $table->dropIndex(['paid_at']);
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['expires_at']);
            $table->dropSoftDeletes();
        });
    }
};
