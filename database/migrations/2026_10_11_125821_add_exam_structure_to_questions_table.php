<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Gerçek AÖL kitapçıklarındaki gibi soru = giriş metni (parça, ayet,
     * öncüller) + soru kökü (`text`) + şıklar. Kaynak ve kitaptaki sayfa da
     * tutulur.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->text('passage')->nullable()->after('course_id');
            $table->string('source', 20)->default('ai')->after('correct_option')->index();
            $table->unsignedSmallInteger('source_page')->nullable()->after('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn(['passage', 'source', 'source_page']);
        });
    }
};
