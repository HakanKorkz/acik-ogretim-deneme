<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Yüklenen ders kitabının nerede durduğu: yerel özel disk (file_path)
     * ya da Cloudinary (source_url + upload_public_id).
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('upload_disk', 20)->nullable()->after('file_path');
            $table->string('upload_public_id')->nullable()->after('upload_disk');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn(['upload_disk', 'upload_public_id']);
        });
    }
};
