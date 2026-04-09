<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_closings', function (Blueprint $table) {
            $table->decimal('total_amount_usd', 15, 2)->default(0)->after('archived_transfers_count');
            $table->decimal('total_profit', 15, 2)->default(0)->after('total_amount_usd');
        });
    }

    public function down(): void
    {
        Schema::table('monthly_closings', function (Blueprint $table) {
            $table->dropColumn(['total_amount_usd', 'total_profit']);
        });
    }
};
