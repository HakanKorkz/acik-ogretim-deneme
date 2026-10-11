<?php

namespace App\Services\PastExams;

use App\Models\PastExam;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * MEB Açık Öğretim Lisesi "Çıkmış Sınav Soruları" sayfasını okur ve
 * kitapçıkları `past_exams` tablosuyla eşitler (PDF indirmez).
 *
 * Sayfa her sınav dönemi için bir tablo içerir: başlık ("14-15 Mart 2026
 * Tarihlerinde Yapılan Sınavlar"), isteğe bağlı alt başlıklar ("III.Dönem
 * Sınav Soru ve Cevapları") ve oturum bağlantıları.
 */
class PastExamListing
{
    public const URL = 'https://aol.meb.gov.tr/www/cikmis-sinav-sorulari/icerik/15';

    private const BASE_URL = 'https://aol.meb.gov.tr';

    /**
     * @return array{created: int, total: int}
     */
    public function sync(): array
    {
        $response = Http::timeout(60)
            ->withUserAgent('AcikOgretimDeneme/1.0 (+çıkmış sınav listesi)')
            ->get(self::URL);

        if (! $response->successful()) {
            throw new RuntimeException("Çıkmış sınav listesi alınamadı (HTTP {$response->status()}).");
        }

        $entries = $this->parse($response->body());

        if ($entries === []) {
            throw new RuntimeException('Çıkmış sınav listesinde kitapçık bulunamadı; sayfa yapısı değişmiş olabilir.');
        }

        $created = 0;

        foreach ($entries as $entry) {
            $pastExam = PastExam::query()->firstOrNew(['source_url' => $entry['url']]);

            if (! $pastExam->exists) {
                $created++;
            }

            $pastExam->fill([
                'title' => $entry['title'],
                'period' => $entry['period'],
                'session_number' => $entry['session'],
                'sort_order' => $entry['sort_order'],
            ])->save();
        }

        return ['created' => $created, 'total' => count($entries)];
    }

    /**
     * @return list<array{title: string, period: string, session: int|null, url: string, sort_order: int}>
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

        foreach ($this->elements($xpath, '//table') as $table) {
            $headingCell = $this->elements($xpath, './/th', $table)[0] ?? null;
            $heading = $this->clean($headingCell === null ? '' : $headingCell->textContent);

            if ($heading === '') {
                continue;
            }

            $period = trim((string) preg_replace('/\s*Tarihlerinde\s+Yapılan\s+Sınavlar\s*$/iu', '', $heading));
            $subheading = null;

            foreach ($this->elements($xpath, './/td', $table) as $cell) {
                $text = $this->clean($cell->textContent);
                $link = $this->elements($xpath, './/a[contains(@href, ".pdf")]', $cell)[0] ?? null;

                if ($link === null) {
                    if ($text !== '') {
                        $subheading = (string) preg_replace('/\s*Sınav\s+Soru\s+ve\s+Cevapları\s*$/iu', '', $text);
                    }

                    continue;
                }

                $session = preg_match('/(\d)\s*\.\s*oturum/iu', $text, $matches) ? (int) $matches[1] : null;
                $label = trim(implode(' ', array_filter([$period, $subheading])));

                $entries[] = [
                    'title' => $session !== null ? "{$label} – {$session}. oturum" : $label,
                    'period' => $label,
                    'session' => $session,
                    'url' => self::BASE_URL.'/'.ltrim($link->getAttribute('href'), '/'),
                    'sort_order' => count($entries) + 1,
                ];
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
        $elements = [];

        foreach ($nodes === false ? [] : $nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace("\u{00A0}", ' ', $text)));
    }
}
