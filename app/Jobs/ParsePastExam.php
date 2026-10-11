<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\PastExam;
use App\Services\PastExams\PastExamImporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Çıkmış sınav kitapçığını ders bölümlerine ayırır ve aktif derslere ait
 * bölümlerin soru aktarımını kuyruğa alır.
 */
class ParsePastExam implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 960;

    public function __construct(
        public PastExam $pastExam,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->pastExam->id;
    }

    public function handle(PastExamImporter $importer): void
    {
        $this->pastExam->forceFill(['status' => ImportStatus::Running])->save();

        $importer->parse($this->pastExam);

        $sections = $this->pastExam->sections()
            ->with('course')
            ->whereNotNull('answer_key')
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', ImportStatus::Done))
            ->get();

        foreach ($sections as $section) {
            if ($section->course?->is_active) {
                $section->forceFill(['status' => ImportStatus::Queued, 'error' => null])->save();
                ImportPastExamSection::dispatch($section);
            } else {
                $section->forceFill([
                    'status' => ImportStatus::Skipped,
                    'error' => $section->course === null ? 'Katalogda eşleşen ders yok.' : 'Ders yayında değil.',
                ])->save();
            }
        }

        $this->pastExam->forceFill(['status' => ImportStatus::Done])->save();
    }

    public function failed(?Throwable $exception): void
    {
        $this->pastExam->forceFill([
            'status' => ImportStatus::Failed,
            'error' => $exception?->getMessage(),
        ])->save();
    }
}
