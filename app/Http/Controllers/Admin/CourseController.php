<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionGenerationStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * Derslerin, soru bankalarının ve üretim durumlarının listesi.
     */
    public function index(): Response
    {
        $courses = Course::query()
            ->withCount(['questions', 'examSessions'])
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/courses/index', [
            'courses' => $courses->map(static fn (Course $course): array => [
                'id' => $course->id,
                'name' => $course->name,
                'slug' => $course->slug,
                'filePath' => $course->file_path,
                'questionsCount' => $course->questions_count,
                'examSessionsCount' => $course->exam_sessions_count,
                'isGenerating' => $course->isGeneratingQuestions(),
                'generationFailed' => $course->question_generation_status === QuestionGenerationStatus::Failed,
            ])->values(),
            'questionsPerCourse' => (int) config('exam.questions_per_course'),
        ]);
    }
}
