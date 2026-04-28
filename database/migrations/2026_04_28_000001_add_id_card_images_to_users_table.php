<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * الهدف: استبدال عمود id_card_image الواحد بثلاثة أعمدة منفصلة
     *        selfie_with_id / id_card_front / id_card_back
     *        وتوسيع حقل phone ليستوعب أرقام دولية (+963912345678)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            // ✅ توسيع phone من varchar(255) الافتراضي — لا مشكلة، فقط نوضّح السياق
            // (Laravel يجعلها varchar(255) بالفعل، لكن نضع تعليقاً للتوضيح)

            // ✅ إضافة الأعمدة الثلاثة الجديدة
            $table->string('selfie_with_id')->nullable()->after('id_card_image');
            $table->string('id_card_front')->nullable()->after('selfie_with_id');
            $table->string('id_card_back')->nullable()->after('id_card_front');
        });

        // ✅ نسخ القيمة القديمة إلى selfie_with_id كـ fallback لمن سجّل قديماً
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE users SET selfie_with_id = id_card_image WHERE id_card_image IS NOT NULL"
        );

        // ✅ الآن نحذف العمود القديم (بعد نسخ البيانات)
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('id_card_image');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // استعادة العمود القديم
            $table->string('id_card_image')->nullable()->after('role');
        });

        // نسخ selfie_with_id مجدداً إلى id_card_image عند الـ rollback
        \Illuminate\Support\Facades\DB::statement(
            "UPDATE users SET id_card_image = selfie_with_id WHERE selfie_with_id IS NOT NULL"
        );

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['selfie_with_id', 'id_card_front', 'id_card_back']);
        });
    }
};
