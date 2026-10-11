<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Kitapçıktaki bir dersin bölümü: ham metni, cevap anahtarı ve
     * katalogdaki dersle eşleşmesi. Sorular bölüm bölüm yapılandırılır.
     */
    public function up(): void
    {
        Schema::create('past_exam_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('past_exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            // Kitapçıktaki ders kodu ve adı: "(132) TARİH – 2"
            $table->string('code', 10);
            $table->string('name');
            $table->longText('content');
            // Soru numarası → doğru şık harfi
            $table->json('answer_key')->nullable();
            $table->string('status', 20)->nullable();
            $table->unsignedTinyInteger('imported_count')->default(0);
            $table->unsignedTinyInteger('skipped_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['past_exam_id', 'code', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('past_exam_sections');
    }
};
