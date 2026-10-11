<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->foreignId('past_exam_section_id')->nullable()->after('source_page')->constrained()->nullOnDelete();
            // Kitapçıktaki soru numarası.
            $table->unsignedTinyInteger('past_exam_number')->nullable()->after('past_exam_section_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('past_exam_section_id');
            $table->dropColumn('past_exam_number');
        });
    }
};
