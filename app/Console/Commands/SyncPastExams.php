<?php

namespace App\Console\Commands;

use App\Services\PastExams\PastExamListing;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('past-exams:sync')]
#[Description('MEB çıkmış sınav kitapçıkları listesini eşitler (PDF indirmez)')]
class SyncPastExams extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PastExamListing $listing): int
    {
        try {
            $summary = $listing->sync();
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("{$summary['total']} kitapçık listelendi, {$summary['created']} tanesi yeni.");

        return self::SUCCESS;
    }
}
