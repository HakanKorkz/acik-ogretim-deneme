<?php

namespace App\Jobs;

use App\Enums\QuestionGenerationStatus;
use App\Models\Course;
use App\Services\Questions\QuestionBankImporter;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

/**
 * Bir dersin soru bankasını arka planda üretir.
 *
 * PDF ayrıştırma ve yapay zekâ çağrıları dakikalar sürebildiği için HTTP
 * isteği içinde değil, kuyrukta çalıştırılır.
 */
class GenerateCourseQuestions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Uzun süren pahalı bir iş olduğundan otomatik yeniden deneme yapılmaz.
     */
    public int $tries = 1;

    /**
     * Saniye cinsinden zaman aşımı. `queue.connections.database.retry_after`
     * bu değerden büyük olmalıdır.
     */
    public int $timeout = 900;

    /**
     * Aynı ders için kilidin en fazla tutulacağı süre (saniye).
     */
    public int $uniqueFor = 960;

    /**
     * @param  bool  $append  true: mevcut bankaya yeni sorular ekler (sınavlar
     *                        bağımsız kalsın diye); false: bankayı yeniden üretir.
     */
    public function __construct(
        public Course $course,
        public bool $append = false,
    ) {}

    /**
     * Aynı ders için aynı anda yalnızca bir üretim işi kuyrukta olabilir.
     */
    public function uniqueId(): string
    {
        return (string) $this->course->id;
    }

    /**
     * Execute the job.
     */
    public function handle(QuestionBankImporter $importer): void
    {
        $this->course->markQuestionGeneration(QuestionGenerationStatus::Running);

        $created = $this->append
            ? $importer->append($this->course, (int) config('exam.bank_topup_batch'))
            : $importer->import($this->course, (int) config('exam.questions_per_course'));

        if ($created === 0 && ! $this->append) {
            throw new RuntimeException("PDF içeriğinden uygun soru üretilemedi: {$this->course->name}");
        }

        $this->course->markQuestionGeneration(null);
    }

    /**
     * İş başarısız olduğunda ders durumunu günceller.
     */
    public function failed(?Throwable $exception): void
    {
        $this->course->markQuestionGeneration(QuestionGenerationStatus::Failed);
    }
}
