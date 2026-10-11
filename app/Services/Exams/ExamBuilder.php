<?php

namespace App\Services\Exams;

use App\Enums\CourseLevel;
use App\Enums\ExamStatus;
use App\Enums\QuestionPool;
use App\Enums\ResultMode;
use App\Jobs\GenerateCourseQuestions;
use App\Models\Course;
use App\Models\ExamSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seçilen derslerden bir sınav kurar.
 *
 * Her ders kendi bölümündedir (1–10 numaralı) ve dersler sırayla çözülür;
 * bir dersin içine başka dersin sorusu karışmaz. Sorular ziyaretçinin
 * görmediklerinden seçilir, şıkların sırası her sınavda yeniden karıştırılır.
 * Görülmemiş soru azaldıysa derse arka planda yeni sorular eklenir.
 */
class ExamBuilder
{
    public function __construct(
        private readonly QuestionPicker $picker,
    ) {}

    /**
     * @param  Collection<int, Course>  $courses  Kitapçıktaki sırasıyla.
     *
     * @throws RuntimeException Hiçbir derste soru bulunamazsa.
     */
    public function build(
        CourseLevel $level,
        Collection $courses,
        QuestionPool $pool,
        string $visitorId,
        ResultMode $resultMode,
    ): ExamSession {
        $perCourse = (int) config('exam.questions_per_exam');

        $sections = $courses
            ->map(fn (Course $course): array => [
                'course' => $course,
                'questions' => $this->picker->pick($course, $pool, $visitorId, $perCourse),
            ])
            ->filter(fn (array $section): bool => $section['questions']->isNotEmpty())
            ->values();

        if ($sections->isEmpty()) {
            throw new RuntimeException('Seçilen derslerde bu soru türünden soru bulunmuyor.');
        }

        $examSession = DB::transaction(function () use ($level, $sections, $pool, $visitorId, $resultMode): ExamSession {
            $examSession = ExamSession::query()->create([
                'course_id' => $sections->first()['course']->id,
                'level' => $level,
                'visitor_id' => $visitorId,
                'question_pool' => $pool,
                'result_mode' => $resultMode,
                'section_minutes' => (int) config('exam.section_minutes'),
                'total_questions' => $sections->sum(fn (array $section): int => $section['questions']->count()),
                'status' => ExamStatus::InProgress,
            ]);

            $position = 0;

            foreach ($sections as $sectionIndex => $section) {
                $examSection = $examSession->sections()->create([
                    'course_id' => $section['course']->id,
                    'position' => $sectionIndex + 1,
                    'total_questions' => $section['questions']->count(),
                ]);

                foreach ($section['questions']->values() as $index => $question) {
                    $optionOrder = $question->options->pluck('id')->shuffle()->values()->all();

                    $examSession->answers()->create([
                        'exam_section_id' => $examSection->id,
                        'question_id' => $question->id,
                        'position' => ++$position,
                        'number' => $index + 1,
                        'option_order' => $optionOrder,
                    ]);
                }
            }

            return $examSession;
        });

        $this->topUpBanks($courses, $pool, $visitorId);

        return $examSession;
    }

    /**
     * Ziyaretçinin görmediği soru azaldıysa, yapay zekâ sorusu kullanan
     * havuzlarda dersin bankasını arka planda büyütür.
     *
     * @param  Collection<int, Course>  $courses
     */
    private function topUpBanks(Collection $courses, QuestionPool $pool, string $visitorId): void
    {
        if ($pool === QuestionPool::PastExam) {
            return;
        }

        $threshold = (int) config('exam.bank_topup_unseen_threshold');
        $maxBank = (int) config('exam.bank_max_per_course');

        foreach ($courses as $course) {
            if ($course->isGeneratingQuestions() || $course->isExtractingText()) {
                continue;
            }

            if ($this->picker->availableCount($course, QuestionPool::Generated) >= $maxBank) {
                continue;
            }

            if ($this->picker->unseenCount($course, QuestionPool::Generated, $visitorId) < $threshold) {
                GenerateCourseQuestions::dispatch($course, append: true);
            }
        }
    }
}
