<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extra_boxes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // اسم الصندوق الإضافي
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->foreignId('office_id')->constrained('offices')->onDelete('cascade');
            // الكمية أو الرصيد
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extra_boxes');
    }
};
