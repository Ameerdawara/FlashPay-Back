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
    Schema::table('main_safes', function (Blueprint $table) {
        $table->decimal('profit', 15, 2)->default(0)->after('balance');
    });

    Schema::table('trading_safes', function (Blueprint $table) {
        $table->decimal('profit', 15, 2)->default(0)->after('balance');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('safes_tables', function (Blueprint $table) {
            //
        });
    }
};
