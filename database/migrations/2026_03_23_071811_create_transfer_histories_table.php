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
        Schema::create('transfer_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->onDelete('cascade'); // الحوالة المعدلة
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null'); // الأدمن الذي قام بالتعديل
            $table->json('old_data')->nullable(); // البيانات القديمة (قبل التعديل)
            $table->json('new_data')->nullable(); // البيانات الجديدة (بعد التعديل)
            $table->string('action')->default('updated'); // نوع العملية (تعديل، تغيير حالة..)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_histories');
    }
};
