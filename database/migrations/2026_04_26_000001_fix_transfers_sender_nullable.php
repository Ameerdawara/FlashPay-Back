<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── transfers: sender_id → nullable ─────────────────────────────
        try {
            Schema::table('transfers', function (Blueprint $table) {
                $table->dropForeign(['sender_id']);
            });
        } catch (\Exception $e) {}

        Schema::table('transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('sender_id')->nullable()->change();
            $table->foreign('sender_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->unsignedBigInteger('sender_id')->nullable(false)->change();
            $table->foreign('sender_id')->references('id')->on('users');
        });
    }
};