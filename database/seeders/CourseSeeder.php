<?php

namespace Database\Seeders;

use App\Enums\CourseLevel;
use App\Services\Catalog\CatalogSynchronizer;
use Illuminate\Database\Seeder;
use Throwable;

class CourseSeeder extends Seeder
{
    /**
     * Dersleri MEB aokul.gov.tr kataloğundan oluşturur (PDF indirmez).
     */
    public function run(CatalogSynchronizer $synchronizer): void
    {
        foreach (CourseLevel::cases() as $level) {
            try {
                $summary = $synchronizer->sync($level);
                $this->command->info("{$level->label()}: {$summary['created']} ders eklendi.");
            } catch (Throwable $exception) {
                $this->command->warn("{$level->label()} kataloğu alınamadı: {$exception->getMessage()}");
            }
        }
    }
}
