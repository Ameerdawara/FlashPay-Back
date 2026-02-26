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
        // Database/migrations/create_transfers_table.php
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_code')->unique();
            $table->foreignId('sender_id')->constrained('users');

            // المبالغ
            $table->decimal('amount', 15, 2);
            $table->foreignId('currency_id')->constrained();
            $table->decimal('fee', 10, 2)->default(0);

            // جهة تسليم الكاش للمستلم (Destination)
            $table->foreignId('destination_office_id')->nullable()->constrained('offices');
            $table->foreignId('destination_agent_id')->nullable()->constrained('users');

            // بيانات المستلم
            $table->string('receiver_name');
            $table->string('receiver_phone');
            $table->string('receiver_id_image')->nullable(); // مسار صورة الهوية

            $table->enum('status', ['pending', 'approved','waiting' ,'ready', 'completed', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
