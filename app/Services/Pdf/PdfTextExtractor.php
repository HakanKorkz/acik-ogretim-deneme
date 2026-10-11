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
