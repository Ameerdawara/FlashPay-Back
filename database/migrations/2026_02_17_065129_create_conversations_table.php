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
       Schema::create('conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('transfer_id')->constrained();
    $table->foreignId('customer_id')->constrained('users');
    $table->foreignId('agent_id')->nullable()->constrained('users'); // المندوب أو موظف المكتب
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
