<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionGenerationStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateCourseQuestions;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;

class CourseQuestionController extends Controller
{
    /**
     * Bir dersin soru bankası üretimini kuyruğa alır.
     *
     * Mevcut sorular varsa yeniden üretilir; sınavlarda kullanılmış olanlar
     * arşivlenir, geçmiş sonuçlar bozulmaz.
     */
    public function store(Course $course): RedirectResponse
    {
        if (blank($course->file_path)) {
            return back()->with('error', 'Bu ders için bir PDF kaynağı tanımlı değil.');
        }

        if ($course->isGeneratingQuestions()) {
            return back()->with('error', 'Bu ders için soru üretimi zaten devam ediyor.');
        }

        $course->markQuestionGeneration(QuestionGenerationStatus::Queued);

        GenerateCourseQuestions::dispatch($course);

        return back()->with('success', "{$course->name} için soru üretimi başlatıldı.");
    }
}
