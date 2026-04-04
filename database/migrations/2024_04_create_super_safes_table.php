<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_safes', function (Blueprint $table) {
            $table->id();
            $table->decimal('balance', 20, 4)->default(0);
            $table->timestamps();
        });

        Schema::create('super_safe_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['deposit', 'withdraw', 'transfer_to_office', 'transfer_from_office']);
            $table->decimal('amount', 20, 4);
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->string('office_name')->nullable();
            $table->string('note')->nullable();
            $table->decimal('balance_before', 20, 4)->default(0);
            $table->decimal('balance_after', 20, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_safe_logs');
        Schema::dropIfExists('super_safes');
    }
};
