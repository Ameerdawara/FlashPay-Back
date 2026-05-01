<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transfer', function (Blueprint $table) {
            $table->foreignId('currency_id')
                  ->nullable()
                  ->after('destination_city')
                  ->constrained('currencies')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transfer', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn('currency_id');
        });
    }
};
