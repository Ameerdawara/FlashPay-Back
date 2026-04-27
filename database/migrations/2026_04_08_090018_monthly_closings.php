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
            // الشهر بصيغة "YYYY-MM"
            $table->string('month', 7);
            // null = تم الإقفال لكل المكاتب دفعة واحدة
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->unsignedInteger('archived_transfers_count')->default(0);
            $table->foreignId('performed_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();

            // منع تكرار الإقفال لنفس الشهر والمكتب
            $table->unique(['month', 'office_id']);
        });

        // 2. إنشاء جدول لقطات الصناديق (Snapshots)
        Schema::create('monthly_safe_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('closing_id')->constrained('monthly_closings')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices');

            // أرصدة الصناديق
            $table->decimal('office_safe_usd', 15, 2)->default(0);
            $table->decimal('office_safe_sy',  15, 2)->default(0);
            $table->decimal('trading_safe_usd',  15, 2)->default(0);
            $table->decimal('trading_safe_sy',   15, 2)->default(0);
            $table->decimal('trading_safe_cost', 15, 4)->default(0);
            $table->decimal('profit_safe_main',  15, 2)->default(0);
            $table->decimal('profit_safe_trade', 15, 2)->default(0);

            $table->timestamps();
        });

        // 3. تعديل حالة الحوالات لتشمل 'archived'
        // (ملاحظة: تأكد أن هذه هي كل الحالات الموجودة لديك في جدول الحوالات)

        // أولاً: حذف الـ constraint القديم إن وجد (لتجنب التعارض)
        DB::statement("ALTER TABLE transfers DROP CONSTRAINT IF EXISTS transfers_status_check");
        DB::statement("ALTER TABLE transfers DROP CONSTRAINT IF EXISTS status_check");

        // ثانياً: تحويل العمود إلى VARCHAR لدعم القيم الجديدة
        DB::statement("ALTER TABLE transfers ALTER COLUMN status TYPE VARCHAR(255)");

        // ثالثاً: إضافة constraint جديد يشمل 'archived'
        DB::statement("ALTER TABLE transfers ADD CONSTRAINT transfers_status_check CHECK (status IN ('waiting', 'pending', 'approved', 'ready', 'completed', 'cancelled', 'rejected', 'archived'))");
    }

    public function down(): void
    {
        // إرجاع الحالة لما كانت عليه قبل التهجير
        DB::statement("ALTER TABLE transfers DROP CONSTRAINT IF EXISTS transfers_status_check");
        DB::statement("ALTER TABLE transfers DROP CONSTRAINT IF EXISTS status_check");

        DB::statement("ALTER TABLE transfers ALTER COLUMN status TYPE VARCHAR(255)");
        DB::statement("ALTER TABLE transfers ADD CONSTRAINT transfers_status_check CHECK (status IN ('waiting', 'pending', 'approved', 'ready', 'completed', 'cancelled', 'rejected'))");

        Schema::dropIfExists('monthly_safe_snapshots');
        Schema::dropIfExists('monthly_closings');
    }
};