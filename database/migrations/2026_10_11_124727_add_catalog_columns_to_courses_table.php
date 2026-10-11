<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Dersler artık MEB kitap kataloğundan (aokul.gov.tr) ya da yüklenen
     * PDF'ten gelir. PDF barındırılmaz; metni sayfa sayfa book_pages'e yazılır.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('level', 20)->default('lise')->after('id')->index();
            $table->string('subject')->nullable()->after('name');
            $table->string('source', 20)->default('upload')->after('slug');
            // aokul kataloğundaki klasör yolu (ör. "2/1/1"); kademe ile birlikte tekildir.
            $table->string('external_key')->nullable()->after('source');
            $table->string('source_url', 500)->nullable()->after('external_key');
            // Açıklamadaki sorumluluk aralığı; kitabın BASILI sayfa numaralarıdır.
            $table->unsignedSmallInteger('page_from')->nullable()->after('description');
            $table->unsignedSmallInteger('page_to')->nullable()->after('page_from');
            // PDF sayfa sırası = basılı sayfa + kaydırma (kapak ve ön sayfalar).
            $table->smallInteger('page_offset')->nullable()->after('page_to');
            $table->unsignedSmallInteger('page_count')->nullable()->after('page_offset');
            $table->string('text_status', 20)->nullable()->after('page_count');
            $table->timestamp('text_extracted_at')->nullable()->after('text_status');
            $table->timestamp('source_last_modified')->nullable()->after('text_extracted_at');
            $table->unsignedBigInteger('source_size')->nullable()->after('source_last_modified');
            // Kaynak PDF, metin çıkarıldıktan sonra değiştiyse işaretlenir.
            $table->timestamp('source_changed_at')->nullable()->after('source_size');
            $table->boolean('is_active')->default(false)->after('source_changed_at');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');

            $table->unique(['level', 'external_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropUnique(['level', 'external_key']);
            $table->dropIndex(['level']);
            $table->dropColumn([
                'level', 'subject', 'source', 'external_key', 'source_url', 'page_from', 'page_to',
                'page_offset', 'page_count', 'text_status', 'text_extracted_at', 'source_last_modified',
                'source_size', 'source_changed_at', 'is_active', 'sort_order',
            ]);
        });
    }
};
