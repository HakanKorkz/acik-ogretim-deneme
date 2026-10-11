<?php

namespace App\Services\Books;

/**
 * Basılı sayfa numarası ile PDF sayfa sırası arasındaki farkı bulur.
 *
 * Ders kitaplarında kapak, künye ve içindekiler basılı numara almaz; bu
 * yüzden açıklamadaki "83. sayfa" PDF'in 83. sayfası değildir. Sayfaların
 * başında/sonunda tek başına duran numaralar toplanır, (PDF sırası − numara)
 * farkının en sık görülen değeri kaydırma kabul edilir.
 */
class PageOffsetDetector
{
    /**
     * Bir kaydırmanın kabul edilmesi için gereken en az sayfa sayısı.
     */
    private const MIN_VOTES = 3;

    /**
     * @param  array<int, string>  $pages  PDF sırası (1'den) → sayfa metni
     */
    public function detect(array $pages): ?int
    {
        /** @var array<int, int> $votes */
        $votes = [];

        foreach ($pages as $pdfPage => $content) {
            foreach ($this->printedNumbers($content) as $printed) {
                $offset = $pdfPage - $printed;

                if ($offset < 0 || $offset > 60) {
                    continue;
                }

                $votes[$offset] = ($votes[$offset] ?? 0) + 1;
            }
        }

        if ($votes === []) {
            return null;
        }

        arsort($votes);
        $offset = array_key_first($votes);

        return $votes[$offset] >= self::MIN_VOTES ? $offset : null;
    }

    /**
     * Sayfanın ilk ve son satırlarında tek başına duran sayılar.
     *
     * @return list<int>
     */
    private function printedNumbers(string $content): array
    {
        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\R/u', $content) ?: []),
            static fn (string $line): bool => $line !== '',
        ));

        $edges = array_unique([
            ...array_slice($lines, 0, 3),
            ...array_slice($lines, -3),
        ]);

        $numbers = [];

        foreach ($edges as $line) {
            if (preg_match('/^\d{1,3}$/', $line) && (int) $line > 0) {
                $numbers[] = (int) $line;
            }
        }

        return $numbers;
    }
}
