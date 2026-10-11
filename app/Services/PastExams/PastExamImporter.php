<?php

namespace App\Services\PastExams;

use App\Ai\Agents\PastQuestionExtractor;
use App\Enums\ImportStatus;
use App\Enums\QuestionSource;
use App\Models\PastExam;
use App\Models\PastExamSection;
use App\Services\Ai\AiModelChain;
use App\Services\Pdf\PdfTextExtractor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Çıkmış sınav kitapçıklarını soru bankasına aktarır.
 *
 * 1. `parse`: Kitapçık geçici olarak indirilir; ders bölümleri ve cevap
 *    anahtarı deterministik olarak ayrıştırılıp katalogdaki derslerle
 *    eşlenir. PDF saklanmaz.
 * 2. `importSection`: Bir bölümün ham metni yapay zekâyla soru soru
 *    yapılandırılır; doğru şık her zaman kitapçığın cevap anahtarından gelir.
 */
class PastExamImporter
{
    public function __construct(
        private readonly PdfTextExtractor $pdf,
        private readonly BookletParser $parser,
        private readonly ExamCourseMatcher $matcher,
        private readonly AiModelChain $chain,
    ) {}

    /**
     * @return int Bulunan ders bölümü sayısı.
     */
    public function parse(PastExam $pastExam): int
    {
        $path = $this->download($pastExam);

        try {
            $pages = $this->pdf->extractPages($path);
            $words = $this->pdf->extractWords($path, $this->parser->answerKeyPages($pages));
        } finally {
            File::delete($path);
        }

        if ($words === null) {
            throw new RuntimeException('Cevap anahtarı okunamadı: sunucuda `pdftotext` (poppler-utils) kurulu olmalı.');
        }

        $sections = $this->parser->parse($pages, $words);

        if ($sections === []) {
            throw new RuntimeException('Kitapçıkta ders bölümü bulunamadı; biçim farklı olabilir.');
        }

        DB::transaction(function () use ($pastExam, $sections): void {
            foreach ($sections as $section) {
                $model = $pastExam->sections()->firstOrNew(['code' => $section['code'], 'name' => $section['name']]);

                $model->fill([
                    'content' => $section['content'],
                    'answer_key' => $section['answer_key'] === [] ? null : $section['answer_key'],
                    'course_id' => $this->matcher->match($section['name'])?->id,
                ])->save();
            }

            $pastExam->forceFill(['parsed_at' => now(), 'error' => null])->save();
        });

        return count($sections);
    }

    /**
     * Bölümün sorularını bankaya ekler. Daha önce aktarılmış sorular atlanır.
     *
     * @return int Eklenen soru sayısı.
     */
    public function importSection(PastExamSection $section): int
    {
        if ($section->course_id === null) {
            throw new RuntimeException("{$section->heading()}: katalogda karşılığı olan ders bulunamadı.");
        }

        $answerKey = $section->answer_key ?? [];

        if ($answerKey === []) {
            throw new RuntimeException("{$section->heading()}: cevap anahtarı okunamadı.");
        }

        if (! $this->chain->isConfigured()) {
            throw new RuntimeException('Yapay zekâ ayarlanmamış; çıkmış sorular yapılandırılamıyor.');
        }

        $agent = new PastQuestionExtractor($section->heading(), $section->content);

        $questions = $this->chain->run(
            $agent,
            $agent->payload(),
            fn (array $output): array => $this->usableQuestions($output['questions'] ?? null, $answerKey),
            timeout: 180,
        );

        if ($questions === null) {
            throw new RuntimeException("{$section->heading()}: yapay zekâ soruları ayrıştıramadı.");
        }

        $existing = $section->questions()->pluck('past_exam_number')->all();

        $created = DB::transaction(function () use ($section, $questions, $answerKey, $existing): int {
            $created = 0;

            foreach ($questions as $question) {
                if (in_array($question['number'], $existing, true)) {
                    continue;
                }

                $correct = $answerKey[$question['number']] ?? $answerKey[(string) $question['number']];

                $model = $section->questions()->create([
                    'course_id' => $section->course_id,
                    'passage' => $question['passage'],
                    'text' => $question['text'],
                    'correct_option' => $correct,
                    'source' => QuestionSource::PastExam,
                    'past_exam_number' => $question['number'],
                ]);

                $model->options()->createMany(array_map(
                    static fn (string $label, string $text): array => ['label' => $label, 'text' => $text, 'is_correct' => $label === $correct],
                    ['A', 'B', 'C', 'D'],
                    $question['options'],
                ));

                $created++;
            }

            return $created;
        });

        $section->forceFill([
            'status' => ImportStatus::Done,
            'imported_count' => $section->questions()->count(),
            'skipped_count' => max(0, count($answerKey) - $section->questions()->count()),
            'error' => null,
        ])->save();

        return $created;
    }

    /**
     * Yapay zekâ çıktısından, cevap anahtarında karşılığı olan, dört şıklı
     * ve görsel gerektirmeyen soruları seçer.
     *
     * @param  array<int|string, string>  $answerKey
     * @return list<array{number: int, passage: string|null, text: string, options: list<string>}>
     */
    private function usableQuestions(mixed $items, array $answerKey): array
    {
        if (! is_array($items)) {
            return [];
        }

        $questions = [];

        foreach ($items as $item) {
            if (! is_array($item) || ($item['needs_figure'] ?? false) === true) {
                continue;
            }

            $number = $item['number'] ?? null;
            $text = trim(is_string($item['text'] ?? null) ? $item['text'] : '');
            $passage = trim(is_string($item['passage'] ?? null) ? $item['passage'] : '');
            $options = is_array($item['options'] ?? null)
                ? array_map(static fn (mixed $option): string => trim(is_scalar($option) ? (string) $option : ''), array_values($item['options']))
                : [];

            // Model şıkları "A) ..." biçiminde döndürebilir; harf öneki atılır.
            $options = array_map(static fn (string $option): string => trim((string) preg_replace('/^[A-E]\)\s*/u', '', $option)), $options);

            if (! is_int($number) || ! isset($answerKey[$number]) && ! isset($answerKey[(string) $number])) {
                continue;
            }

            if ($text === '' || count($options) !== 4 || in_array('', $options, true)) {
                continue;
            }

            $questions[$number] = [
                'number' => $number,
                'passage' => $passage === '' ? null : $passage,
                'text' => $text,
                'options' => $options,
            ];
        }

        ksort($questions);

        return array_values($questions);
    }

    private function download(PastExam $pastExam): string
    {
        $directory = storage_path('app/private/tmp');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/'.Str::uuid()->toString().'.pdf';

        $response = Http::timeout(300)
            ->withUserAgent('AcikOgretimDeneme/1.0 (+çıkmış sınav kitapçığı)')
            ->sink($path)
            ->get($pastExam->source_url);

        if (! $response->successful()) {
            File::delete($path);

            throw new RuntimeException("Kitapçık indirilemedi (HTTP {$response->status()}).");
        }

        return $path;
    }
}
