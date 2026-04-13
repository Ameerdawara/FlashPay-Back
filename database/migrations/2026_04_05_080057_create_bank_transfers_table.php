<?php
// =============================================================================
//  Migration: create_bank_transfers_table
//  المسار: database/migrations/xxxx_xx_xx_create_bank_transfers_table.php
//
//  تشغيل:  php artisan migrate
// =============================================================================

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transfer', function (Blueprint $table) {
            $table->id();

            // الوكيل الذي أرسل الطلب
            $table->foreignId('agent_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // بيانات البنك
            $table->string('bank_name');
            $table->string('account_number');
            $table->string('full_name');
            $table->string('phone', 30);

            // المبلغ بالدولار — يذهب إلى super_safe مباشرة عند الموافقة
            $table->decimal('amount', 15, 2);

            // ملاحظات اختيارية
            $table->text('notes')->nullable();

            // الحالة: pending → approved | rejected
            $table->enum('status', ['pending', 'approved', 'rejected'])
                  ->default('pending');

            // السوبر ادمن الذي وافق/رفض
            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfer');
    }
};
