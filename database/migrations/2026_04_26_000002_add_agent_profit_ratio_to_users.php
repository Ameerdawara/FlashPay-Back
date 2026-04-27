<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // فحص منفصل خارج الـ closure — هذا هو الصحيح في PostgreSQL
        if (!Schema::hasColumn('users', 'agent_profit_ratio')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('agent_profit_ratio', 5, 2)->default(0)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'agent_profit_ratio')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('agent_profit_ratio');
            });
        }
    }
};