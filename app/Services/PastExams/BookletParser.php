<?php

namespace App\Services\PastExams;

/**
 * Çıkmış sınav kitapçığının sayfa metinlerini ders bölümlerine ve cevap
 * anahtarına ayırır.
 *
 * Kitapçık düzeni:
 *  - 1. sayfa: kapak ve içindekiler (atlanır).
 *  - Soru sayfaları: her ders "(132) TARİH – 2" başlığıyla başlar; bazı uzun
 *    adlar iki satıra bölünür ("(195) SEÇMELİ" / "ORTAK TÜRK TARİHİ – 1").
 *  - "CEVAP ANAHTARI" sayfaları: önce bir sıra ders başlığı, ardından aynı
 *    sırayla her dersin "1. B … 10. A" cevapları gelir.
 */
class BookletParser
{
    private const HEADER = '/^\((\d{3})\)\s*(.*)$/u';

    private const ANSWER = '/^(\d{1,2})\.\s*([A-E])$/u';

    /**
     * Ad tamamlandı mı? ("– 2" gibi bir numarayla biter.)
     */
    private const NAME_COMPLETE = '/[–-]\s*\d+\s*$/u';

    /**
     * @param  array<int, string>  $pages  PDF sırası → sayfa metni (okuma sırası)
     * @param  array<int, list<array{text: string, x: float, y: float, right: float}>>  $answerKeyWords  Cevap anahtarı sayfalarının konumlu kelimeleri
     * @return list<array{code: string, name: string, content: string, answer_key: array<int, string>}>
     */
    public function parse(array $pages, array $answerKeyWords): array
    {
        $questionPages = [];

        foreach ($pages as $number => $content) {
            if ($number !== array_key_first($pages) && mb_stripos($content, 'CEVAP ANAHTARI') === false) {
                $questionPages[] = $content;
            }
        }

        $sections = $this->sections(implode("\n", $questionPages));
        $answerKeys = [];

        foreach ($answerKeyWords as $words) {
            $answerKeys = [...$answerKeys, ...$this->answerKeys($words)];
        }

        $result = [];

        foreach ($sections as $section) {
            $key = $this->matchKey($section['code'], $section['name'], $answerKeys);

            $result[] = [
                'code' => $section['code'],
                'name' => $section['name'],
                'content' => trim($section['content']),
                'answer_key' => $key ?? [],
            ];
        }

        return $result;
    }

    /**
     * Soru sayfalarını ders başlıklarından bölümlere ayırır.
     *
     * @return list<array{code: string, name: string, content: string}>
     */
    private function sections(string $text): array
    {
        $sections = [];
        $current = null;
        $lines = preg_split('/\R/u', $text) ?: [];
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            $line = trim($lines[$index]);

            if (preg_match(self::HEADER, $line, $matches)) {
                [$name, $consumed] = $this->completeName($matches[2], $lines, $index);
                $index += $consumed;

                if ($current !== null) {
                    $sections[] = $current;
                }

                $current = ['code' => $matches[1], 'name' => $name, 'content' => ''];

                continue;
            }

            if ($current !== null) {
                $current['content'] .= $line."\n";
            }
        }

        if ($current !== null) {
            $sections[] = $current;
        }

