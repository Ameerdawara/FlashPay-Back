<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. حذف عمود profit من main_safes ──────────────────────────────
        Schema::table('main_safes', function (Blueprint $table) {
            if (Schema::hasColumn('main_safes', 'profit')) {
                $table->dropColumn('profit');
            }
        });

        // ── 2. حذف عمود profit من trading_safes + إضافة balance_sy ────────
        Schema::table('trading_safes', function (Blueprint $table) {
            if (Schema::hasColumn('trading_safes', 'profit')) {
                $table->dropColumn('profit');
            }
            // رصيد بالليرة السورية (مرتبط مباشر بـ office_safe.balance_sy)
        });

        // ── 3. إضافة balance_sy إلى office_safes ─────────────────────────
        Schema::table('office_safes', function (Blueprint $table) {
            // رصيد بالليرة السورية (مجموع ما في صندوق التداول + أي إيداع يدوي)
            $table->decimal('balance_sy', 20, 2)->default(0)->after('balance');
        });
    }

    public function down(): void
    {
        // استعادة profit
        Schema::table('main_safes', function (Blueprint $table) {
            $table->decimal('profit', 15, 2)->default(0)->after('balance');
        });

        Schema::table('trading_safes', function (Blueprint $table) {
            $table->decimal('profit', 15, 2)->default(0)->after('balance');
            $table->dropColumn('balance_sy');
        });

        Schema::table('office_safes', function (Blueprint $table) {
            $table->dropColumn('balance_sy');
        });
    }
};
