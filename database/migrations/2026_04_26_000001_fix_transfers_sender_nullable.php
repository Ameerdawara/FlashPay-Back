<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * هذا Migration يُصلح مشكلة حذف المستخدم:
 * - يجعل sender_id في transfers قابلاً للـ null
 * - يُضيف agent_profit_ratio و fcm_token لجدول users إن لم تكن موجودة
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. transfers: sender_id → nullable ──────────────────────────
        Schema::table('transfers', function (Blueprint $table) {
            // إزالة الـ FK القديم أولاً (اسمه الافتراضي في Laravel)
            $table->dropForeign(['sender_id']);

            // تغيير العمود ليقبل null
            $table->unsignedBigInteger('sender_id')->nullable()->change();

            // إعادة إضافة الـ FK مع nullOnDelete
            $table->foreign('sender_id')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });

        // ── 2. users: إضافة الأعمدة الناقصة إن لم تكن موجودة ──────────
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'fcm_token')) {
                $table->string('fcm_token')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('users', 'agent_profit_ratio')) {
                $table->decimal('agent_profit_ratio', 5, 2)->default(0)->nullable()->after('fcm_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['sender_id']);
            $table->unsignedBigInteger('sender_id')->nullable(false)->change();
            $table->foreign('sender_id')->references('id')->on('users');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['fcm_token', 'agent_profit_ratio']);
        });
    }
};
