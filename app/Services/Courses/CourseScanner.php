<?php

namespace App\Services\Courses;

use App\Models\Course;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class CourseScanner
{
    /**
     * `config('exam.pdf_path')` altındaki PDF dosyalarını tarar ve her biri
     * için bir ders kaydı oluşturur ya da günceller.
     *
     * @return array<int, Course>
     */
    public function scan(): array
    {
        $directory = config('exam.pdf_path');

        if (! is_string($directory) || ! is_dir($directory)) {
            return [];
        }

        $courses = [];

        $finder = Finder::create()
            ->files()
            ->in($directory)
            ->name('*.pdf')
            ->sortByName();

        foreach ($finder as $file) {
            $filename = $file->getFilenameWithoutExtension();

            $courses[] = Course::updateOrCreate(
                ['slug' => Str::slug($filename)],
                [
                    'name' => $this->humanize($filename),
                    'file_path' => 'dersler/'.$file->getFilename(),
                    'description' => 'Kaynak dosya: '.$file->getFilename(),
                ],
            );
        }

        return $courses;
    }

    /**
     * Dosya adını okunabilir bir ders adına çevirir.
     */
    private function humanize(string $filename): string
    {
        $words = preg_split('/[_\-\s]+/u', $filename) ?: [$filename];

        $words = array_values(array_filter($words, static fn (string $word): bool => $word !== ''));

        $words = array_map(
            static function (string $word): string {
                if (mb_strlen($word) > 1 && $word === mb_strtoupper($word, 'UTF-8')) {
                    return $word;
                }

                return mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8')
                    .mb_substr($word, 1, null, 'UTF-8');
            },
            $words,
        );

        return implode(' ', $words);
    }
}
