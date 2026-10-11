<?php

use App\Models\Course;
use App\Models\Question;
use App\Services\Pdf\PdfTextExtractor;
use App\Services\Questions\QuestionBankImporter;
use App\Services\Questions\QuestionGenerator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

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
                'source' => 'ai',
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

test('şıklar karıştırılır ve doğru şık tek bir harfte toplanmaz', function () {
    bindAlwaysFirstOptionGenerator();
    $course = Course::factory()->withText()->create();

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

test('yeniden üretim geçmiş sınav sonuçlarını ve çıkmış soruları bozmaz', function () {
    config()->set('exam.questions_per_exam', 5);
    Queue::fake();
    actAsVisitor($this);

    bindAlwaysFirstOptionGenerator('Eski soru');
    $course = Course::factory()->withText()->create();
    app(QuestionBankImporter::class)->import($course, 10);
    Question::factory()->count(2)->withOptions()->pastExam()->for($course)->create();

    $exam = startExam($this, [$course], pool: 'generated');
    $this->post(route('exams.start', $exam));
    $this->post(route('exams.finish', $exam), ['answers' => answersFor($exam->sections()->sole(), 5)]);

    bindAlwaysFirstOptionGenerator('Yeni soru');
    app(QuestionBankImporter::class)->import($course, 10);

    expect($course->questions()->where('source', 'ai')->count())->toBe(10)
        ->and($course->questions()->where('source', 'ai')->pluck('text')->every(fn (string $text): bool => str_starts_with($text, 'Yeni soru')))->toBeTrue()
        ->and($course->questions()->where('source', 'past_exam')->count())->toBe(2)
        ->and(Question::onlyTrashed()->count())->toBe(5);

    $this->get(route('exams.result', $exam))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('results.0.correct', 5)
            ->has('results.0.review', 5)
            ->where('results.0.review.0.question', fn (string $text): bool => str_starts_with($text, 'Eski soru')));
});

test('ekleme modu mevcut soruları korur ve aynı metinli soruları atlar', function () {
    bindAlwaysFirstOptionGenerator('Soru');
    $course = Course::factory()->withText()->create();
    app(QuestionBankImporter::class)->import($course, 5);

    $added = app(QuestionBankImporter::class)->append($course, 8);

    expect($added)->toBe(3)
        ->and($course->questions()->count())->toBe(8);
});

test('yapay zekâ sorusunun giriş metni ve kaynak sayfası kaydedilir', function () {
    app()->instance(QuestionGenerator::class, new class implements QuestionGenerator
    {
        public function generate(string $sourceText, int $count = 10): array
        {
            return [[
                'passage' => "I. Birinci öncül\nII. İkinci öncül",
                'text' => 'Yukarıdakilerden hangileri doğrudur?',
                'correct_option' => 'A',
                'options' => [
                    ['label' => 'A', 'text' => 'Yalnız I', 'is_correct' => true],
                    ['label' => 'B', 'text' => 'Yalnız II', 'is_correct' => false],
                    ['label' => 'C', 'text' => 'I ve II', 'is_correct' => false],
                    ['label' => 'D', 'text' => 'Hiçbiri değil', 'is_correct' => false],
                ],
                'source' => 'ai',
                'source_page' => 47,
            ]];
        }
    });

    $course = Course::factory()->withText()->create();
    app(QuestionBankImporter::class)->import($course, 1);

    $question = $course->questions()->sole();

    expect($question->passage)->toContain('Birinci öncül')
        ->and($question->source->value)->toBe('ai')
        ->and($question->source_page)->toBe(47);
});

test('kaynak metin basılı sayfa işaretleriyle gönderilir', function () {
    $received = new ArrayObject;

    app()->instance(QuestionGenerator::class, new class($received) implements QuestionGenerator
    {
        public function __construct(private ArrayObject $received) {}

        public function generate(string $sourceText, int $count = 10): array
        {
            $this->received['text'] = $sourceText;

            return [];
        }
    });

    $course = Course::factory()->withText(3)->create(['page_offset' => 1]);
    app(QuestionBankImporter::class)->import($course, 1);

    expect($received['text'])->toContain("[Sayfa 0]\nSayfa 1.")->toContain("[Sayfa 2]\nSayfa 3.");
});

test('soru üretimine yalnızca sorumluluk aralığındaki sayfalar gönderilir', function () {
    $received = new ArrayObject;

    app()->instance(QuestionGenerator::class, new class($received) implements QuestionGenerator
    {
        public function __construct(private ArrayObject $received) {}

        public function generate(string $sourceText, int $count = 10): array
        {
            $this->received['text'] = $sourceText;

            return [];
        }
    });

    $course = Course::factory()->withText(6)->create(['page_from' => 2, 'page_to' => 3, 'page_offset' => 1]);

    app(QuestionBankImporter::class)->import($course, 5);

    expect($received['text'])->toContain('Sayfa 3.')->toContain('Sayfa 4.')
        ->not->toContain('Sayfa 2.')->not->toContain('Sayfa 5.');
});

test('metni hazır olmayan dersin metni önce çıkarılır', function () {
    bindAlwaysFirstOptionGenerator();

    $this->mock(PdfTextExtractor::class)
        ->shouldReceive('extractPages')
        ->once()
        ->andReturn([1 => 'Birinci sayfa metni.', 2 => 'İkinci sayfa metni.']);

    $course = Course::factory()->create();
    File::ensureDirectoryExists(public_path('dersler'));
    File::put(public_path($course->file_path), '%PDF');

    try {
        $created = app(QuestionBankImporter::class)->import($course, 3);
    } finally {
        File::delete(public_path($course->file_path));
    }

    expect($created)->toBe(3)
        ->and($course->refresh()->hasText())->toBeTrue()
        ->and($course->pages()->count())->toBe(2);
});
