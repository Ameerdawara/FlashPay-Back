<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::create('electronic_safe_logs', function (Blueprint $table) {
        $table->id();
        $table->foreignId('office_id')->constrained()->onDelete('cascade');
        $table->string('currency_type'); // syp_sham_cash, usd_sham_cash, usdt
        $table->enum('action_type', ['buy', 'sell']); 
        $table->decimal('amount', 15, 2);
        $table->decimal('commission_rate', 5, 2);
        $table->decimal('net_amount', 15, 2);
        $table->decimal('profit', 15, 2);
        $table->string('note')->nullable();
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('electronic_safe_logs');
    }
};
