<?php

namespace App\Http\Controllers;

use App\Enums\CourseLevel;
use App\Enums\QuestionPool;
use App\Enums\QuestionSource;
use App\Enums\ResultMode;
use App\Http\Middleware\EnsureVisitorId;
use App\Http\Requests\StartExamRequest;
use App\Models\Course;
use App\Models\ExamAnswer;
use App\Models\ExamSection;
use App\Models\ExamSession;
use App\Services\Exams\ExamBuilder;
use App\Services\Exams\ExamRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Sınav akışı: dersler sırayla çözülür. Her dersin bölümü "Başlayınız" ile
 * başlar, kendi süresi işler ve teslim edilince değerlendirilir. Seçime göre
 * her ders bitince ya da en sonda toplu sonuç gösterilir.
 */
class ExamSessionController extends Controller
{
    public function __construct(
        private readonly ExamRunner $runner,
    ) {}

    /**
     * Seçilen derslerle yeni bir sınav kurar.
     */
    public function store(StartExamRequest $request, ExamBuilder $builder): RedirectResponse
    {
        /** @var list<string> $slugs */
        $slugs = $request->validated('courses');

        $courses = Course::query()->whereIn('slug', $slugs)->get()
            ->sortBy(fn (Course $course): int|false => array_search($course->slug, $slugs, true))
            ->values();

        try {
            $examSession = $builder->build(
                CourseLevel::from($request->validated('level')),
                $courses,
                QuestionPool::from($request->validated('pool')),
                EnsureVisitorId::of($request),
                ResultMode::from($request->validated('result_mode')),
            );
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('exams.show', $examSession);
    }

    /**
     * Sıradaki dersin talimat ekranı ya da sınav ekranı.
     */
    public function show(Request $request, ExamSession $examSession): Response|RedirectResponse
    {
        $this->ensureOwnership($request, $examSession);
        $this->runner->closeExpiredSections($examSession->load('sections.course'));

        $section = $examSession->refresh()->load('sections.course')->currentSection();

        if ($examSession->isCompleted() || $section === null) {
            return to_route('exams.result', $examSession);
        }

        if (! $section->hasStarted()) {
            return Inertia::render('exams/instructions', [
                'exam' => $this->examSummary($examSession),
                'section' => $this->sectionSummary($section),
                'isFirstSection' => $section->position === 1,
            ]);
        }

        $section->load('answers.question.options');

        return Inertia::render('exams/show', [
            'exam' => $this->examSummary($examSession),
            'section' => [
                ...$this->sectionSummary($section),
                'remainingSeconds' => $section->remainingSeconds(),
            ],
            'questions' => $section->answers->map(static fn (ExamAnswer $answer): array => [
                'id' => $answer->question->id,
                'number' => $answer->number ?? $answer->position,
                'passage' => $answer->question->passage,
                'text' => $answer->question->text,
                'options' => array_map(
                    static fn (array $option): array => ['id' => $option['id'], 'label' => $option['label'], 'text' => $option['text']],
                    $answer->displayedOptions(),
                ),
                'selectedOptionId' => $answer->option_id,
            ])->values(),
        ]);
    }

    /**
     * "Başlayınız": sıradaki dersin süresini başlatır.
     */
    public function start(Request $request, ExamSession $examSession): RedirectResponse
    {
        $this->ensureOwnership($request, $examSession);

        $section = $examSession->load('sections')->currentSection();

        if ($section !== null) {
            $this->runner->start($section);
        }

        return to_route('exams.show', $examSession);
    }

    /**
     * Tek bir cevabı anında kaydeder.
     */
    public function answer(Request $request, ExamSession $examSession): JsonResponse
    {
        $this->ensureOwnership($request, $examSession);

        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'option_id' => ['nullable', 'integer'],
        ]);

        $section = $examSession->load('sections.examSession')->currentSection();
        $saved = $section !== null && $this->runner->saveAnswer(
            $section,
            (int) $validated['question_id'],
            isset($validated['option_id']) ? (int) $validated['option_id'] : null,
        );

