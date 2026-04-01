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
            $table->foreignId('office_id')->nullable()->constrained('offices')->onDelete('cascade');
            $table->string('sender_name');
            $table->string('receiver_name');
            $table->string('destination_province');
            $table->string('receiver_phone')->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('commission', 15, 2)->default(0.00);
            $table->string('currency', 10)->default('SYP');            // العملة
            $table->enum('fee_payer', ['sender', 'receiver'])->default('sender'); // من يدفع الرسوم
            $table->boolean('is_paid')->default(false);
            $table->date('transfer_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_transfers');
    }
};
