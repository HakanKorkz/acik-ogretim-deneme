<?php

namespace App\Console\Commands;

use App\Services\Courses\CourseScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('courses:scan')]
#[Description('public/dersler dizinindeki PDF dosyalarını tarayıp ders kayıtlarını oluşturur')]
class ScanCourses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CourseScanner $scanner): int
    {
        $directory = config('exam.pdf_path');

        if (! is_string($directory) || ! is_dir($directory)) {
            $this->components->error("PDF dizini bulunamadı: {$directory}");

            return self::FAILURE;
        }

        $courses = $scanner->scan();

        if ($courses === []) {
            $this->components->warn('Hiç PDF dosyası bulunamadı.');

            return self::SUCCESS;
        }

        $this->table(
            ['Ders', 'Slug', 'Dosya'],
            array_map(
                static fn ($course): array => [$course->name, $course->slug, $course->file_path],
                $courses,
            ),
        );

        $this->components->info(count($courses).' ders senkronize edildi.');

        return self::SUCCESS;
    }
}
