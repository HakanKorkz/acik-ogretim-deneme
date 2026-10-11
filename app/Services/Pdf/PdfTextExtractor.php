<?php

namespace App\Services\Pdf;

use RuntimeException;
use Smalot\PdfParser\Parser;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * PDF dosyalarından düz metin çıkarır.
 *
 * Öncelik sırası:
 *  1. Sistemde `pdftotext` (poppler) varsa onunla — hızlı ve karakter kodlaması
 *     açısından güvenilirdir.
 *  2. Aksi hâlde `smalot/pdfparser` ile.
 */
class PdfTextExtractor
{
    private ?bool $pdftotextAvailable = null;

    public function __construct(
        private readonly Parser $parser = new Parser,
    ) {}

    /**
     * Bir PDF dosyasındaki metni çıkarır.
     *
     * @param  string  $path  PDF dosyasının mutlak yolu.
     * @param  int|null  $maxPages  İşlenecek maksimum sayfa (null ise tümü).
     *
     * @throws RuntimeException Dosya bulunamazsa.
     */
    public function extract(string $path, ?int $maxPages = null): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("PDF dosyası okunamadı: {$path}");
        }

        $text = $this->extractWithPdftotext($path, $maxPages);

        if ($text !== null && trim($text) !== '') {
            return $text;
        }

        return $this->extractWithParser($path, $maxPages);
    }

    /**
     * `pdftotext` ile metni çıkarır. Kullanılamıyorsa null döner.
     */
    private function extractWithPdftotext(string $path, ?int $maxPages): ?string
    {
        if ($this->pdftotextAvailable === false) {
            return null;
        }

        try {
            $process = new Process($this->buildCommand($path, $maxPages));
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

        return $process->getOutput();
    }

    /**
     * @return array<int, string>
     */
    private function buildCommand(string $path, ?int $maxPages): array
    {
        $command = ['pdftotext', '-enc', 'UTF-8'];

        if ($maxPages !== null && $maxPages > 0) {
            $command[] = '-f';
            $command[] = '1';
            $command[] = '-l';
            $command[] = (string) $maxPages;
        }

        $command[] = $path;
        $command[] = '-';

        return $command;
    }

    /**
     * `smalot/pdfparser` ile yedek çıkarım.
     */
    private function extractWithParser(string $path, ?int $maxPages): string
    {
        $pdf = $this->parser->parseFile($path);
        $pages = $pdf->getPages();

        if ($maxPages !== null && $maxPages > 0) {
            $pages = array_slice($pages, 0, $maxPages);
        }

        $chunks = [];

        foreach ($pages as $page) {
            $text = trim($page->getText());

            if ($text !== '') {
                $chunks[] = $text;
            }
        }

        return implode("\n", $chunks);
    }
}
