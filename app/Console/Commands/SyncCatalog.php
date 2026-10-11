<?php

namespace App\Console\Commands;

use App\Enums\CourseLevel;
use App\Services\Catalog\CatalogSynchronizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('catalog:sync {level? : lise | ortaokul (verilmezse ikisi de)}')]
#[Description('MEB aokul.gov.tr ders kitabı kataloğunu dersler tablosuyla eşitler (PDF indirmez)')]
class SyncCatalog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CatalogSynchronizer $synchronizer): int
    {
        $argument = $this->argument('level');
        $levels = $argument === null ? CourseLevel::cases() : array_filter([CourseLevel::tryFrom((string) $argument)]);

        if ($levels === []) {
            $this->components->error("Geçersiz kademe: {$argument} (lise | ortaokul)");

            return self::FAILURE;
        }

        $failed = false;

        foreach ($levels as $level) {
            try {
                $summary = $synchronizer->sync($level);
            } catch (Throwable $exception) {
                $this->components->error("{$level->label()}: {$exception->getMessage()}");
                $failed = true;

                continue;
            }

            $this->components->info(sprintf(
                '%s: %d yeni, %d güncellendi, %d değişmedi.',
                $level->label(),
                $summary['created'],
                $summary['updated'],
                $summary['unchanged'],
            ));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
