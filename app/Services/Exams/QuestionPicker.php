<?php

namespace App\Services\Exams;

use App\Enums\QuestionPool;
use App\Models\Course;
use App\Models\ExamAnswer;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Bir sınav için dersin soru bankasından soru seçer.
 *
 * Ziyaretçinin daha önceki sınavlarında gördüğü sorular sona bırakılır;
 * böylece aynı kişi yeni bir sınava girdiğinde mümkün olduğunca hiç
 * görmediği sorularla karşılaşır. Görülmemiş soru yetmezse görülmüşlerden
 * rastgele tamamlanır.
 */
class QuestionPicker
{
    /**
     * @return Collection<int, Question>
     */
    public function pick(Course $course, QuestionPool $pool, ?string $visitorId, int $count): Collection
    {
        $query = $this->poolQuery($course, $pool)->select('questions.*')->with('options');

        if ($visitorId !== null) {
            $query
                ->leftJoinSub($this->seenQuestionIds($visitorId)->distinct(), 'seen', 'seen.question_id', '=', 'questions.id')
                ->orderByRaw('seen.question_id is null desc');
        }

        return $query->inRandomOrder()->limit($count)->get();
    }

    /**
     * Ziyaretçinin bu derste henüz görmediği soru sayısı.
     */
    public function unseenCount(Course $course, QuestionPool $pool, string $visitorId): int
    {
        return $this->poolQuery($course, $pool)
            ->whereNotIn('questions.id', $this->seenQuestionIds($visitorId))
            ->count();
    }

    /**
     * Dersin seçilen havuzdaki soru sayısı.
     */
    public function availableCount(Course $course, QuestionPool $pool): int
    {
        return $this->poolQuery($course, $pool)->count();
    }

    /**
     * @return Builder<Question>
     */
    private function poolQuery(Course $course, QuestionPool $pool): Builder
    {
        return Question::query()
            ->whereBelongsTo($course)
            ->whereIn('source', $pool->sources());
    }

    /**
     * @return Builder<ExamAnswer>
     */
    private function seenQuestionIds(string $visitorId): Builder
    {
        return ExamAnswer::query()
            ->select('exam_answers.question_id')
            ->join('exam_sessions', 'exam_sessions.id', '=', 'exam_answers.exam_session_id')
            ->where('exam_sessions.visitor_id', $visitorId);
    }
}
