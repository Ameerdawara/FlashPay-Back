<?php
// ══════════════════════════════════════════════════════════════════════════
//  Migration ① — monthly_closings
//  الملف: database/migrations/xxxx_xx_xx_create_monthly_closings_table.php
// ══════════════════════════════════════════════════════════════════════════

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_closings', function (Blueprint $table) {
            $table->id();

            // الشهر بصيغة "YYYY-MM" مثل "2025-03"
            $table->string('month', 7);

            // null = تم الإقفال لكل المكاتب دفعة واحدة
            $table->foreignId('office_id')->nullable()->constrained('offices')->nullOnDelete();

            // عدد الحوالات التي أُرشفت في هذا الإقفال
            $table->unsignedInteger('archived_transfers_count')->default(0);

            // من نفّذ الإقفال
            $table->foreignId('performed_by')->constrained('users');

            $table->text('notes')->nullable();
            $table->timestamps();

            // منع تكرار الإقفال لنفس الشهر + المكتب
            $table->unique(['month', 'office_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_closings');
    }
};


// ══════════════════════════════════════════════════════════════════════════
//  Migration ② — monthly_safe_snapshots
//  الملف: database/migrations/xxxx_xx_xx_create_monthly_safe_snapshots_table.php
// ══════════════════════════════════════════════════════════════════════════

/*
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_safe_snapshots', function (Blueprint $table) {
            $table->id();

            $table->foreignId('closing_id')->constrained('monthly_closings')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices');

            // خزنة المكتب (OfficeSafe)
            $table->decimal('office_safe_usd', 15, 2)->default(0);
            $table->decimal('office_safe_sy',  15, 2)->default(0);

            // صندوق التداول (TradingSafe — currency_id = 1)
            $table->decimal('trading_safe_usd',  15, 2)->default(0);
            $table->decimal('trading_safe_sy',   15, 2)->default(0);
            $table->decimal('trading_safe_cost', 15, 4)->default(0); // متوسط التكلفة AVCO

            // صندوق الأرباح (ProfitSafe)
            $table->decimal('profit_safe_main',  15, 2)->default(0);
            $table->decimal('profit_safe_trade', 15, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_safe_snapshots');
    }
};
*/

// ══════════════════════════════════════════════════════════════════════════
//  تعديل migration الحوالات — إضافة قيمة 'archived' لعمود status
//  الملف: database/migrations/xxxx_xx_xx_add_archived_to_transfers_status.php
// ══════════════════════════════════════════════════════════════════════════

/*
return new class extends Migration
{
    public function up(): void
    {
        // MySQL: تغيير نوع العمود ENUM ليشمل 'archived'
        // إذا كان status عمود ENUM:
        DB::statement("ALTER TABLE transfers MODIFY COLUMN status ENUM(
            'waiting','pending','approved','ready','completed','cancelled','rejected','archived'
        ) NOT NULL DEFAULT 'waiting'");

        // إذا كان status عمود VARCHAR/string عادي لا تحتاج لشيء إضافي
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transfers MODIFY COLUMN status ENUM(
            'waiting','pending','approved','ready','completed','cancelled','rejected'
        ) NOT NULL DEFAULT 'waiting'");
    }
};
*/
