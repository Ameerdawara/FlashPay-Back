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
    Schema::create('profit_safes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('office_id')->constrained()->cascadeOnDelete(); // ربط بالمكتب
        $table->decimal('profit_trade', 15, 2)->default(0); // أرباح التداول
        $table->decimal('profit_main', 15, 2)->default(0);  // أرباح الصندوق الرئيسي
        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profit_safes');
    }
};
