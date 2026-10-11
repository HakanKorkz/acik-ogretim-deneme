<?php

namespace App\Console\Commands;

use App\Jobs\ParsePastExam;
use App\Models\PastExam;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('past-exams:import
    {--latest=3 : En yeni kaç kitapçık içe aktarılsın}
    {--id=* : Belirli kitapçık kimlikleri}')]
#[Description('Çıkmış sınav kitapçıklarını kuyrukta işler: aktif derslerin soruları bankaya eklenir')]
class ImportPastExams extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $ids = array_map('intval', (array) $this->option('id'));

        $pastExams = PastExam::query()
            ->when($ids !== [], fn ($query) => $query->whereKey($ids), fn ($query) => $query->orderBy('sort_order')->limit((int) $this->option('latest')))
            ->get();

        if ($pastExams->isEmpty()) {
            $this->components->error('Kitapçık bulunamadı. Önce `php artisan past-exams:sync` çalıştırın.');

            return self::FAILURE;
        }

        foreach ($pastExams as $pastExam) {
            ParsePastExam::dispatch($pastExam);
            $this->components->info("Kuyruğa alındı: {$pastExam->title}");
        }

        return self::SUCCESS;
    }
}
