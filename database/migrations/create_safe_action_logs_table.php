<?php
// database/migrations/xxxx_xx_xx_create_safe_action_logs_table.php
// الاسم المقترح: 2025_01_01_000000_create_safe_action_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('safe_action_logs', function (Blueprint $table) {
            $table->id();

            // المكتب المنفِّذ للعملية
            $table->unsignedBigInteger('office_id')->index();

            // نوع الصندوق: office_safe | trading | profit_safe | office_main
            $table->string('safe_type', 30)->index();

            // نوع العملية: deposit | withdraw | transfer_to_office | buy | sell
            $table->string('action_type', 30)->index();

            // العملة: USD | SYP
            $table->string('currency', 5)->default('USD');

            // مبلغ العملية (دائماً موجب)
            $table->decimal('amount', 18, 4)->default(0);

            // وصف تفصيلي اختياري
            $table->string('description', 255)->nullable();

            // من نفّذ العملية (user_id)
            $table->unsignedBigInteger('performed_by')->nullable()->index();

            // الرصيد بعد العملية — مساعد للعرض السريع
            $table->decimal('balance_after',    18, 4)->nullable(); // USD / profit_main
            $table->decimal('balance_sy_after', 18, 4)->nullable(); // SYP / profit_trade

            $table->timestamps();

            // فهارس للفلترة السريعة
            $table->index(['office_id', 'safe_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safe_action_logs');
    }
};
