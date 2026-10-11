<?php

namespace App\Console\Commands;

use App\Enums\CourseSource;
use App\Models\Course;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('catalog:check-updates')]
#[Description('Metni çıkarılmış MEB kitaplarının PDF\'i değişmiş mi diye bakar (yalnızca HEAD isteği, indirme yapmaz)')]
class CheckCatalogUpdates extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $courses = Course::query()
            ->where('source', CourseSource::Aokul)
            ->whereNotNull('text_extracted_at')
            ->whereNotNull('source_url')
            ->get();

        $changed = 0;

        foreach ($courses as $course) {
            try {
                $response = Http::timeout(30)
                    ->withUserAgent('AcikOgretimDeneme/1.0 (+güncelleme kontrolü)')
                    ->head((string) $course->source_url);
            } catch (Throwable $exception) {
                $this->components->warn("{$course->name}: {$exception->getMessage()}");

                continue;
            }

            if (! $response->successful()) {
                $this->components->warn("{$course->name}: HTTP {$response->status()}");

                continue;
            }

            $lastModified = $response->header('Last-Modified');
            $remoteModified = $lastModified !== '' ? CarbonImmutable::parse($lastModified) : null;
            $remoteSize = (int) $response->header('Content-Length') ?: null;

            $isChanged = ($remoteModified !== null && $course->source_last_modified !== null && ! $remoteModified->equalTo($course->source_last_modified))
                || ($remoteSize !== null && $course->source_size !== null && $remoteSize !== $course->source_size);

            if ($isChanged && $course->source_changed_at === null) {
                $course->forceFill(['source_changed_at' => now()])->save();
                $this->components->info("Güncellenmiş kitap: {$course->name}");
                $changed++;
            }
        }

        $this->components->info("{$courses->count()} kitap kontrol edildi, {$changed} tanesi değişmiş.");

        return self::SUCCESS;
    }
}
