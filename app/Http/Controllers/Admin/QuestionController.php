<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateQuestionRequest;
use App\Models\Course;
use App\Models\Question;
use App\Services\Questions\QuestionEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    public function index(Request $request, Course $course): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', Rule::enum(QuestionSource::class)],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $search = trim($filters['search'] ?? '');
        $source = $filters['source'] ?? null;

        return Inertia::render('admin/questions/index', [
            'course' => [...$course->only(['slug', 'name']), 'level' => $course->level->value],
            'filters' => ['search' => $search, 'source' => $source],
            'sources' => array_map(static fn (QuestionSource $source): array => [
                'value' => $source->value,
                'label' => $source === QuestionSource::Local ? 'Yerel üretim' : $source->label(),
            ], QuestionSource::cases()),
            'questions' => fn () => $course->questions()
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('text', 'like', "%{$search}%")->orWhere('passage', 'like', "%{$search}%")))
                ->when($source, fn ($query) => $query->where('source', $source))
                ->with(['options', 'pastExamSection.pastExam'])
                ->orderBy('id')->paginate(10)->withQueryString()
                ->through(fn (Question $question): array => $this->row($question, $course)),
        ]);
    }

    public function edit(Course $course, Question $question, QuestionEditor $editor): Response
    {
        $question->load(['options', 'pastExamSection.pastExam']);

        return Inertia::render('admin/questions/edit', [
            'course' => [...$course->only(['slug', 'name']), 'level' => $course->level->value],
            'question' => [...$this->row($question, $course), 'version' => $editor->version($question)],
        ]);
    }

    public function update(UpdateQuestionRequest $request, Course $course, Question $question, QuestionEditor $editor): RedirectResponse
    {
        $options = [];

        foreach (['A', 'B', 'C', 'D'] as $label) {
            $options[$label] = $request->string('options.'.$label)->toString();
        }

        $replacement = $editor->update($question, [
            'version' => $request->string('version')->toString(),
            'passage' => $request->filled('passage') ? $request->string('passage')->toString() : null,
            'text' => $request->string('text')->toString(),
            'correct_option' => $request->string('correct_option')->toString(),
            'source_page' => $request->filled('source_page') ? $request->integer('source_page') : null,
            'options' => $options,
        ]);

        return to_route('admin.courses.questions.edit', [$course, $replacement])
            ->with('success', 'Soru kaydedildi. Önceki sınavların içeriği korundu.');
    }

    /** @return array<string, mixed> */
    private function row(Question $question, Course $course): array
    {
        $section = $question->pastExamSection;

        return [
            'id' => $question->id,
            'passage' => $question->passage,
            'text' => $question->text,
            'correctOption' => $question->correct_option,
            'source' => $question->source === QuestionSource::Local ? 'Yerel üretim' : $question->source->label(),
            'sourcePage' => $question->source_page,
            'sourcePdfPage' => $section === null && $question->source_page !== null ? $question->source_page + ($course->page_offset ?? 0) : null,
            'pastExamTitle' => $section?->pastExam->title,
            'pastExamNumber' => $question->past_exam_number,
            'officialAnswer' => $section?->answer_key[$question->past_exam_number] ?? null,
            'sourceUrl' => $section?->pastExam->source_url ?? $course->source_url,
            'options' => $question->options->map(static fn ($option): array => [
                'label' => $option->label, 'text' => $option->text, 'isCorrect' => $option->is_correct,
            ])->values(),
        ];
    }
}
