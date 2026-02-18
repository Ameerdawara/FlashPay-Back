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
        Schema::create('trading_safes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained();
            $table->foreignId('currency_id')->constrained();
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('cost', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_safes');
    }
};
