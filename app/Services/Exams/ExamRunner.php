<?php

namespace App\Services\Exams;

use App\Enums\ExamStatus;
use App\Models\ExamAnswer;
use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Models\Option;
use Illuminate\Support\Facades\DB;

/**
 * Sınavın ders bölümlerini yürütür: bölümü başlatır, cevapları kaydeder,
 * bölümü değerlendirir ve son bölüm bitince sınavı tamamlar.
 */
class ExamRunner
{
    /**
     * Süre dolduktan sonra gelen teslimler için tolerans (ağ gecikmesi).
     */
    private const GRACE_SECONDS = 30;

    /**
     * "Başlayınız": bölümün süresi işlemeye başlar.
     */
    public function start(ExamSection $section): void
    {
        if ($section->hasStarted()) {
            return;
        }

        $minutes = $section->examSession->section_minutes ?? (int) config('exam.section_minutes');
        $now = now();

        $section->forceFill([
            'started_at' => $now,
            'expires_at' => $now->addMinutes($minutes),
        ])->save();

        $section->examSession->started_at ??= $now;
        $section->examSession->save();
    }

    /**
     * Tek bir cevabı anında kaydeder (sayfa yenilense de kaybolmaz).
     *
     * @return bool Kaydedildi mi? Bölüm bitmiş ya da süresi dolmuşsa false.
     */
    public function saveAnswer(ExamSection $section, int $questionId, ?int $optionId): bool
    {
        if (! $this->acceptsAnswers($section)) {
            return false;
        }

        /** @var ExamAnswer|null $answer */
        $answer = $section->answers()->where('question_id', $questionId)->first();

        if ($answer === null) {
            return false;
        }

        if ($optionId !== null && ! Option::query()->whereKey($optionId)->where('question_id', $questionId)->exists()) {
            return false;
        }

        $answer->forceFill(['option_id' => $optionId])->save();

        return true;
    }

    /**
     * Bölümü değerlendirir. Son bölümse sınavı da tamamlar.
     *
     * @param  array<int|string, int|string|null>  $submitted  question_id → option_id (son kayıt)
     */
    public function finishSection(ExamSection $section, array $submitted = []): void
    {
        if ($section->isCompleted()) {
            return;
        }

        foreach ($submitted as $questionId => $optionId) {
            $this->saveAnswer($section, (int) $questionId, $optionId === null ? null : (int) $optionId);
        }

        DB::transaction(function () use ($section): void {
            $section->load('answers.question.options');
            $correctCount = 0;

            foreach ($section->answers as $answer) {
                $correctOption = $answer->question->options->firstWhere('is_correct', true);
                $isCorrect = $answer->option_id !== null && $correctOption?->id === $answer->option_id;

                $answer->forceFill(['is_correct' => $isCorrect])->save();

                if ($isCorrect) {
                    $correctCount++;
                }
            }

            $section->forceFill([
                'correct_count' => $correctCount,
                'completed_at' => now(),
            ])->save();

            $this->completeSessionIfFinished($section->examSession);
        });
    }

    /**
     * Süresi dolmuş ama teslim edilmemiş bölümleri kaydedilmiş cevaplarla kapatır.
     */
    public function closeExpiredSections(ExamSession $examSession): void
    {
        foreach ($examSession->sections as $section) {
            if (! $section->isCompleted() && $section->isExpired()) {
                $this->finishSection($section);
            }
        }
    }

    private function acceptsAnswers(ExamSection $section): bool
    {
        if (! $section->hasStarted() || $section->isCompleted() || $section->expires_at === null) {
            return false;
        }

        return now()->lessThanOrEqualTo($section->expires_at->addSeconds(self::GRACE_SECONDS));
    }

    private function completeSessionIfFinished(ExamSession $examSession): void
    {
        $sections = $examSession->sections()->get();

        if ($sections->contains(fn (ExamSection $section): bool => ! $section->isCompleted())) {
            return;
        }

        $examSession->forceFill([
            'status' => ExamStatus::Completed,
            'correct_count' => $sections->sum('correct_count'),
            'completed_at' => now(),
        ])->save();
    }
}
