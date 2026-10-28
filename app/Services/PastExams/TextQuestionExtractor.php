<?php

namespace App\Services\PastExams;

class TextQuestionExtractor
{
    /**
     * Yalnızca 1'den başlayıp eksiksiz ilerleyen, A–D şıkları sıralı ve
     * görsel atfı olmayan bölümleri kabul eder. Belirsizlikte yapay zekâ
     * ayrıştırıcısına bırakır; kaynakta olmayan içerik üretmez.
     *
     * @return list<array{number: int, passage: string, text: string, options: list<string>, needs_figure: bool}>|null
     */
    public function extract(string $text, int $expectedCount): ?array
    {
        if ($expectedCount < 1 || preg_match('/\bşekil\b|şekle\b|şekildeki|şekilde (?:verilen|gösterilen|belirtilen)|görsel|grafik|tablo|harita|fotoğraf|resim|altı çizili/iu', $text)) {
            return null;
        }

        $text = (string) preg_replace('/(?<=\p{L})-\h*\R\h*(?=\p{Ll})/u', '', $text);
        $questions = [];
        $number = 0;
        $body = [];
        $options = [];
        $label = null;

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (preg_match('/^(\d{1,2})\.\s+(.+)$/u', $line, $matches)
                && (int) $matches[1] === $number + 1
                && ($number === 0 || count($options) === 4)) {
                if ($number > 0) {
                    $questions[] = $this->question($number, $body, $options);
                }

                $number++;
                $body = [$matches[2]];
                $options = [];
                $label = null;

                continue;
            }

            if ($number === 0) {
                return null;
            }

            if (preg_match('/^([A-D])\)\s*(.*)$/u', $line, $matches)) {
                if ($matches[1] !== (['A', 'B', 'C', 'D'][count($options)] ?? null)) {
                    return null;
                }

                $label = $matches[1];
                $options[$label] = $matches[2];
            } elseif ($label !== null) {
                if (preg_match('/^\d{1,2}\.\s/u', $line)) {
                    return null;
                }

                $options[$label] .= ' '.$line;
            } else {
                $body[] = $line;
            }
        }

        if ($number !== $expectedCount || count($options) !== 4) {
            return null;
        }

        $questions[] = $this->question($number, $body, $options);

        foreach ($questions as $question) {
            if (! str_ends_with($question['text'], '?') || in_array('', $question['options'], true)) {
                return null;
            }
        }

        return $questions;
    }

    /**
     * @param  list<string>  $body
     * @param  array<string, string>  $options
     * @return array{number: int, passage: string, text: string, options: list<string>, needs_figure: bool}
     */
    private function question(int $number, array $body, array $options): array
    {
        $stemStart = 0;

        foreach ($body as $index => $line) {
            if (preg_match('/^(Bu (?:metin|ifade|ayet|tanım|durum)|Buna göre)/u', $line)) {
                $stemStart = $index;

                break;
            }
        }

        return [
            'number' => $number,
            'passage' => implode(' ', array_slice($body, 0, $stemStart)),
            'text' => implode(' ', array_slice($body, $stemStart)),
            'options' => array_values(array_map('trim', $options)),
            'needs_figure' => false,
        ];
    }
}
