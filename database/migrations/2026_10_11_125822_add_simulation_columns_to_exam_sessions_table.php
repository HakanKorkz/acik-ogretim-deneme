<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Bir sınav, seçilen derslerin sırayla çözüldüğü bölümlerden oluşur
     * (exam_sections). Her bölümün kendi süresi ve sonucu vardır; dersler
     * birbirine karışmaz. Ziyaretçinin daha önce gördüğü sorular yeni
     * sınavlarda sona bırakılır.
     */
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->change();
            $table->string('level', 20)->default('lise')->after('ulid');
            $table->string('visitor_id', 26)->nullable()->after('user_id')->index();
            $table->string('question_pool', 20)->default('mixed')->after('visitor_id');
            // per_course: her ders bitince sonucu göster; combined: en sonda toplu.
            $table->string('result_mode', 20)->default('combined')->after('question_pool');
            $table->unsignedSmallInteger('section_minutes')->nullable()->after('result_mode');
        });

        Schema::create('exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->unsignedTinyInteger('total_questions')->default(0);
            $table->unsignedTinyInteger('correct_count')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_session_id', 'position']);
        });

        Schema::table('exam_answers', function (Blueprint $table) {
            $table->foreignId('exam_section_id')->nullable()->after('exam_session_id')->constrained()->cascadeOnDelete();
            // Dersin bölümündeki soru numarası (kitapçıktaki gibi 1–10).
            $table->unsignedTinyInteger('number')->nullable()->after('position');
            // Bu sınavda şıkların gösterim sırası (option id listesi).
            $table->json('option_order')->nullable()->after('number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_answers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exam_section_id');
            $table->dropColumn(['number', 'option_order']);
        });

        Schema::dropIfExists('exam_sections');

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropIndex(['visitor_id']);
            $table->dropColumn(['level', 'visitor_id', 'question_pool', 'result_mode', 'section_minutes']);
        });
    }
};
