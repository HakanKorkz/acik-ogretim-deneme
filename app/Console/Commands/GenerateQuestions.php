<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Services\Questions\QuestionBankImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('questions:generate
    {course? : Sadece bu slug ile eşleşen ders için üret}
    {--count= : Ders başına üretilecek soru sayısı (varsayılan: config)}
    {--pages= : PDF için işlenecek maksimum sayfa sayısı}
    {--driver= : Soru üretim sürücüsü (local|openrouter)}
    {--force : Mevcut sorular olsa bile yeniden üret}')]
#[Description('Derslerin PDF kaynaklarından çoktan seçmeli sorular üretir')]
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

        $maxPages = $this->option('pages') !== null
            ? (int) $this->option('pages')
            : config('exam.pdf_max_pages');

        $courses = Course::query()
            ->when(
                $this->argument('course'),
                fn ($query, string $slug) => $query->where('slug', $slug),
            )
            ->orderBy('name')
            ->get();

        if ($courses->isEmpty()) {
            $this->components->error('İşlenecek ders bulunamadı. Önce `php artisan courses:scan` çalıştırın.');

            return self::FAILURE;
        }

        foreach ($courses as $course) {
            $this->components->info("Ders: {$course->name}");

            $absolutePath = $course->pdfAbsolutePath();

            if ($absolutePath === null || ! is_file($absolutePath)) {
                $this->components->warn('PDF bulunamadı: '.($absolutePath ?? $course->name));

                continue;
            }

            if (! $this->option('force') && $course->questions()->exists()) {
                $this->components->warn('Sorular zaten mevcut, atlanıyor (yeniden üretmek için --force kullanın).');

                continue;
            }

            $startedAt = microtime(true);

            $created = $importer->import($course, $count, is_int($maxPages) ? $maxPages : null);

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
