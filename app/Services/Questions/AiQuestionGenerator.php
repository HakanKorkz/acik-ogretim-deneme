<?php

namespace App\Services\Questions;

use App\Ai\Agents\QuestionWriter;
use App\Services\Ai\AiModelChain;
use App\Settings\AiSettings;

/**
 * Panelde seçilen yapay zekâ sağlayıcısıyla (OpenRouter, OpenAI, Anthropic)
 * soru üretir.
 *
 * Kaynak metin, istenen soru sayısına göre bölümlere ayrılır ve her bölüm
 * için ayrı bir istek gönderilir; böylece sorular kitabın tamamına yayılır.
 * Her bölümde modeller panelde verilen sırayla denenir. Hiçbiri geçerli
 * yanıt vermezse o bölüm yerel üreticiyle (LocalQuestionGenerator)
 * tamamlanır; sistem her koşulda çalışmaya devam eder.
 *
 * @phpstan-import-type GeneratedQuestion from QuestionGenerator
 */
class AiQuestionGenerator implements QuestionGenerator
{
    /** @var array<int, string> */
    private const LABELS = ['A', 'B', 'C', 'D'];

    public function __construct(
        private readonly AiSettings $settings,
        private readonly AiModelChain $chain,
        private readonly LocalQuestionGenerator $fallback,
        private readonly QuestionQualityGuard $guard,
    ) {}

    public function generate(string $sourceText, int $count = 10, array $styleExamples = []): array
    {
        if ($count < 1 || trim($sourceText) === '') {
            return [];
        }

        if (! $this->chain->isConfigured()) {
            return $this->fallback->generate($sourceText, $count);
        }

        $perRequest = max(1, $this->settings->questions_per_request);
        $sections = $this->split($sourceText, (int) ceil($count / $perRequest));

        /** @var array<string, GeneratedQuestion> $questions */
        $questions = [];
        $remaining = $count;

        foreach ($sections as $index => $section) {
            // Kalan soruları kalan bölümlere eşit dağıt.
            $wanted = (int) ceil($remaining / (count($sections) - $index));

            $this->collect($questions, $this->generateForSection($section, $wanted, $styleExamples), $wanted);

            $remaining = $count - count($questions);

            if ($remaining <= 0) {
                break;
            }
        }

        // Bölümlerden yeterli soru çıkmadıysa tüm metinden yerel üretimle tamamla.
        if ($remaining > 0) {
            $this->collect($questions, $this->fallback->generate($sourceText, $count), $remaining);
        }

        return array_values($questions);
    }

    /**
     * Bir bölüm için modelleri sırayla dener; hiçbiri olmazsa yerel üreticiye düşer.
     *
     * @param  list<string>  $styleExamples
     * @return array<int, GeneratedQuestion>
     */
    private function generateForSection(string $section, int $count, array $styleExamples): array
    {
        $agent = new QuestionWriter($this->sample($section), $count, $styleExamples);

        $questions = $this->chain->run(
            $agent,
            $agent->payload(),
            fn (array $output): array => $this->normalize($output['questions'] ?? null, $count),
        );

        return $questions ?? $this->fallback->generate($section, $count);
    }

    /**
     * Modelin yapılandırılmış yanıtını doğrulayıp standart yapıya çevirir.
     *
     * @return array<int, GeneratedQuestion>
     */
    private function normalize(mixed $items, int $count): array
    {
        if (! is_array($items)) {
            return [];
        }

        $questions = [];

        foreach ($items as $item) {
            $question = $this->normalizeQuestion($item);

            if ($question !== null && $this->guard->passes($question)) {
                $questions[] = $question;
            }

            if (count($questions) >= $count) {
                break;
            }
        }

        return $questions;
    }

    /**
     * @return GeneratedQuestion|null
     */
    private function normalizeQuestion(mixed $item): ?array
    {
        if (! is_array($item) || ! is_array($item['options'] ?? null)) {
            return null;
        }

        $text = trim((string) ($item['text'] ?? ''));
        $optionTexts = array_map(
            static fn (mixed $option): string => trim(is_scalar($option) ? (string) $option : ''),
            array_values($item['options']),
        );
        $correctIndex = $item['correct_index'] ?? null;

        if ($text === '' || count($optionTexts) !== 4 || in_array('', $optionTexts, true)) {
            return null;
        }

        if (! is_int($correctIndex) || $correctIndex < 0 || $correctIndex > 3) {
            return null;
        }

        // Aynı metinli iki şık olamaz.
        $lowered = array_map(static fn (string $option): string => mb_strtolower($option, 'UTF-8'), $optionTexts);

        if (count(array_unique($lowered)) !== 4) {
            return null;
        }

        $options = [];

        foreach ($optionTexts as $index => $optionText) {
            $options[] = [
                'label' => self::LABELS[$index],
                'text' => $optionText,
                'is_correct' => $index === $correctIndex,
            ];
        }

        $passage = trim(is_string($item['passage'] ?? null) ? $item['passage'] : '');
        $page = $item['page'] ?? null;

        return [
            'text' => $text,
            'passage' => $passage === '' ? null : $passage,
            'correct_option' => self::LABELS[$correctIndex],
            'options' => $options,
            'source' => 'ai',
            'source_page' => is_int($page) && $page > 0 ? $page : null,
        ];
    }

    /**
     * Yeni soruları, aynı metinli olanları atlayarak listeye ekler.
     *
     * @param  array<string, GeneratedQuestion>  $questions
     * @param  array<int, GeneratedQuestion>  $candidates
     */
    private function collect(array &$questions, array $candidates, int $limit): void
    {
        $added = 0;

        foreach ($candidates as $candidate) {
            if ($added >= $limit) {
                return;
            }

            $key = mb_strtolower(trim($candidate['text']), 'UTF-8');

            if (isset($questions[$key])) {
                continue;
            }

            $questions[$key] = $candidate;
            $added++;
        }
    }

    /**
     * Metni, satır sınırlarına dikkat ederek yaklaşık eşit uzunlukta
     * bölümlere ayırır.
     *
     * @return array<int, string>
     */
    private function split(string $text, int $parts): array
    {
        $text = trim($text);

        if ($parts <= 1) {
            return [$text];
        }

        $lines = preg_split('/\n/u', $text) ?: [$text];
        $targetLength = (int) ceil(mb_strlen($text) / $parts);
        $sections = [];
        $current = '';
        $currentLength = 0;

        foreach ($lines as $line) {
            $current .= $line."\n";
            $currentLength += mb_strlen($line) + 1;

            if ($currentLength >= $targetLength && count($sections) < $parts - 1) {
                $sections[] = $current;
                $current = '';
                $currentLength = 0;
            }
        }

        if (trim($current) !== '') {
            $sections[] = $current;
        }

        return array_values(array_filter(
            $sections,
            static fn (string $section): bool => trim($section) !== '',
        )) ?: [$text];
    }

    /**
     * Uzun bir bölümden, bölüme yayılmış temsili bir örnek alır.
     */
    private function sample(string $text): string
    {
        $maxChars = (int) config('exam.ai.max_input_chars');

        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{2,}/u', "\n", $text) ?? $text;
        $text = trim($text);

        $length = mb_strlen($text);

        if ($maxChars <= 0 || $length <= $maxChars) {
            return $text;
        }

        $chunks = 8;
        $chunkSize = intdiv($maxChars, $chunks);
        $segments = [];

        for ($index = 0; $index < $chunks; $index++) {
            $start = intdiv($index * $length, $chunks);
            $segments[] = mb_substr($text, $start, $chunkSize);
        }

        return implode("\n...\n", $segments);
    }
}
