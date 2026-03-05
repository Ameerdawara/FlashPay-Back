<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('transfers', function (Blueprint $table) {
            // إضافة حقل القيمة بالدولار (رقم عشري)
            $table->decimal('amount_in_usd', 15, 2)->default(0)->after('amount');
        });
    }

    public function down()
    {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropColumn('amount_in_usd');
        });
    }
};