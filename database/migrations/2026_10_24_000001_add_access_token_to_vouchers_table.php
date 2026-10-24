<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('access_token', 64)->nullable()->after('id');
        });

        // Backfill tokens for vouchers created before this migration.
        DB::table('vouchers')
            ->whereNull('access_token')
            ->orderBy('id')
            ->chunkById(100, function ($vouchers) {
                foreach ($vouchers as $voucher) {
                    DB::table('vouchers')
                        ->where('id', $voucher->id)
                        ->update(['access_token' => Str::random(48)]);
                }
            });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->unique('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropUnique('vouchers_access_token_unique');
            $table->dropColumn('access_token');
        });
    }
};
