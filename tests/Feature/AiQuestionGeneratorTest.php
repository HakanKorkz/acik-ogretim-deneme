<?php

use App\Ai\Agents\QuestionWriter;
use App\Services\Questions\AiQuestionGenerator;
use App\Services\Questions\LocalQuestionGenerator;
use App\Services\Questions\QuestionGenerator;
use App\Settings\AiSettings;
use Laravel\Ai\Prompts\AgentPrompt;

/**
 * Yapılandırılmış çıktı biçiminde sahte sorular.
 *
 * @return array{questions: array<int, array{text: string, options: list<string>, correct_index: int}>}
 */
function fakeWriterOutput(string $prefix, int $count, int $correctIndex = 1): array
{
    return ['questions' => array_map(fn (int $number): array => [
        'text' => "{$prefix} soru {$number}?",
        'options' => ['Bir', 'İki', 'Üç', 'Dört'],
        'correct_index' => $correctIndex,
    ], range(1, $count))];
}

beforeEach(function () {
    config()->set('exam.generator', 'ai');

    AiSettings::fake([
        'provider' => 'openrouter',
        'openrouter_key' => 'test-key',
        'openai_key' => null,
        'anthropic_key' => null,
        'models' => ['birinci/model', 'ikinci/model'],
        'questions_per_request' => 10,
    ]);
});

test('yapılandırılmış yanıtı geçerli soru listesine dönüştürür', function () {
    QuestionWriter::fake([fakeWriterOutput('Örnek', 3, correctIndex: 2)]);

    $questions = app(QuestionGenerator::class)->generate('örnek ders metni', 3);

    expect($questions)->toHaveCount(3)
        ->and($questions[0]['correct_option'])->toBe('C')
        ->and(collect($questions[0]['options'])->pluck('label')->all())->toBe(['A', 'B', 'C', 'D'])
        ->and(collect($questions[0]['options'])->where('is_correct', true))->toHaveCount(1);
});

test('geçersiz yanıt veren modelden sonra sıradaki model denenir', function () {
    QuestionWriter::fake([
        ['questions' => [['text' => 'Eksik şıklı?', 'options' => ['A', 'B'], 'correct_index' => 0]]],
        fakeWriterOutput('İkinci', 2),
    ]);

    $questions = app(QuestionGenerator::class)->generate('örnek ders metni', 2);

    expect(collect($questions)->pluck('text')->all())->toBe(['İkinci soru 1?', 'İkinci soru 2?']);

    QuestionWriter::assertPromptedTimes(2);
});

test('hiçbir model geçerli yanıt vermezse yerel üreticiye düşer', function () {
    QuestionWriter::fake([['questions' => []], ['questions' => []]]);

    $questions = app(QuestionGenerator::class)->generate(examGeneratorSampleText(), 5);

    expect($questions)->not->toBeEmpty();

    foreach ($questions as $question) {
        expect($question['options'])->toHaveCount(4);
    }
});

test('soru bankası metnin farklı bölümlerinden ayrı isteklerle üretilir', function () {
    QuestionWriter::fake([
        fakeWriterOutput('Birinci', 9),
        fakeWriterOutput('İkinci', 8),
        fakeWriterOutput('Üçüncü', 8),
    ]);

    $text = implode("\n", array_map(
        fn (int $line): string => "Satır {$line} ders kitabının içeriğinden bir paragraf.",
        range(1, 30),
    ));

    $questions = app(QuestionGenerator::class)->generate($text, 25);

    expect($questions)->toHaveCount(25)
        ->and(collect($questions)->pluck('text')->unique())->toHaveCount(25);

    QuestionWriter::assertPromptedTimes(3);
    QuestionWriter::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Satır 1 ') && ! $prompt->contains('Satır 30 '));
    QuestionWriter::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Satır 30 ') && ! $prompt->contains('Satır 1 '));
});

test('anahtar ya da model yoksa yapay zekâya hiç istek gönderilmez', function () {
    QuestionWriter::fake();

    AiSettings::fake([
        'provider' => 'anthropic',
        'openrouter_key' => 'test-key',
        'openai_key' => null,
        'anthropic_key' => null,
        'models' => ['claude-haiku-5-5'],
        'questions_per_request' => 10,
    ]);
    config()->set('exam.ai.keys.anthropic', null);

    $questions = app(QuestionGenerator::class)->generate(examGeneratorSampleText(), 5);

    expect($questions)->not->toBeEmpty();

    QuestionWriter::assertNeverPrompted();
});

test('panelde anahtar yoksa .env anahtarı kullanılır', function () {
    AiSettings::fake([
        'provider' => 'openai',
        'openrouter_key' => null,
        'openai_key' => null,
        'anthropic_key' => null,
        'models' => ['gpt-5-mini'],
        'questions_per_request' => 10,
    ]);
    config()->set('exam.ai.keys.openai', 'env-key');

    $settings = app(AiSettings::class);

    expect($settings->configured())->toBeTrue()
        ->and($settings->keyFor($settings->activeProvider()))->toBe('env-key')
        ->and($settings->hasPanelKey($settings->activeProvider()))->toBeFalse();
});

test('sürücü seçimine göre doğru üretici bağlanır', function () {
    config()->set('exam.generator', 'local');
    expect(app(QuestionGenerator::class))->toBeInstanceOf(LocalQuestionGenerator::class);

    config()->set('exam.generator', 'ai');
    expect(app(QuestionGenerator::class))->toBeInstanceOf(AiQuestionGenerator::class);
});
