<?php

namespace App\Services\Catalog;

use App\Enums\CourseLevel;
use App\Enums\CourseSource;
use App\Models\Course;

/**
 * aokul kataloğunu `courses` tablosuyla eşitler.
 *
 * - Yeni dersler PASİF eklenir; yönetici metni hazırlayıp aktifleştirir.
 * - Katalogdan kalkan dersler silinmez (geçmiş sınavlar onlara bağlı).
 * - Sayfa aralığı yalnızca açıklama değiştiğinde ya da hiç aralık yokken
 *   yeniden okunur; böylece yöneticinin elle yaptığı düzeltme ezilmez.
 * - PDF adresi değişen ve metni çıkarılmış derse "kaynak değişti" işareti konur.
 */
class CatalogSynchronizer
{
    public function __construct(
        private readonly AokulCatalog $catalog,
    ) {}

    /**
     * @return array{created: int, updated: int, unchanged: int}
     */
    public function sync(CourseLevel $level): array
    {
        return $this->apply($level, $this->catalog->fetch($level));
    }

    /**
     * @param  list<CatalogEntry>  $entries
     * @return array{created: int, updated: int, unchanged: int}
     */
    public function apply(CourseLevel $level, array $entries): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        foreach ($entries as $entry) {
            $course = Course::query()
                ->where('level', $level)
                ->where('external_key', $entry->externalKey)
                ->first();

            if ($course === null) {
                $this->create($level, $entry);
                $summary['created']++;

                continue;
            }

            $this->update($course, $entry) ? $summary['updated']++ : $summary['unchanged']++;
        }

        return $summary;
    }

    private function create(CourseLevel $level, CatalogEntry $entry): void
    {
        $range = $entry->pageRange();

        Course::query()->create([
            'level' => $level,
            'name' => $entry->name,
            'subject' => $entry->subject,
            'slug' => Course::uniqueSlug($level, $entry->name),
            'source' => CourseSource::Aokul,
            'external_key' => $entry->externalKey,
            'source_url' => $entry->pdfUrl,
            'description' => $entry->description,
            'page_from' => $range->from,
            'page_to' => $range->to,
            'sort_order' => $entry->sortOrder,
            'is_active' => false,
        ]);
    }

    /**
     * @return bool Kayıt değişti mi?
     */
    private function update(Course $course, CatalogEntry $entry): bool
    {
        $course->fill([
            'name' => $entry->name,
            'subject' => $entry->subject,
            'sort_order' => $entry->sortOrder,
        ]);

        $hasNoRange = $course->page_from === null && $course->page_to === null;

        if ($course->description !== $entry->description || $hasNoRange) {
            $range = $entry->pageRange();

            $course->fill([
                'description' => $entry->description,
                'page_from' => $range->from,
                'page_to' => $range->to,
            ]);
        }

        if ($course->source_url !== $entry->pdfUrl) {
            $course->source_url = $entry->pdfUrl;

            if ($course->text_extracted_at !== null) {
                $course->source_changed_at = now();
            }
        }

        if (! $course->isDirty()) {
            return false;
        }

        $course->save();

        return true;
    }
}
