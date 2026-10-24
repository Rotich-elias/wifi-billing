<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('data_limit')->nullable()->after('idle_timeout');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('voucher_id')->nullable()->change();
            $table->string('customer_name')->nullable()->after('phone');
            $table->string('mac', 17)->nullable()->after('customer_name');
            $table->string('ip', 45)->nullable()->after('mac');
            $table->text('login_url')->nullable()->after('ip');
            $table->text('orig_url')->nullable()->after('login_url');
            $table->string('status', 20)->default('pending')->change();
        });

        // The old lifecycle used completed/cancelled. The portal flow uses
        // paid/expired; keep historical rows consistent.
        DB::table('payments')->where('status', 'completed')->update(['status' => 'paid']);
        DB::table('payments')->where('status', 'cancelled')->update(['status' => 'expired']);

        Schema::table('vouchers', function (Blueprint $table) {
            $table->text('provisioning_error')->nullable()->after('mikrotik_synced_at');
        });

        // Development driver storage. It mirrors the hotspot user fields the
        // real RouterOS driver writes so the rest of the app behaves the same.
        Schema::create('mock_router_users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('password');
            $table->string('profile')->nullable();
            $table->string('uptime_limit')->nullable();
            $table->string('data_limit')->nullable();
            $table->string('comment')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_router_users');

        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('provisioning_error');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['customer_name', 'mac', 'ip', 'login_url', 'orig_url']);
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('data_limit');
        });
    }
};
