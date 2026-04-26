<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'agent_profit_ratio')) {
                $table->decimal('agent_profit_ratio', 5, 2)->default(0)->nullable()->after('fcm_token');
            }
        });

        // ✅ sender_id → nullable لحل مشكلة حذف المستخدم
        if (Schema::hasColumn('transfers', 'sender_id')) {
            // إزالة FK القديم وإعادته مع nullOnDelete
            try {
                Schema::table('transfers', function (Blueprint $table) {
                    $table->dropForeign(['sender_id']);
                });
            } catch (\Exception $e) { /* FK قد لا يكون موجوداً بالاسم الافتراضي */ }

            Schema::table('transfers', function (Blueprint $table) {
                $table->unsignedBigInteger('sender_id')->nullable()->change();
                $table->foreign('sender_id')
                      ->references('id')->on('users')
                      ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumnIfExists('agent_profit_ratio');
        });
    }
};
