<?php

namespace App\Services\Questions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * OpenRouter (OpenAI uyumlu chat completions) üzerinden yapay zekâ ile soru
 * üreten servis.
 *
 * Kaynak metin, istenen soru sayısına göre bölümlere ayrılır ve her bölüm
 * için ayrı bir istek gönderilir; böylece sorular kitabın tamamına yayılır.
 *
 * Anahtar tanımlı değilse, bir bölümün yanıtı boş/geçersizse veya istek
 * başarısız olursa o bölüm için yerel üreticiye (LocalQuestionGenerator)
 * düşülür; böylece sistem her koşulda çalışmaya devam eder.
 */
class OpenRouterQuestionGenerator implements QuestionGenerator
{
    /** @var array<int, string> */
    private const LABELS = ['A', 'B', 'C', 'D'];

    public function __construct(
        private readonly LocalQuestionGenerator $fallback,
    ) {}

    public function generate(string $sourceText, int $count = 10): array
    {
        if ($count < 1 || trim($sourceText) === '') {
            return [];
        }

        if (blank(config('exam.openrouter.key'))) {
            return $this->fallback->generate($sourceText, $count);
        }

        $perRequest = max(1, (int) config('exam.openrouter.questions_per_request'));
        $sections = $this->split($sourceText, (int) ceil($count / $perRequest));

        /** @var array<string, array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>}> $questions */
        $questions = [];
        $remaining = $count;

        foreach ($sections as $index => $section) {
            // Kalan soruları kalan bölümlere eşit dağıt.
            $wanted = (int) ceil($remaining / (count($sections) - $index));

            $this->collect($questions, $this->generateForSection($section, $wanted), $wanted);

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
     * Tek bir bölüm için yapay zekâdan soru ister; olmazsa yerel üreticiye düşer.
     *
     * @return array<int, array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>}>
     */
    private function generateForSection(string $section, int $count): array
    {
        try {
            return $this->request($section, $count);
        } catch (Throwable $exception) {
            Log::warning('OpenRouter ile soru üretimi başarısız, bu bölüm için yerel üreticiye düşülüyor.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return $this->fallback->generate($section, $count);
    }

    /**
     * Yeni soruları, aynı metinli olanları atlayarak listeye ekler.
     *
     * @param  array<string, array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>}>  $questions
     * @param  array<int, array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>}>  $candidates
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
     * @return array<int, array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>}>
     */
    private function request(string $sourceText, int $count): array
    {
        $config = (array) config('exam.openrouter');

        $response = Http::baseUrl((string) $config['base_url'])
            ->withToken((string) $config['key'])
            ->acceptJson()
            ->connectTimeout((int) $config['connect_timeout'])
            ->timeout((int) $config['timeout'])
            ->retry(2, 200, function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->status() === 429));
            })
            ->withHeaders([
                'HTTP-Referer' => (string) config('app.url'),
                'X-Title' => (string) config('app.name'),
            ])
            ->post('/chat/completions', $this->buildPayload($config, $sourceText, $count))
            ->throw();

        $payload = $response->json();
        $model = data_get($payload, 'model', $config['model']);
        $finishReason = data_get($payload, 'choices.0.finish_reason');
        $content = data_get($payload, 'choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException("OpenRouter boş yanıt (model: {$model}, finish: {$finishReason}).");
        }

        $questions = $this->normalize($content, $count);

        if ($questions === []) {
            throw new RuntimeException(
                "OpenRouter geçerli soru döndürmedi (model: {$model}, finish: {$finishReason}). "
                .'İçerik: '.mb_substr($content, 0, 500),
            );
        }

        return $questions;
    }

