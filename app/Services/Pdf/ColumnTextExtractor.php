<?php

namespace App\Services\Pdf;

use DOMDocument;
use RuntimeException;

class ColumnTextExtractor
{
    /**
     * Sütunlar arasına sıçrayan PDF okuma sırası, komşu dersin sorularını
     * bir bölüme katabilir. Soru numaralarının yatay konumlarından sütun
     * başlangıçlarını bulup her sütunu yukarıdan aşağıya okur.
     *
     * @return array<int, string>
     */
    public function extract(string $xml): array
    {
        $document = new DOMDocument;

        if (! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Kitapçığın konumlu metni okunamadı.');
        }

        $pages = [];
        $starts = [];

        foreach ($document->getElementsByTagName('page') as $pageIndex => $page) {
            $width = (float) $page->getAttribute('width');
            $height = (float) $page->getAttribute('height');

            if ($width <= 0) {
                throw new RuntimeException('Kitapçığın sayfa genişliği okunamadı.');
            }

            $lines = [];

            foreach ($page->getElementsByTagName('line') as $line) {
                $words = [];

                foreach ($line->getElementsByTagName('word') as $word) {
                    $words[] = $word->textContent;
                }

                $text = implode(' ', $words);
                $y = (float) $line->getAttribute('yMin');

                if (preg_match('/^\d+\.\s*OTURUM$/u', $text)
                    || ($height > 0 && $y > $height * 0.94 && preg_match('/^\d+$/u', $text))) {
                    continue;
                }

                $lines[] = [
                    'text' => $text,
                    'x' => (float) $line->getAttribute('xMin') / $width,
                    'y' => $y,
                ];
            }

            $text = implode("\n", array_column($lines, 'text'));
            $isQuestionPage = $pageIndex > 0 && mb_stripos($text, 'CEVAP ANAHTARI') === false;

            if ($isQuestionPage) {
                foreach ($lines as $line) {
                    if (preg_match('/^(?:[1-9]|10)\.\s+(?!OTURUM\b)/u', $line['text'])) {
                        $starts[] = $line['x'];
                    }
                }
            }

            $pages[$pageIndex + 1] = $lines;
        }

        sort($starts);
        $minimumCount = count($starts) > 3 ? max(2, (int) (count($starts) * 0.02)) : 1;
        $supportedStarts = array_filter($starts, static function (float $start) use ($starts, $minimumCount): bool {
            $nearby = 0;

            foreach ($starts as $other) {
                if (abs($other - $start) < 0.025) {
                    $nearby++;
                }
            }

            return $nearby >= $minimumCount;
        });
        $columns = [];

        foreach ($supportedStarts as $start) {
            if ($columns === [] || $start - $columns[array_key_last($columns)] > 0.12) {
                $columns[] = $start;
            }
        }

        if (count($columns) > 3 || ($starts !== [] && $columns === [])) {
            throw new RuntimeException('Kitapçığın sütun düzeni güvenle belirlenemedi.');
        }

        $result = [];

        foreach ($pages as $pageNumber => $lines) {
            foreach ($lines as &$line) {
                $line['column'] = 0;

                foreach ($columns as $column => $start) {
                    if ($line['x'] >= $start - 0.02) {
                        $line['column'] = $column;
                    }
                }
            }

            unset($line);

            usort($lines, static fn (array $first, array $second): int => [$first['column'], $first['y'], $first['x']] <=> [$second['column'], $second['y'], $second['x']]);

            $result[$pageNumber] = implode("\n", array_column($lines, 'text'));
        }

        return $result;
    }
}