        return response()->json(['saved' => $saved], $saved ? 200 : 409);
    }

    /**
     * Sıradaki dersi teslim eder.
     */
    public function finish(Request $request, ExamSession $examSession): RedirectResponse
    {
        $this->ensureOwnership($request, $examSession);

        /** @var array<int|string, int|string|null> $submitted */
        $submitted = $request->validate([
            'answers' => ['array'],
            'answers.*' => ['nullable', 'integer'],
        ])['answers'] ?? [];

        $section = $examSession->load('sections.examSession')->currentSection();

        if ($section === null || ! $section->hasStarted()) {
            return to_route('exams.show', $examSession);
        }

        $this->runner->finishSection($section, $submitted);

        if ($examSession->refresh()->isCompleted()) {
            return to_route('exams.result', $examSession);
        }

        if ($examSession->result_mode === ResultMode::PerCourse) {
            return to_route('exams.sections.result', [$examSession, $section->position]);
        }

        return to_route('exams.show', $examSession);
    }

    /**
     * Tek bir dersin sonucu ("her ders bitince sonuç" seçeneği).
     */
    public function sectionResult(ExamSession $examSession, int $position): Response
    {
        $section = $examSession->sections()->where('position', $position)->firstOrFail();

        abort_unless($section->isCompleted(), 404);

        $section->load(['course', 'answers.question.options', 'answers.option']);

        return Inertia::render('exams/section-result', [
            'exam' => $this->examSummary($examSession->load('sections.course')),
            'result' => $this->sectionResultData($section),
            'hasNextSection' => ! $examSession->isCompleted(),
        ]);
    }

    /**
     * Sınavın toplu sonucu. Tahmin edilemeyen ULID bağlantısıyla paylaşılabilir.
     */
    public function result(ExamSession $examSession): Response|RedirectResponse
    {
        if (! $examSession->isCompleted()) {
            return to_route('exams.show', $examSession);
        }

        $examSession->load(['sections.course', 'sections.answers.question.options', 'sections.answers.option']);

        $results = $examSession->sections
            ->map(fn (ExamSection $section): array => $this->sectionResultData($section))
            ->values();

        return Inertia::render('exams/result', [
            'exam' => $this->examSummary($examSession),
            'results' => $results,
            'totals' => [
                'courses' => $results->count(),
                'passed' => $results->where('passed', true)->count(),
                'correct' => $results->sum('correct'),
                'questions' => $results->sum('total'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function examSummary(ExamSession $examSession): array
    {
        return [
            'id' => $examSession->ulid,
            'level' => $examSession->level->value,
            'levelLabel' => $examSession->level->label(),
            'pool' => $examSession->question_pool->label(),
            'resultMode' => $examSession->result_mode->value,
            'sectionMinutes' => $examSession->section_minutes,
            'passScore' => (int) config('exam.pass_score'),
            'sections' => $examSession->sections->map(static fn (ExamSection $section): array => [
                'position' => $section->position,
                'course' => $section->course->name,
                'isCompleted' => $section->isCompleted(),
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionSummary(ExamSection $section): array
    {
        return [
            'position' => $section->position,
            'course' => $section->course->name,
            'subject' => $section->course->subject,
            'totalQuestions' => $section->total_questions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionResultData(ExamSection $section): array
    {
        $answers = $section->answers;
        $blank = $answers->filter(static fn (ExamAnswer $answer): bool => ! $answer->isAnswered())->count();
        $correct = $section->correct_count ?? 0;
        $course = $section->course;

        return [
            'position' => $section->position,
            'course' => $course->name,
            'subject' => $course->subject,
            'total' => $section->total_questions,
            'correct' => $correct,
            'wrong' => $section->total_questions - $correct - $blank,
            'blank' => $blank,
            'score' => $section->score(),
            'passed' => $section->isPassed(),
            'review' => $answers->map(static function (ExamAnswer $answer) use ($course): array {
                $question = $answer->question;
                $options = $answer->displayedOptions();
                $correctOption = collect($options)->firstWhere('is_correct', true);
                $selected = collect($options)->firstWhere('id', $answer->option_id);

                return [
                    'number' => $answer->number ?? $answer->position,
                    'passage' => $question->passage,
                    'question' => $question->text,
                    'isCorrect' => $answer->is_correct,
                    'selectedLabel' => $selected['label'] ?? null,
                    'correctLabel' => $correctOption['label'] ?? null,
                    'options' => array_map(
                        static fn (array $option): array => ['label' => $option['label'], 'text' => $option['text'], 'isCorrect' => $option['is_correct']],
                        $options,
                    ),
                    'source' => $question->source->label(),
                    'isPastExam' => $question->source === QuestionSource::PastExam,
                    'sourcePage' => $question->source_page,
                    'bookUrl' => $question->source_page !== null && filled($course->source_url)
                        ? $course->source_url.'#page='.($question->source_page + ($course->page_offset ?? 0))
                        : null,
                ];
            })->values(),
        ];
    }

    /**
     * Devam eden sınavı yalnızca onu başlatan tarayıcı (ziyaretçi kimliği)
     * görebilir ve çözebilir. Sonuç sayfaları ULID bağlantısıyla paylaşılabilir.
     */
    private function ensureOwnership(Request $request, ExamSession $examSession): void
    {
        abort_unless($examSession->visitor_id === EnsureVisitorId::of($request), 404);
    }
}
