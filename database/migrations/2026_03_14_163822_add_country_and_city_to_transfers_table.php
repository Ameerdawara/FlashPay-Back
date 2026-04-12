<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('transfers', function (Blueprint $table) {
            $table->foreignId('destination_country_id')->nullable()->constrained('countries')->onDelete('set null')->after('destination_agent_id');
            $table->string('destination_city')->nullable()->after('destination_country_id');
        });
    }
    public function down() {
        Schema::table('transfers', function (Blueprint $table) {
            $table->dropForeign(['destination_country_id']);
            $table->dropColumn(['destination_country_id', 'destination_city']);
        });
    }
};