        return $this->mergeRepeated($sections);
    }

    /**
     * İki satıra bölünmüş ders adını tamamlar.
     *
     * @param  list<string>  $lines
     * @return array{0: string, 1: int} Ad ve tüketilen ek satır sayısı.
     */
    private function completeName(string $name, array $lines, int $index): array
    {
        $name = trim($name);
        $consumed = 0;

        while (! preg_match(self::NAME_COMPLETE, $name) && $consumed < 2 && isset($lines[$index + $consumed + 1])) {
            $next = trim($lines[$index + $consumed + 1]);

            if ($next === '' || preg_match(self::HEADER, $next) || preg_match(self::ANSWER, $next)) {
                break;
            }

            $name = trim("{$name} {$next}");
            $consumed++;
        }

        return [$this->cleanName($name), $consumed];
    }

    /**
     * Aynı ders başlığı birden çok sayfada tekrarlanabilir; içerikleri birleştirilir.
     *
     * @param  list<array{code: string, name: string, content: string}>  $sections
     * @return list<array{code: string, name: string, content: string}>
     */
    private function mergeRepeated(array $sections): array
    {
        $merged = [];

        foreach ($sections as $section) {
            $key = $section['code'].'|'.$this->normalize($section['name']);

            if (isset($merged[$key])) {
                $merged[$key]['content'] .= "\n".$section['content'];
            } else {
                $merged[$key] = $section;
            }
        }

        return array_values($merged);
    }

    /**
     * Cevap anahtarı sayfalarının numaraları (okuma sırası metninden).
     *
     * @param  array<int, string>  $pages
     * @return list<int>
     */
    public function answerKeyPages(array $pages): array
    {
        return array_keys(array_filter(
            $pages,
            static fn (string $content): bool => mb_stripos($content, 'CEVAP ANAHTARI') !== false,
        ));
    }

    /**
     * Bir cevap anahtarı sayfasını, kelimelerin sayfadaki konumlarına göre
     * ders → (soru no → şık) eşlemesine çevirir.
     *
     * Sayfa sütunlardan oluşur; her sütunda ders başlıkları ve altlarında
     * cevaplar vardır. Okuma sırası sütunlar arasında kayabildiği için her
     * cevap, kendi sütunundaki (solunda başlayan en yakın) ve hemen üstündeki
     * başlığa atanır. 1'den başlayıp kesintisiz ilerlemeyen anahtar atılır.
     *
     * @param  list<array{text: string, x: float, y: float, right: float}>  $words
     * @return list<array{code: string, name: string, answers: array<int, string>}>
     */
    private function answerKeys(array $words): array
    {
        $headers = [];
        $answers = [];

        foreach ($words as $index => $word) {
            if (preg_match('/^\((\d{3})\)$/u', $word['text'], $matches)) {
                $headers[] = ['code' => $matches[1], 'x' => $word['x'], 'y' => $word['y'], 'index' => $index];
            }
        }

        foreach ($words as $index => $word) {
            if (! preg_match('/^(\d{1,2})\.$/u', $word['text'], $matches)) {
                continue;
            }

            $letter = $words[$index + 1] ?? null;

            if ($letter === null || ! preg_match('/^[A-E]$/u', $letter['text']) || abs($letter['y'] - $word['y']) > 6) {
                continue;
            }

            $owner = $this->headerAbove($headers, $word['x'], $word['y']);

            if ($owner !== null) {
                $answers[$owner][(int) $matches[1]] = $letter['text'];
            }
        }

        $keys = [];

        foreach ($headers as $position => $header) {
            $headerAnswers = $answers[$position] ?? [];

            if ($this->isComplete($headerAnswers)) {
                ksort($headerAnswers);
                $keys[] = [
                    'code' => $header['code'],
                    'name' => $this->cleanName($this->headerName($words, $header, $headers)),
                    'answers' => $headerAnswers,
                ];
            }
        }

        return $keys;
    }

    /**
     * Konumdaki cevabın başlığı: solunda başlayan en yakın sütundaki,
     * hemen üstündeki başlık.
     *
     * @param  list<array{code: string, x: float, y: float, index: int}>  $headers
     */
    private function headerAbove(array $headers, float $x, float $y): ?int
    {
        $columnX = null;

        foreach ($headers as $header) {
            if ($header['y'] < $y && $header['x'] <= $x + 20 && ($columnX === null || $header['x'] > $columnX)) {
                $columnX = $header['x'];
            }
        }

        if ($columnX === null) {
            return null;
        }

        $owner = null;

        foreach ($headers as $position => $header) {
            if ($header['y'] < $y && abs($header['x'] - $columnX) < 20 && ($owner === null || $header['y'] > $headers[$owner]['y'])) {
                $owner = $position;
            }
        }

        return $owner;
    }

    /**
     * Başlık kodunu izleyen ad: aynı satırdaki kelimeler, ad "– N" ile
     * bitmediyse altındaki satır(lar)daki aynı sütun kelimeleri.
     *
     * @param  list<array{text: string, x: float, y: float, right: float}>  $words
     * @param  array{code: string, x: float, y: float, index: int}  $header
     * @param  list<array{code: string, x: float, y: float, index: int}>  $headers
     */
    private function headerName(array $words, array $header, array $headers): string
    {
        $nextColumnX = INF;

        foreach ($headers as $other) {
            if ($other['x'] > $header['x'] + 20 && abs($other['y'] - $header['y']) < 30) {
                $nextColumnX = min($nextColumnX, $other['x']);
            }
        }

        $name = [];

        for ($index = $header['index'] + 1; isset($words[$index]); $index++) {
            $word = $words[$index];

            if (abs($word['y'] - $header['y']) > 2 || $word['x'] >= $nextColumnX - 5 || preg_match('/^\(\d{3}\)$/u', $word['text'])) {
                break;
            }

            $name[] = $word['text'];
        }

        $text = implode(' ', $name);

        if (preg_match(self::NAME_COMPLETE, $text)) {
            return $text;
        }

        $continuation = array_filter(
            $words,
            static fn (array $word): bool => $word['y'] > $header['y'] + 2
                && $word['y'] < $header['y'] + 25
                && $word['x'] >= $header['x'] - 30
                && $word['x'] < $nextColumnX - 5
                && ! preg_match('/^(\d{1,2}\.|[A-E]|\(\d{3}\))$/u', $word['text']),
        );

        usort($continuation, static fn (array $first, array $second): int => [$first['y'], $first['x']] <=> [$second['y'], $second['x']]);

        foreach ($continuation as $word) {
            $text .= ' '.$word['text'];

            if (preg_match(self::NAME_COMPLETE, $text)) {
                break;
            }
        }

        return $text;
    }

    /**
     * 1'den başlayıp kesintisiz ilerleyen en az 5 cevap.
     *
     * @param  array<int, string>  $answers
     */
    private function isComplete(array $answers): bool
    {
        if (count($answers) < 5) {
            return false;
        }

        ksort($answers);

        return array_keys($answers) === range(1, count($answers));
    }

    /**
     * Bölüme ait cevap anahtarı. Anahtardaki ad kitapçıktakinden biraz farklı
     * yazılabildiği için ("DİN KÜL. VE AHL. BİL." / "DİN KÜL. AHL. BİL.")
     * aynı koddaki adlar sadeleştirilerek karşılaştırılır.
     *
     * @param  list<array{code: string, name: string, answers: array<int, string>}>  $keys
     * @return array<int, string>|null
     */
    private function matchKey(string $code, string $name, array $keys): ?array
    {
        $candidates = array_values(array_filter($keys, static fn (array $key): bool => $key['code'] === $code));

        if (count($candidates) === 1) {
            return $candidates[0]['answers'];
        }

        foreach ($candidates as $candidate) {
            if ($this->normalize($candidate['name']) === $this->normalize($name)) {
                return $candidate['answers'];
            }
        }

        return null;
    }

    private function cleanName(string $name): string
    {
        $name = (string) preg_replace('/\s+/u', ' ', $name);

        return trim((string) preg_replace('/\s*[–-]\s*(\d+)\s*$/u', ' – $1', $name));
    }

    private function normalize(string $name): string
    {
        $name = mb_strtolower($name, 'UTF-8');
        $name = (string) preg_replace('/\bve\b/u', ' ', $name);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $name);
    }
}
