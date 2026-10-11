<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\PastExamSection;
use App\Services\PastExams\PastExamImporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Bir çıkmış sınav bölümünün sorularını yapay zekâyla yapılandırıp bankaya ekler.
 */
class ImportPastExamSection implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public int $uniqueFor = 960;

    public function __construct(
        public PastExamSection $section,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->section->id;
    }

    public function handle(PastExamImporter $importer): void
    {
        $this->section->forceFill(['status' => ImportStatus::Running])->save();

        $importer->importSection($this->section);
    }

    public function failed(?Throwable $exception): void
    {
        $this->section->forceFill([
            'status' => ImportStatus::Failed,
            'error' => $exception?->getMessage(),
        ])->save();
    }
}
