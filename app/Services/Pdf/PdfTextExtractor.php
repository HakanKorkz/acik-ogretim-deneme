<?php

namespace App\Services\Pdf;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * PDF dosyalarından sayfa sayfa düz metin çıkarır.
 *
 * Öncelik sırası:
 *  1. Sistemde `pdftotext` (poppler) varsa onunla — hızlı ve karakter kodlaması
 *     açısından güvenilirdir. Sayfalar form-feed (\f) ile ayrılır.
 *  2. Aksi hâlde `smalot/pdfparser` ile.
 */
class PdfTextExtractor
{
    private ?bool $pdftotextAvailable = null;

    public function __construct(
        private readonly Parser $parser = new Parser,
    ) {}

    /**
     * Bir PDF dosyasının sayfalarını çıkarır.
     *
     * @param  string  $path  PDF dosyasının mutlak yolu.
     * @return array<int, string> PDF sırası (1'den başlar) → sayfa metni.
     *
     * @throws RuntimeException Dosya okunamazsa.
     */
    public function extractPages(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("PDF dosyası okunamadı: {$path}");
        }

        $pages = $this->extractWithPdftotext($path);

        if ($pages !== null && trim(implode('', $pages)) !== '') {
            return $pages;
        }

        return $this->extractWithParser($path);
    }

    /**
     * Belirtilen sayfalardaki kelimeleri sayfa üzerindeki konumlarıyla
     * (`pdftotext -bbox`) döndürür. Sütunlu tabloları (ör. cevap anahtarı)
     * geometrik olarak okumak için kullanılır. `pdftotext` yoksa null döner.
     *
     * @param  list<int>  $pageNumbers
     * @return array<int, list<array{text: string, x: float, y: float, right: float}>>|null
     */
    public function extractWords(string $path, array $pageNumbers): ?array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("PDF dosyası okunamadı: {$path}");
        }

        $words = [];

        foreach ($pageNumbers as $pageNumber) {
            try {
                $process = new Process(['pdftotext', '-bbox', '-f', (string) $pageNumber, '-l', (string) $pageNumber, $path, '-']);
                $process->setTimeout(120);
                $process->run();
            } catch (Throwable) {
                return null;
            }

            if (! $process->isSuccessful()) {
                return null;
            }

            preg_match_all(
                '/<word xMin="([\d.]+)" yMin="([\d.]+)" xMax="([\d.]+)" yMax="[\d.]+">(.*?)<\/word>/u',
                $process->getOutput(),
                $matches,
                PREG_SET_ORDER,
            );

            $words[$pageNumber] = array_map(static fn (array $match): array => [
                'text' => html_entity_decode($match[4], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'x' => (float) $match[1],
                'y' => (float) $match[2],
                'right' => (float) $match[3],
            ], $matches);
        }

        return $words;
    }

    /**
     * `pdftotext` ile sayfaları çıkarır. Kullanılamıyorsa null döner.
     *
     * @return array<int, string>|null
     */
    private function extractWithPdftotext(string $path): ?array
    {
        if ($this->pdftotextAvailable === false) {
            return null;
        }

        try {
            $process = new Process(['pdftotext', '-enc', 'UTF-8', $path, '-']);
            $process->setTimeout(600);
            $process->run();
            $this->pdftotextAvailable = true;
        } catch (Throwable) {
            $this->pdftotextAvailable = false;

            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        $pages = explode("\f", $process->getOutput());

        // Çıktı son sayfadan sonra da bir \f ile biter.
        if (count($pages) > 1 && trim($pages[array_key_last($pages)]) === '') {
            array_pop($pages);
        }

        return $this->numbered($pages);
    }

    /**
     * `smalot/pdfparser` ile yedek çıkarım.
     *
     * @return array<int, string>
     */
    private function extractWithParser(string $path): array
    {
        $pages = array_map(
            static fn ($page): string => $page->getText(),
            $this->parser->parseFile($path)->getPages(),
        );

        return $this->numbered($pages);
    }

    /**
     * @param  array<int, string>  $pages
     * @return array<int, string>
     */
    private function numbered(array $pages): array
    {
        $numbered = [];

        foreach (array_values($pages) as $index => $content) {
            $numbered[$index + 1] = $content;
        }

        return $numbered;
    }
}
