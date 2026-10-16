<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── أسعار الصرف بالليرة السورية (منفصلة عن سعر الدولار) ──
        Schema::table('currencies', function (Blueprint $table) {
            $table->decimal('price_syp', 18, 6)->nullable()->after('main_price');       // سعر البيع بالسوري
            $table->decimal('main_price_syp', 18, 6)->nullable()->after('price_syp');   // سعر التكلفة بالسوري
        });

        // ── نوع الشريحة: usd (الموجودة حالياً) أو syp ──
        Schema::table('currency_rates', function (Blueprint $table) {
            $table->string('type', 3)->default('usd')->after('currency_id');
            $table->index(['currency_id', 'type']);
        });

        // ── الحوالة تحفظ أي سعر استُخدم ──
        Schema::table('transfers', function (Blueprint $table) {
            $table->string('rate_type', 3)->default('usd');
            $table->decimal('applied_rate', 18, 6)->nullable();
            $table->decimal('amount_in_syp', 18, 2)->nullable();
            $table->decimal('fee_syp', 18, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn(['rate_type', 'applied_rate', 'amount_in_syp', 'fee_syp']);
        });

        Schema::table('currency_rates', function (Blueprint $table) {
            $table->dropIndex(['currency_id', 'type']);
            $table->dropColumn('type');
        });

        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn(['price_syp', 'main_price_syp']);
        });
    }
};
