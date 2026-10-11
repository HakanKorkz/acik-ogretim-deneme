<?php

namespace App\Services\Catalog;

/**
 * aokul.gov.tr kataloğundaki tek bir ders kitabı.
 */
final readonly class CatalogEntry
{
    public function __construct(
        public string $subject,
        public string $name,
        public string $externalKey,
        public string $pdfUrl,
        public ?string $description,
        public int $sortOrder,
    ) {}

    public function pageRange(): PageRange
    {
        return PageRange::fromDescription($this->description);
    }
}
