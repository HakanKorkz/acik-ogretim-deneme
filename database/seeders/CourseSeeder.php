<?php

namespace Database\Seeders;

use App\Services\Courses\CourseScanner;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * `public/dersler` dizinindeki PDF dosyalarından ders kayıtları oluşturur.
     */
    public function run(): void
    {
        $courses = app(CourseScanner::class)->scan();

        $this->command->info(count($courses).' ders tarandı.');
    }
}
