<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('main_safes', function (Blueprint $table) {
            // نسبة ربح المندوب (0–100)، يحددها السوبر أدمن عند إنشاء الموظف
            $table->decimal('agent_profit_ratio', 5, 2)->default(0)->after('balance')
                  ->comment('نسبة الربح التي تذهب لصندوق المندوب من fee الحوالة (%)');

            // إجمالي الأرباح المتراكمة في صندوق المندوب
            $table->decimal('agent_profit', 15, 2)->default(0)->after('agent_profit_ratio')
                  ->comment('إجمالي أرباح المندوب المتراكمة من الحوالات');
        });
    }

    public function down(): void
    {
        Schema::table('main_safes', function (Blueprint $table) {
            $table->dropColumn(['agent_profit_ratio', 'agent_profit']);
        });
    }
};
