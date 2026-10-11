<?php

namespace App\Http\Controllers;

use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\ExamAnswer;
use App\Models\ExamSession;
use App\Models\Option;
use App\Services\Questions\QuestionBankImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ExamSessionController extends Controller
{
    /**
     * Tarayıcı oturumunun sahip olduğu sınav ULID'lerinin tutulduğu anahtar.
     */
    private const OWNED_SESSIONS_KEY = 'owned_exam_sessions';

    /**
     * Seçilen ders için yeni bir sınav oturumu başlatır.
     */
    public function store(Request $request, Course $course, QuestionBankImporter $importer): RedirectResponse
    {
        $questions = $importer->randomQuestionsFor($course, (int) config('exam.questions_per_exam'));

        if ($questions->isEmpty()) {
            return back()->with('error', 'Bu derste soru bulunmuyor. Önce soruları üretin.');
        }

        $examSession = DB::transaction(function () use ($course, $questions): ExamSession {
            $examSession = ExamSession::create([
                'course_id' => $course->id,
                'user_id' => auth()->id(),
                'total_questions' => $questions->count(),
                'status' => ExamStatus::InProgress,
                'started_at' => now(),
            ]);

            foreach ($questions->values() as $index => $question) {
                $examSession->answers()->create([
                    'question_id' => $question->id,
                    'position' => $index + 1,
                ]);
            }

            return $examSession;
        });

        $request->session()->push(self::OWNED_SESSIONS_KEY, $examSession->ulid);

        return redirect()->route('exams.show', $examSession);
    }

    /**
     * Sınav ekranını gösterir. Doğru cevaplar istemciye gönderilmez.
     */
    public function show(Request $request, ExamSession $examSession): Response|RedirectResponse
    {
        $this->ensureOwnership($request, $examSession);

        if ($examSession->isCompleted()) {
            return redirect()->route('exams.result', $examSession);
        }

        $examSession->load(['course', 'answers.question.options']);

        return Inertia::render('exams/show', [
            'session' => [
                'id' => $examSession->ulid,
                'course' => $examSession->course->name,
                'course_slug' => $examSession->course->slug,
                'total_questions' => $examSession->total_questions,
                'started_at' => $examSession->started_at?->toIso8601String(),
            ],
            'questions' => $examSession->answers->values()->map(
                static fn (ExamAnswer $answer): array => [
                    'id' => $answer->question->id,
                    'position' => $answer->position,
                    'text' => $answer->question->text,
                    'options' => $answer->question->options->values()->map(
                        static fn (Option $option): array => [
                            'id' => $option->id,
                            'label' => $option->label,
                            'text' => $option->text,
                        ],
                    ),
                ],
            ),
        ]);
    }

    /**
     * Kullanıcının cevaplarını kaydeder, skoru hesaplar ve sonuca yönlendirir.
     */
    public function submit(Request $request, ExamSession $examSession): RedirectResponse
    {
        $this->ensureOwnership($request, $examSession);

        if ($examSession->isCompleted()) {
            return redirect()->route('exams.result', $examSession);
        }

        /** @var array<int, int|null> $submitted */
        $submitted = $request->validate([
            'answers' => ['array'],
            'answers.*' => ['nullable', 'integer', 'exists:options,id'],
        ])['answers'] ?? [];

        $examSession->load('answers');

        $questionIds = $examSession->answers->pluck('question_id');

        /** @var Collection<int, int> $optionToQuestion */
        $optionToQuestion = Option::query()
            ->whereIn('question_id', $questionIds)
            ->pluck('question_id', 'id');

        /** @var Collection<int, int> $correctOptionForQuestion */
        $correctOptionForQuestion = Option::query()
            ->whereIn('question_id', $questionIds)
            ->where('is_correct', true)
            ->pluck('id', 'question_id');

        DB::transaction(function () use ($examSession, $submitted, $optionToQuestion, $correctOptionForQuestion): void {
            $correctCount = 0;

            foreach ($examSession->answers as $answer) {
                $questionId = $answer->question_id;
                $optionId = isset($submitted[$questionId]) ? (int) $submitted[$questionId] : null;

                if ($optionId !== null && (int) $optionToQuestion->get($optionId) !== $questionId) {
                    $optionId = null;
                }

                $isCorrect = $optionId !== null
                    && (int) $correctOptionForQuestion->get($questionId) === $optionId;

                $answer->update([
                    'option_id' => $optionId,
                    'is_correct' => $isCorrect,
                ]);

                if ($isCorrect) {
                    $correctCount++;
                }
            }

            $examSession->update([
                'status' => ExamStatus::Completed,
                'correct_count' => $correctCount,
                'completed_at' => now(),
            ]);
        });

        return redirect()->route('exams.result', $examSession);
    }

    /**
     * Sınav sonucunu ve soru bazlı değerlendirmeyi gösterir.
     */
    public function result(ExamSession $examSession): Response|RedirectResponse
    {
        if (! $examSession->isCompleted()) {
            return redirect()->route('exams.show', $examSession);
        }

        $examSession->load(['course', 'answers.option', 'answers.question.options']);

        $answers = $examSession->answers->values();
        $correctCount = $examSession->correct_count ?? 0;
        $blankCount = $answers->filter(static fn (ExamAnswer $answer): bool => ! $answer->isAnswered())->count();

        return Inertia::render('exams/result', [
            'session' => [
                'id' => $examSession->ulid,
                'course' => $examSession->course->name,
                'course_slug' => $examSession->course->slug,
                'total_questions' => $examSession->total_questions,
                'correct_count' => $correctCount,
                'wrong_count' => $examSession->total_questions - $correctCount - $blankCount,
                'blank_count' => $blankCount,
                'score' => $examSession->scorePercentage(),
                'completed_at' => $examSession->completed_at?->toIso8601String(),
            ],
            'review' => $answers->map(function (ExamAnswer $answer): array {
                $correctOption = $answer->question->options->firstWhere('is_correct', true);

                return [
                    'position' => $answer->position,
                    'question' => $answer->question->text,
                    'is_correct' => $answer->is_correct,
                    'selected_label' => $answer->option?->label,
                    'selected_text' => $answer->option?->text,
                    'correct_label' => $correctOption?->label,
                    'correct_text' => $correctOption?->text,
                    'options' => $answer->question->options->values()->map(
                        static fn (Option $option): array => [
                            'label' => $option->label,
                            'text' => $option->text,
                            'is_correct' => $option->is_correct,
                        ],
                    ),
                ];
            }),
        ]);
    }

    /**
     * Devam eden bir sınavı yalnızca onu başlatan tarayıcı oturumu görebilir
     * ve gönderebilir. Sonuç sayfası ise tahmin edilemeyen ULID bağlantısıyla
     * paylaşılabilir.
     */
    private function ensureOwnership(Request $request, ExamSession $examSession): void
    {
        /** @var array<int, string> $ownedSessions */
        $ownedSessions = $request->session()->get(self::OWNED_SESSIONS_KEY, []);

        abort_unless(in_array($examSession->ulid, $ownedSessions, true), 404);
    }
}
