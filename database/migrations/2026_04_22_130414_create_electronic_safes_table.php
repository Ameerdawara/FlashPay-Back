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
    Schema::create('electronic_safes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('office_id')->constrained()->onDelete('cascade');
        $table->decimal('syp_sham_cash', 15, 2)->default(0);
        $table->decimal('usd_sham_cash', 15, 2)->default(0);
        $table->decimal('usdt', 15, 2)->default(0);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('electronic_safes');
    }
};
