<?php

namespace App\Services\Books;

use App\Enums\CourseSource;
use App\Enums\TextStatus;
use App\Models\Course;
use App\Services\Pdf\PdfTextExtractor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Ders kitabının metnini sayfa sayfa `book_pages` tablosuna yazar.
 *
 * MEB kataloğundaki kitaplar geçici olarak indirilir ve işlendikten sonra
 * silinir; sunucuda PDF barındırılmaz. Yüklenen PDF'ler diskten okunur.
 */
class CourseTextExtractor
{
    /**
     * Veritabanına tek seferde yazılacak sayfa sayısı.
     */
    private const INSERT_CHUNK = 50;

    public function __construct(
        private readonly PdfTextExtractor $pdf,
        private readonly PageOffsetDetector $offsetDetector,
    ) {}

    /**
     * @return int Kaydedilen sayfa sayısı.
     *
     * @throws RuntimeException PDF alınamaz ya da metin çıkmazsa.
     */
    public function extract(Course $course): int
    {
        $download = $course->source === CourseSource::Aokul ? $this->download($course) : null;
        $path = $download['path'] ?? $course->pdfAbsolutePath();

        if ($path === null) {
            throw new RuntimeException("{$course->name} için PDF kaynağı tanımlı değil.");
        }

        try {
            $pages = array_map(
                static fn (string $content): string => trim((string) preg_replace('/[ \t]+/u', ' ', $content)),
                $this->pdf->extractPages($path),
            );
        } finally {
            if ($download !== null) {
                File::delete($download['path']);
            }
        }

        if (trim(implode('', $pages)) === '') {
            throw new RuntimeException("{$course->name} kitabından metin çıkarılamadı (taranmış görüntü olabilir).");
        }

        DB::transaction(function () use ($course, $pages, $download): void {
            $course->pages()->delete();

            $now = now();
            $rows = [];

            foreach ($pages as $pageNumber => $content) {
                $rows[] = [
                    'course_id' => $course->id,
                    'page_number' => $pageNumber,
                    'content' => $content,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
                DB::table('book_pages')->insert($chunk);
            }

            $course->forceFill([
                'page_count' => count($pages),
                'page_offset' => $course->page_offset ?? $this->offsetDetector->detect($pages),
                'text_status' => TextStatus::Ready,
                'text_extracted_at' => $now,
                'source_last_modified' => $download['last_modified'] ?? $course->source_last_modified,
                'source_size' => $download['size'] ?? $course->source_size,
                'source_changed_at' => null,
            ])->save();
        });

        return count($pages);
    }

    /**
     * MEB sunucusundan PDF'i geçici dosyaya indirir.
     *
     * @return array{path: string, last_modified: CarbonImmutable|null, size: int|null}
     */
    private function download(Course $course): array
    {
        if (blank($course->source_url)) {
            throw new RuntimeException("{$course->name} için kaynak adres tanımlı değil.");
        }

        $directory = storage_path('app/private/tmp');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/'.Str::uuid()->toString().'.pdf';

        $response = Http::timeout(600)
            ->withUserAgent('AcikLiseTest/1.0 (+ders kitabı metni)')
            ->sink($path)
            ->get($course->source_url);

        if (! $response->successful()) {
            File::delete($path);

            throw new RuntimeException("Kitap indirilemedi (HTTP {$response->status()}): {$course->source_url}");
        }

        $lastModified = $response->header('Last-Modified');

        return [
            'path' => $path,
            'last_modified' => $lastModified !== '' ? CarbonImmutable::parse($lastModified) : null,
            'size' => (int) $response->header('Content-Length') ?: null,
        ];
    }
}
