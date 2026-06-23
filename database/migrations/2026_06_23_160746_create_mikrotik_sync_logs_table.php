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
        Schema::create('mikrotik_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('action', ['add', 'remove', 'update']);
            $table->enum('status', ['success', 'failed']);
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mikrotik_sync_logs');
    }
};
