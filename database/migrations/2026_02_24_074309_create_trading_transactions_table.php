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
        Schema::create('trading_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained();
            $table->foreignId('currency_id')->constrained();
            $table->foreignId('user_id')->constrained(); // الموظف الذي قام بالعملية

            $table->enum('type', ['buy', 'sell']); // نوع العملية
            $table->decimal('amount', 15, 2);      // الكمية (مثلاً 100$)
            $table->decimal('price', 15, 2);       // سعر التنفيذ
            $table->decimal('cost_at_time', 15, 4); // التكلفة في لحظة العملية (لحساب الربح لاحقاً)
            $table->decimal('profit', 15, 2)->default(0); // الربح المحقق (يحسب عند البيع فقط)

            $table->date('transaction_date'); // حقل التاريخ الذي طلبته للبحث اليومي
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_transactions');
    }
};
