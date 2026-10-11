<?php

namespace App\Services\Catalog;

use App\Enums\CourseLevel;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * MEB Açık Öğretim kitap kataloğunu (aokul.gov.tr) okur.
 *
 * Sayfa bir akordeon: ders grubu (AKAİD) → alt ders (Akaid-1) → PDF linki ve
 * sorumluluk açıklaması. PDF'ler indirilmez; yalnızca adresleri alınır.
 * Site yalnızca HTTP üzerinden yayın yapıyor.
 */
class AokulCatalog
{
    public const BASE_URL = 'http://aokul.gov.tr/Kitaplar/';

    /**
     * Kademenin kataloğunu indirip ayrıştırır.
     *
     * @return list<CatalogEntry>
     *
     * @throws RuntimeException Sayfa alınamazsa ya da hiç ders bulunamazsa.
     */
    public function fetch(CourseLevel $level): array
    {
        $response = Http::timeout(60)
            ->withUserAgent('AcikOgretimDeneme/1.0 (+katalog senkronizasyonu)')
            ->get(self::BASE_URL.'Kitaplar.aspx', ['type' => $level->aokulType()]);

        if (! $response->successful()) {
            throw new RuntimeException("Katalog sayfası alınamadı (HTTP {$response->status()}).");
        }

        $entries = $this->parse($response->body());

        if ($entries === []) {
            throw new RuntimeException('Katalog sayfasında ders bulunamadı; sayfa yapısı değişmiş olabilir.');
        }

        return $entries;
    }

    /**
     * Katalog HTML'ini ders listesine çevirir.
     *
     * @return list<CatalogEntry>
     */
    public function parse(string $html): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $entries = [];
        $sortOrder = 0;

        foreach ($this->elements($xpath, "//div[@id='kitaplar']/div[contains(@class,'accordion-item')]") as $group) {
            $subject = $this->text($xpath, './/h2/button', $group);

            if ($subject === '') {
                continue;
            }

            foreach ($this->elements($xpath, ".//div[contains(@id,'-alt')]/div[contains(@class,'accordion-item')]", $group) as $course) {
                $name = $this->text($xpath, './/h2/button', $course);
                $link = $this->elements($xpath, ".//div[contains(@class,'pdfLink')]//a", $course)[0] ?? null;

                if ($name === '' || $link === null) {
                    continue;
                }

                $href = trim($link->getAttribute('href'));

                if (! str_ends_with(mb_strtolower($href), '.pdf')) {
                    continue;
                }

                $entries[] = new CatalogEntry(
                    subject: $subject,
                    name: $name,
                    externalKey: $this->externalKey($href),
                    pdfUrl: $this->absoluteUrl($href),
                    description: $this->description($link),
                    sortOrder: ++$sortOrder,
                );
            }
        }

        return $entries;
    }

    /**
     * XPath sorgusunun yalnızca element düğümleri.
     *
     * @return list<DOMElement>
     */
    private function elements(DOMXPath $xpath, string $expression, ?DOMElement $context = null): array
    {
        $nodes = $xpath->query($expression, $context);

        if ($nodes === false) {
            return [];
        }

        $elements = [];

        foreach ($nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function text(DOMXPath $xpath, string $expression, DOMElement $context): string
    {
        $node = $this->elements($xpath, $expression, $context)[0] ?? null;

        return $node === null ? '' : trim((string) preg_replace('/\s+/u', ' ', $node->textContent));
    }

    /**
     * "Açıklama:" etiketinden sonraki metin.
     */
    private function description(DOMElement $link): ?string
    {
        $container = $link->parentNode?->parentNode;

        if ($container === null) {
            return null;
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $container->textContent));
        $position = mb_strpos($text, 'Açıklama:');

        if ($position === false) {
            return null;
        }

        $description = trim(mb_substr($text, $position + mb_strlen('Açıklama:')));

        return $description === '' ? null : $description;
    }

    /**
     * "Kitaplar/2/1/1/akaid_1_2.pdf" → "2/1/1". Kademe/grup/ders kimliği;
     * dosya adı değişse de ders aynı kalır.
     */
    private function externalKey(string $href): string
    {
        $path = trim((string) preg_replace('#^Kitaplar/#i', '', $href), '/');
        $directory = dirname($path);

        return $directory === '.' ? $path : $directory;
    }

    /**
     * Göreli adresi, boşluk ve Türkçe karakterleri kodlanmış mutlak adrese çevirir.
     */
    private function absoluteUrl(string $href): string
    {
        $segments = array_map('rawurlencode', explode('/', ltrim($href, '/')));

        return self::BASE_URL.implode('/', $segments);
    }
}
