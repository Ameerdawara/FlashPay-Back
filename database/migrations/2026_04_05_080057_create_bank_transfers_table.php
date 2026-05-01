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
{public function up(): void
{
    Schema::create('bank_transfer', function (Blueprint $table) {
        $table->id();

        $table->foreignId('agent_id')
              ->constrained('users')
              ->onDelete('cascade');

        $table->string('bank_name');
        $table->string('account_number');
        $table->string('full_name');
  $table->string('destination_country')->nullable(); // الدولة الوجهة
        $table->string('destination_city')->nullable();    // المدينة الوجهة

        // 👇 هذا هو السطر الذي نسيته يجب إضافته هنا 👇
        $table->string('recipient_name');

        
        $table->string('phone', 30);
        $table->decimal('amount', 15, 2);
        $table->text('notes')->nullable();

        $table->enum('status', ['pending', 'approved', 'admin_approved', 'completed', 'rejected'])
              ->default('pending');

        $table->foreignId('approved_by')
              ->nullable()
              ->constrained('users')
              ->onDelete('set null');

        // أضفت لك عمود الكاشير أيضاً لأنه موجود في الـ Model والـ Controller ولكنه ناقص هنا
        $table->foreignId('cashier_id')
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
