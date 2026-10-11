<?php

namespace App\Jobs;

use App\Enums\TextStatus;
use App\Models\Course;
use App\Services\Books\CourseTextExtractor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Ders kitabını indirip metnini sayfa sayfa kaydeder (arka planda).
 */
class ExtractCourseText implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * 180 MB'lık bir kitabı indirip ayrıştırmak birkaç dakika sürebilir.
     */
    public int $timeout = 900;

    public int $uniqueFor = 960;

    public function __construct(
        public Course $course,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->course->id;
    }

    /**
     * Execute the job.
     */
    public function handle(CourseTextExtractor $extractor): void
    {
        $this->course->forceFill(['text_status' => TextStatus::Running])->save();

        $extractor->extract($this->course);
    }

    public function failed(?Throwable $exception): void
    {
        $this->course->forceFill(['text_status' => TextStatus::Failed])->save();
    }
}
