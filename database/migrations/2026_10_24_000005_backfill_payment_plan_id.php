<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Older payments only had a voucher_id. Copy the plan across so
        // reporting and admin screens work for historical rows.
        DB::statement(
            'update payments set plan_id = (select plan_id from vouchers where vouchers.id = payments.voucher_id) where plan_id is null and voucher_id is not null'
        );
    }

    public function down(): void
    {
        // Data backfill only; nothing to reverse.
    }
};
