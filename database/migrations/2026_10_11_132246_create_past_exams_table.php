<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MEB'in yayımladığı çıkmış sınav kitapçıkları (aol.meb.gov.tr).
     */
    public function up(): void
    {
        Schema::create('past_exams', function (Blueprint $table) {
            $table->id();
            // "14-15 Mart 2026 – 1. oturum"
            $table->string('title');
            $table->string('period');
            $table->unsignedTinyInteger('session_number')->nullable();
            $table->string('source_url', 500)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('parsed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('past_exams');
    }
};