    /**
     * OpenRouter istek gövdesini hazırlar. Yedek model zinciri tanımlıysa
     * `models` dizisi, aksi hâlde tek `model` gönderilir.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function buildPayload(array $config, string $sourceText, int $count): array
    {
        $payload = [
            'temperature' => $config['temperature'],
            'max_tokens' => (int) $config['max_tokens'],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->userPrompt($this->sample($sourceText, (int) $config['max_input_chars']), $count)],
            ],
        ];

        $models = is_array($config['models'] ?? null) ? $config['models'] : [];
        $models = array_values(array_filter(
            $models,
            static fn (mixed $model): bool => is_string($model) && $model !== '',
        ));

        if ($models !== []) {
            $payload['models'] = $models;
        } else {
            $payload['model'] = $config['model'];
        }

        // Muhakemeyi kapat (aksi hâlde bazı ücretsiz modeller JSON yerine
        // "düşünme" metni üretip token limitini doldurur).
        if (! ($config['allow_reasoning'] ?? false)) {
            $payload['reasoning'] = ['enabled' => false];
        }

        // Yapılandırılmış JSON modu (destekleyen modellerde JSON'a uyumu artırır).
        if ($config['json_mode'] ?? true) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        return $payload;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        Sen Millî Eğitim Bakanlığı Açık Öğretim Lisesi (AÖL) sınavları için soru
        hazırlayan bir ölçme ve değerlendirme uzmanısın. Verilen metne dayanarak,
        gerçek AÖL sınav kitapçıklarındaki kalıplara uygun, Türkçe çoktan seçmeli
        sorular hazırlarsın. Her sorunun dört şıkkı (A, B, C, D) olur ve yalnızca
        bir şık doğrudur. Bilgiyi yalnızca verilen metinden alırsın; metinde
        olmayan bir bilgiyi uydurmazsın. Yanıtını SADECE geçerli bir JSON olarak
        döndürürsün; açıklama veya metin eklemezsin.
        PROMPT;
    }

    private function userPrompt(string $text, int $count): string
    {
        $schema = '{"questions":[{"text":"...","options":['
            .'{"text":"...","is_correct":true},'
            .'{"text":"...","is_correct":false},'
            .'{"text":"...","is_correct":false},'
            .'{"text":"...","is_correct":false}]}]}';

        return <<<PROMPT
        Aşağıdaki metinden {$count} adet çoktan seçmeli soru üret.

        Kurallar:
        - Şu kalıpları kullan: "Bu metinde/parçada anlatılan kavram aşağıdakilerden hangisidir?",
          "Aşağıdakilerden hangisi ... ile ilgilidir?", "... hakkında aşağıdakilerden hangisi doğrudur?",
          "Bu metinden aşağıdakilerden hangisine ulaşılamaz?", ayrıca tarih/kavram/kişi bilgisi soruları.
        - Çeldiriciler doğru yanıtla aynı türden ve makul olsun (tarih sorusunda diğer tarihler,
          kişi/yer sorusunda benzer özel isimler, kavram sorusunda benzer kavramlar).
        - Çeldiricileri uydurma: gerçekte var olan antlaşma, kişi, yer, eser ve kavramları kullan;
          mümkünse metinde geçen diğer özel isimlerden seç.
        - Doğru şıkkın yeri rastgele olsun; her soruda doğru yanıtı aynı sıraya koyma.
        - Dört şık birbirinden farklı olsun; "hepsi" veya "hiçbiri" seçeneği kullanma.
        - Soru kökü kısa ve net olsun.
        - Yanıtını yalnızca aşağıdaki JSON şemasıyla ver:
        {$schema}

        METİN:
        """
        {$text}
        """
        PROMPT;
    }

    /**
     * Modelden gelen metni, geçerli soru listesine dönüştürür.
     *
     * @return array<int, array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>}>
     */
    private function normalize(string $content, int $count): array
    {
        $data = $this->decode($content);

        if ($data === null) {
            return [];
        }

        $items = $data['questions'] ?? (array_is_list($data) ? $data : []);

        if (! is_array($items)) {
            return [];
        }

        $questions = [];

        foreach ($items as $item) {
            $question = $this->normalizeQuestion($item);

            if ($question === null) {
                continue;
            }

            $questions[] = $question;

            if (count($questions) >= $count) {
                break;
            }
        }

        return $questions;
    }

    /**
     * JSON çözümlemesi; düz JSON, ```json blokları ve gömülü { ... } desteklenir.
     *
     * @return array<mixed>|null
     */
    private function decode(string $content): ?array
    {
        $decoded = json_decode($content, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $stripped = trim((string) preg_replace('/```(?:json)?/i', '', $content));
        $decoded = json_decode($stripped, true);

        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');

        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($content, $start, $end - $start + 1), true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Tek bir ham soruyu doğrular ve standart yapıya çevirir.
     *
     * @return array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>}|null
     */
    private function normalizeQuestion(mixed $item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $text = trim((string) ($item['text'] ?? $item['question'] ?? $item['soru'] ?? ''));

        if ($text === '') {
            return null;
        }

        $rawOptions = $item['options'] ?? $item['şıklar'] ?? null;

        if (! is_array($rawOptions)) {
            return null;
        }

        $rawOptions = array_slice(array_values($rawOptions), 0, 4);

        if (count($rawOptions) < 4) {
            return null;
        }

        $texts = [];
        $correctIndex = null;

        foreach ($rawOptions as $index => $option) {
            $optionText = is_array($option)
                ? (string) ($option['text'] ?? $option['metin'] ?? '')
                : (string) $option;

            $optionText = trim($optionText);

            if ($optionText === '') {
                return null;
            }

            $texts[] = $optionText;

            $isCorrect = is_array($option)
                && filter_var($option['is_correct'] ?? $option['dogru'] ?? false, FILTER_VALIDATE_BOOL);

            if ($isCorrect && $correctIndex === null) {
                $correctIndex = $index;
            }
        }

        // Aynı metinli iki şık olamaz.
        $seen = [];

        foreach ($texts as $optionText) {
            $key = mb_strtolower($optionText, 'UTF-8');

            if (isset($seen[$key])) {
                return null;
            }

            $seen[$key] = true;
        }

        // Doğru şık işaretlenmemişse correct_option/answer harfinden çöz.
        if ($correctIndex === null) {
            $letter = strtoupper(trim((string) ($item['correct_option'] ?? $item['answer'] ?? $item['doğru_cevap'] ?? '')));
            $position = array_search($letter, self::LABELS, true);

            if ($position !== false) {
                $correctIndex = $position;
            }
        }

        if ($correctIndex === null) {
            return null;
        }

        $options = [];

        foreach ($texts as $index => $optionText) {
            $options[] = [
                'label' => self::LABELS[$index],
                'text' => $optionText,
                'is_correct' => $index === $correctIndex,
            ];
        }

        return [
            'text' => $text,
            'correct_option' => self::LABELS[$correctIndex],
            'options' => $options,
        ];
    }

    /**
     * Uzun metinden, belgeye yayılmış temsili bir örnek alır. Böylece yapay
     * zekâ yalnızca ön sözü değil, kitabın genelinden içerik görür.
     */
    private function sample(string $text, int $maxChars): string
    {
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
