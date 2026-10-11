<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Services\Questions\QuestionBankImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('questions:generate
    {course? : Sadece bu slug ile eşleşen ders için üret}
    {--count= : Ders başına üretilecek soru sayısı (varsayılan: config)}
    {--driver= : Soru üretim sürücüsü (ai|local)}
    {--force : Mevcut sorular olsa bile yeniden üret}')]
#[Description('Derslerin kitap metninden çoktan seçmeli soru bankası üretir (ders verilmezse tüm aktif dersler)')]
class GenerateQuestions extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Sürücü, üretici container'dan çözülmeden önce ayarlanmalıdır.
        if ($this->option('driver') !== null) {
            config(['exam.generator' => (string) $this->option('driver')]);
        }

        /** @var QuestionBankImporter $importer */
        $importer = app(QuestionBankImporter::class);

        $count = $this->option('count') !== null
            ? (int) $this->option('count')
            : (int) config('exam.questions_per_course');

        $slug = $this->argument('course');

        $courses = Course::query()
            ->when(
                $slug,
                fn ($query, string $slug) => $query->where('slug', $slug),
                fn ($query) => $query->active(),
            )
            ->orderBy('level')
            ->orderBy('sort_order')
            ->get();

        if ($courses->isEmpty()) {
            $this->components->error('İşlenecek ders bulunamadı. Önce `php artisan catalog:sync` çalıştırıp dersleri aktifleştirin.');

            return self::FAILURE;
        }

        foreach ($courses as $course) {
            $this->components->info("Ders: {$course->name}");

            if (! $this->option('force') && $course->questions()->exists()) {
                $this->components->warn('Sorular zaten mevcut, atlanıyor (yeniden üretmek için --force kullanın).');

                continue;
            }

            $startedAt = microtime(true);

            try {
                $created = $importer->import($course, $count);
            } catch (Throwable $exception) {
                $this->components->error($exception->getMessage());

                continue;
            }

            $elapsed = round(microtime(true) - $startedAt, 1);

            if ($created === 0) {
                $this->components->warn('Uygun içerik bulunamadı, soru üretilemedi.');
            } else {
                $this->components->info("{$created} soru üretildi ({$elapsed}s).");
            }
        }

        return self::SUCCESS;
    }
}
