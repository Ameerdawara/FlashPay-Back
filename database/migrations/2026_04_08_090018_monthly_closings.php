<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. إنشاء جدول الإقفالات
        Schema::create('monthly_closings', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7);
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->unsignedInteger('archived_transfers_count')->default(0);
            $table->foreignId('performed_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['month', 'office_id']);
        });

        // 2. إنشاء جدول لقطات الصناديق
        Schema::create('monthly_safe_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('closing_id')->constrained('monthly_closings')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices');
            $table->decimal('office_safe_usd', 15, 2)->default(0);
            $table->decimal('office_safe_sy',  15, 2)->default(0);
            $table->decimal('trading_safe_usd',  15, 2)->default(0);
            $table->decimal('trading_safe_sy',   15, 2)->default(0);
            $table->decimal('trading_safe_cost', 15, 4)->default(0);
            $table->decimal('profit_safe_main',  15, 2)->default(0);
            $table->decimal('profit_safe_trade', 15, 2)->default(0);
            $table->timestamps();
        });

        // 3. تعديل عمود status ليشمل 'archived' (MySQL)
        DB::statement("ALTER TABLE transfers MODIFY COLUMN status ENUM('waiting', 'pending', 'approved', 'ready', 'completed', 'cancelled', 'rejected', 'archived') NOT NULL");
    }

    public function down(): void
    {
        // إرجاع status بدون archived
        DB::statement("ALTER TABLE transfers MODIFY COLUMN status ENUM('waiting', 'pending', 'approved', 'ready', 'completed', 'cancelled', 'rejected') NOT NULL");

        Schema::dropIfExists('monthly_safe_snapshots');
        Schema::dropIfExists('monthly_closings');
    }
};