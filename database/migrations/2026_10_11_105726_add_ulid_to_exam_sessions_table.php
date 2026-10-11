<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sınav oturumları URL'de tahmin edilemeyen bir ULID ile tanımlanır.
     */
    public function up(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->ulid()->nullable()->after('id');
        });

        DB::table('exam_sessions')->whereNull('ulid')->orderBy('id')->each(function (object $session): void {
            DB::table('exam_sessions')
                ->where('id', $session->id)
                ->update(['ulid' => (string) Str::ulid()]);
        });

        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->unique('ulid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_sessions', function (Blueprint $table) {
            $table->dropUnique(['ulid']);
            $table->dropColumn('ulid');
        });
    }
};
