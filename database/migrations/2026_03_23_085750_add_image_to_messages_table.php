<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            // إضافة عمود الصورة بعد عمود الرسالة
            $table->string('image')->nullable()->after('message');
        });
    }

    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};