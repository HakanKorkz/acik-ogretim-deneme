<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TextStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ExtractCourseText;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;

class CourseTextController extends Controller
{
    /**
     * Kitap metnini (yeniden) çıkarma işini kuyruğa alır.
     */
    public function store(Course $course): RedirectResponse
    {
        if (blank($course->source_url) && blank($course->file_path)) {
            return back()->with('error', 'Bu ders için bir PDF kaynağı tanımlı değil.');
        }

        if ($course->isExtractingText()) {
            return back()->with('error', 'Bu kitabın metni zaten çıkarılıyor.');
        }

        $course->forceFill(['text_status' => TextStatus::Queued])->save();

        ExtractCourseText::dispatch($course);

        return back()->with('success', "{$course->name} kitabının metni hazırlanıyor.");
    }
}
