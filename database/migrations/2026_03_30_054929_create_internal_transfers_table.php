<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('offices')->onDelete('cascade'); // مرتبط بالمكتب
            $table->string('sender_name');    // المرسل
            $table->string('receiver_name');  // المستلم
            $table->decimal('amount', 15, 2); // المبلغ (أضفته لك لأنه ضروري)
            $table->decimal('commission', 15, 2)->default(0.00); // العمولة
            $table->boolean('is_paid')->default(false); // هل تم الدفع؟ (افتراضياً لا)
            $table->date('transfer_date');    // التاريخ
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_transfers');
    }
};