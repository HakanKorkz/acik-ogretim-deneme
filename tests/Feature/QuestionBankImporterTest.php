<?php

use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\Question;
use App\Services\Pdf\PdfTextExtractor;
use App\Services\Questions\QuestionBankImporter;
use App\Services\Questions\QuestionGenerator;

/**
 * Doğru yanıtı her zaman A şıkkına koyan sahte üretici bağlar.
 */
function bindAlwaysFirstOptionGenerator(string $prefix = 'Soru'): void
{
    app()->instance(QuestionGenerator::class, new class($prefix) implements QuestionGenerator
    {
        public function __construct(private string $prefix) {}

        public function generate(string $sourceText, int $count = 10): array
        {
            return array_map(fn (int $number): array => [
                'text' => "{$this->prefix} {$number}?",
                'correct_option' => 'A',
                'options' => [
                    ['label' => 'A', 'text' => "Doğru {$number}", 'is_correct' => true],
                    ['label' => 'B', 'text' => "Yanlış B {$number}", 'is_correct' => false],
                    ['label' => 'C', 'text' => "Yanlış C {$number}", 'is_correct' => false],
                    ['label' => 'D', 'text' => "Yanlış D {$number}", 'is_correct' => false],
                ],
            ], range(1, $count));
        }
    });
}

beforeEach(function () {
    $this->mock(PdfTextExtractor::class)
        ->shouldReceive('extract')
        ->andReturn('örnek ders metni');
});

test('şıklar karıştırılır ve doğru şık tek bir harfte toplanmaz', function () {
    bindAlwaysFirstOptionGenerator();
    $course = Course::factory()->create();

    app(QuestionBankImporter::class)->import($course, 40);

    $questions = $course->questions()->with('options')->get();

    expect($questions)->toHaveCount(40)
        ->and($questions->pluck('correct_option')->unique()->count())->toBeGreaterThan(1);

    foreach ($questions as $question) {
        expect($question->options->pluck('label')->all())->toBe(['A', 'B', 'C', 'D'])
            ->and($question->options->firstWhere('is_correct', true)->label)->toBe($question->correct_option)
            ->and($question->options->firstWhere('is_correct', true)->text)->toStartWith('Doğru');
    }
});

test('yeniden üretim geçmiş sınav sonuçlarını bozmaz', function () {
    bindAlwaysFirstOptionGenerator('Eski soru');
    $course = Course::factory()->create();
    app(QuestionBankImporter::class)->import($course, 10);

    config()->set('exam.questions_per_exam', 5);
    $this->post(route('exams.store', $course));
    $session = ExamSession::sole();

    $answers = $session->answers()->with('question.options')->get()
        ->mapWithKeys(fn ($answer): array => [
            $answer->question_id => $answer->question->options->firstWhere('is_correct', true)->id,
        ])->all();

    $this->post(route('exams.submit', $session), ['answers' => $answers]);

    bindAlwaysFirstOptionGenerator('Yeni soru');
    app(QuestionBankImporter::class)->import($course, 10);

    expect($course->questions()->count())->toBe(10)
        ->and($course->questions()->pluck('text')->every(fn (string $text): bool => str_starts_with($text, 'Yeni soru')))->toBeTrue()
        ->and(Question::onlyTrashed()->count())->toBe(5)
        ->and(Question::withTrashed()->count())->toBe(15);

    $session->refresh();

    expect($session->status)->toBe(ExamStatus::Completed)
        ->and($session->answers()->count())->toBe(5);

    $this->get(route('exams.result', $session))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('session.correct_count', 5)
            ->has('review', 5)
            ->where('review.0.is_correct', true)
            ->where('review.0.question', fn (string $text): bool => str_starts_with($text, 'Eski soru')));
});

test('ders listesi sınav uzunluğunu gösterir', function () {
    config()->set('exam.questions_per_exam', 20);
    Course::factory()->create();

    $this->get(route('courses.index'))
        ->assertInertia(fn ($page) => $page->where('questionsPerExam', 20));
});

test('sınav soru bankasından ayarlanan sayıda soru seçer', function () {
    config()->set('exam.questions_per_exam', 20);
    $course = Course::factory()->create();
    Question::factory()->count(40)->withOptions()->for($course)->create();

    $this->post(route('exams.store', $course));

    expect(ExamSession::sole()->total_questions)->toBe(20);
});
