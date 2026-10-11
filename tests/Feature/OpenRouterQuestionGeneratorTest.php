<?php

use App\Services\Questions\LocalQuestionGenerator;
use App\Services\Questions\OpenRouterQuestionGenerator;
use App\Services\Questions\QuestionGenerator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * OpenRouter chat completions biçiminde sahte yanıt üretir.
 *
 * @param  array<int, array<string, mixed>>  $questions
 * @return array<string, mixed>
 */
function openRouterFakeResponse(array $questions): array
{
    return [
        'choices' => [
            ['message' => ['content' => json_encode(['questions' => $questions], JSON_UNESCAPED_UNICODE)]],
        ],
    ];
}

test('OpenRouter yanıtını geçerli soru listesine dönüştürür', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', 'test-key');
    config()->set('exam.openrouter.model', 'test/model');
    config()->set('exam.openrouter.models', []);

    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterFakeResponse([
            [
                'text' => 'Bu metinde anlatılan kavram aşağıdakilerden hangisidir?',
                'options' => [
                    ['text' => 'Birinci', 'is_correct' => false],
                    ['text' => 'İkinci', 'is_correct' => true],
                    ['text' => 'Üçüncü', 'is_correct' => false],
                    ['text' => 'Dördüncü', 'is_correct' => false],
                ],
            ],
        ]), 200),
    ]);

    $questions = app(QuestionGenerator::class)->generate('örnek metin', 10);

    expect($questions)->toHaveCount(1)
        ->and($questions[0]['correct_option'])->toBe('B')
        ->and($questions[0]['options'])->toHaveCount(4)
        ->and(collect($questions[0]['options'])->where('is_correct', true))->toHaveCount(1);

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/chat/completions')
        && $request['model'] === 'test/model');
});

test('geçersiz JSON yanıtında yerel üreticiye düşer', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', 'test-key');

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'geçerli json değil']]],
        ], 200),
    ]);

    $questions = app(QuestionGenerator::class)->generate(examGeneratorSampleText(), 10);

    expect($questions)->not->toBeEmpty();

    foreach ($questions as $question) {
        expect($question['options'])->toHaveCount(4);
    }
});

test('sunucu hatasında yerel üreticiye düşer', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', 'test-key');

    Http::fake([
        'openrouter.ai/*' => Http::response('hata', 500),
    ]);

    $questions = app(QuestionGenerator::class)->generate(examGeneratorSampleText(), 10);

    expect($questions)->not->toBeEmpty();
});

test('bağlantı hatasında yerel üreticiye düşer', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', 'test-key');

    Http::fake([
        'openrouter.ai/*' => Http::failedConnection(),
    ]);

    $questions = app(QuestionGenerator::class)->generate(examGeneratorSampleText(), 10);

    expect($questions)->not->toBeEmpty();
});

test('yedek model zinciri tanımlıysa models dizisi gönderilir', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', 'test-key');
    config()->set('exam.openrouter.models', ['a/model:free', 'b/model:free']);

    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterFakeResponse([
            [
                'text' => 'Örnek soru?',
                'options' => [
                    ['text' => 'Bir', 'is_correct' => true],
                    ['text' => 'İki', 'is_correct' => false],
                    ['text' => 'Üç', 'is_correct' => false],
                    ['text' => 'Dört', 'is_correct' => false],
                ],
            ],
        ]), 200),
    ]);

    app(QuestionGenerator::class)->generate('örnek metin', 10);

    Http::assertSent(fn (Request $request): bool => $request['models'] === ['a/model:free', 'b/model:free']
        && ! isset($request['model']));
});

test('yedek zincir yoksa tek model gönderilir', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', 'test-key');
    config()->set('exam.openrouter.models', []);
    config()->set('exam.openrouter.model', 'openrouter/free');

    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterFakeResponse([
            [
                'text' => 'Örnek soru?',
                'options' => [
                    ['text' => 'Bir', 'is_correct' => true],
                    ['text' => 'İki', 'is_correct' => false],
                    ['text' => 'Üç', 'is_correct' => false],
                    ['text' => 'Dört', 'is_correct' => false],
                ],
            ],
        ]), 200),
    ]);

    app(QuestionGenerator::class)->generate('örnek metin', 10);

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'openrouter/free'
        && ! isset($request['models']));
});

