<?php

namespace App\Services\Catalog;

/**
 * Ders açıklamasındaki sorumluluk aralığı. Sayfa numaraları kitabın BASILI
 * numaralarıdır; null, sınırın kitabın başı/sonu olduğu anlamına gelir.
 */
final readonly class PageRange
{
    public function __construct(
        public ?int $from = null,
        public ?int $to = null,
    ) {}

    /**
     * Açıklamadan aralığı çıkarır. Desteklenen kalıplar:
     *  - "83. sayfasına kadar (83. sayfa dâhil)"           → 1–83
     *  - "84. sayfasından ders kitabının sonuna kadar"      → 84–son
     *  - "79. sayfadan 134. sayfaya kadar"                  → 79–134
     *  - "80. sayfası (80. sayfa dâhil) ile …"              → 1–80
     *
     * Açıklamada hem ders hem çalışma kitabı geçiyorsa yalnızca ders
     * kitabına ait ilk ifade okunur; gerisini yönetici panelden düzeltir.
     */
    public static function fromDescription(?string $description): self
    {
        if ($description === null || ! preg_match('/sayfa/iu', $description)) {
            return new self;
        }

        $text = mb_strtolower($description, 'UTF-8');

        $workbookPosition = mb_strpos($text, 'çalışma kitab');
        $textbookPosition = mb_strpos($text, 'ders kitab');

        if ($workbookPosition !== false && $textbookPosition !== false && $textbookPosition < $workbookPosition) {
            $text = mb_substr($text, 0, $workbookPosition);
        }

        if (preg_match('/(\d+)\.?\s*sayfa\w*\s+(\d+)\.?\s*sayfa\w*\s+kadar/u', $text, $matches)) {
            return new self((int) $matches[1], (int) $matches[2]);
        }

        if (preg_match('/(\d+)\.?\s*sayfa(?:dan|sından)\b/u', $text, $matches)) {
            return new self(from: (int) $matches[1]);
        }

        if (preg_match('/(\d+)\.?\s*sayfa(?:ya|sına)\s*kadar/u', $text, $matches)) {
            return new self(to: (int) $matches[1]);
        }

        // "80. sayfası (80. sayfa dâhil) ile …": "kadar" yazılmamış ama anlam aynı.
        if (preg_match('/(\d+)\.?\s*sayfa(?:sı)?\s*\(\s*\d+\.?\s*(?:sayfa\s*)?dâhil\)/u', $text, $matches)) {
            return new self(to: (int) $matches[1]);
        }

        return new self;
    }

    public function isWholeBook(): bool
    {
        return $this->from === null && $this->to === null;
    }
}
