<?php

namespace App\Http\Controllers;

use App\Enums\QuestionGenerationStatus;
use App\Jobs\GenerateCourseQuestions;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;

class CourseQuestionController extends Controller
{
    /**
     * Bir dersin PDF kaynağından soru üretimini kuyruğa alır.
     *
     * Üretim `exam.web_generation` ayarı açıkken web'den tetiklenebilir;
     * aksi hâlde yalnızca `php artisan questions:generate` kullanılır.
     */
    public function store(Course $course): RedirectResponse
    {
        abort_unless(config('exam.web_generation'), 403, 'Web üzerinden soru üretimi kapalı.');

        if ($course->questions()->exists()) {
            return back()->with('error', 'Bu ders için sorular zaten üretilmiş.');
        }

        if (blank($course->file_path)) {
            return back()->with('error', 'Bu ders için bir PDF kaynağı tanımlı değil.');
        }

        if ($course->isGeneratingQuestions()) {
            return back()->with('error', 'Bu ders için soru üretimi zaten devam ediyor.');
        }

        $course->markQuestionGeneration(QuestionGenerationStatus::Queued);

        GenerateCourseQuestions::dispatch($course);

        return back()->with('success', 'Soru üretimi başlatıldı. Hazır olduğunda bu sayfa kendiliğinden güncellenecek.');
    }
}