test('varsayılan olarak muhakeme kapatılır ve JSON modu istenir', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', 'test-key');
    config()->set('exam.openrouter.model', 'test/model');
    config()->set('exam.openrouter.models', []);
    config()->set('exam.openrouter.allow_reasoning', false);
    config()->set('exam.openrouter.json_mode', true);

    Http::fake([
        'openrouter.ai/*' => Http::response(openRouterFakeResponse([
            [
                'text' => 'Örnek soru?',
                'options' => [
                    ['text' => 'Bir', 'is_correct' => true],
                    ['text' => 'İki', 'is_correct' => false],
                    ['text' => 'Üç', 'is_correct' => false],
                    ['text' => 'Dört', 'is_correct' => false],
                ],
            ],
        ]), 200),
    ]);

    app(QuestionGenerator::class)->generate('örnek metin', 10);

    Http::assertSent(fn (Request $request): bool => $request['reasoning'] === ['enabled' => false]
        && $request['response_format'] === ['type' => 'json_object']);
});

test('anahtar yoksa yerel üretici kullanılır', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', null);

    expect(app(QuestionGenerator::class))->toBeInstanceOf(LocalQuestionGenerator::class);
});

test('sürücü openrouter ve anahtar varsa OpenRouter üreticisi kullanılır', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', 'test-key');

    expect(app(QuestionGenerator::class))->toBeInstanceOf(OpenRouterQuestionGenerator::class);
});

test('varsayılan sürücü yerel üreticidir', function () {
    config()->set('exam.generator', 'local');

    expect(app(QuestionGenerator::class))->toBeInstanceOf(LocalQuestionGenerator::class);
});

/**
 * Birbirinden farklı metinlere sahip sahte sorular üretir.
 *
 * @return array<int, array<string, mixed>>
 */
function openRouterFakeQuestions(string $prefix, int $count): array
{
    return array_map(fn (int $number): array => [
        'text' => "{$prefix} soru {$number}?",
        'options' => [
            ['text' => 'Bir', 'is_correct' => true],
            ['text' => 'İki', 'is_correct' => false],
            ['text' => 'Üç', 'is_correct' => false],
            ['text' => 'Dört', 'is_correct' => false],
        ],
    ], range(1, $count));
}

test('soru bankası metnin farklı bölümlerinden ayrı isteklerle üretilir', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', 'test-key');
    config()->set('exam.openrouter.questions_per_request', 10);

    Http::fake([
        'openrouter.ai/*' => Http::sequence()
            ->push(openRouterFakeResponse(openRouterFakeQuestions('Birinci', 9)))
            ->push(openRouterFakeResponse(openRouterFakeQuestions('İkinci', 8)))
            ->push(openRouterFakeResponse(openRouterFakeQuestions('Üçüncü', 8))),
    ]);

    $text = implode("\n", array_map(
        fn (int $line): string => "Satır {$line} ders kitabının içeriğinden bir paragraf.",
        range(1, 30),
    ));

    $questions = app(QuestionGenerator::class)->generate($text, 25);

    expect($questions)->toHaveCount(25)
        ->and(collect($questions)->pluck('text')->unique())->toHaveCount(25);

    $prompts = collect(Http::recorded())->map(fn (array $pair): string => $pair[0]['messages'][1]['content']);

    expect($prompts)->toHaveCount(3)
        ->and($prompts[0])->toContain('Satır 1 ')->not->toContain('Satır 30 ')
        ->and($prompts[2])->toContain('Satır 30 ')->not->toContain('Satır 1 ');
});

test('başarısız bölüm yerel üretimle tamamlanır, diğer bölümler yapay zekâdan gelir', function () {
    config()->set('exam.generator', 'openrouter');
    config()->set('exam.openrouter.key', 'test-key');
    config()->set('exam.openrouter.questions_per_request', 5);

    Http::fake([
        'openrouter.ai/*' => Http::sequence()
            ->push(openRouterFakeResponse(openRouterFakeQuestions('Yapay', 5)))
            ->push('hata', 500)
            ->push('hata', 500)
            ->push('hata', 500),
    ]);

    $questions = app(QuestionGenerator::class)->generate(examGeneratorSampleText()."\n".examGeneratorSampleText(), 10);

    $texts = collect($questions)->pluck('text');

    expect($texts->filter(fn (string $text): bool => str_starts_with($text, 'Yapay')))->toHaveCount(5)
        ->and($texts->count())->toBeGreaterThan(5);
});
