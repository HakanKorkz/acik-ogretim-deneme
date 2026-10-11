<?php

namespace App\Http\Controllers;

use App\Enums\QuestionGenerationStatus;
use App\Models\Course;
use App\Models\ExamSession;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * Derslerin listelendiği ana panel.
     */
    public function index(): Response
    {
        $courses = Course::query()
            ->withCount(['questions', 'examSessions'])
            ->orderBy('name')
            ->get();

        return Inertia::render('courses/index', [
            'courses' => $courses->map(static fn (Course $course): array => [
                'id' => $course->id,
                'name' => $course->name,
                'slug' => $course->slug,
                'description' => $course->description,
                'questions_count' => $course->questions_count,
                'exam_sessions_count' => $course->exam_sessions_count,
                'is_generating' => $course->isGeneratingQuestions(),
                'generation_failed' => $course->question_generation_status === QuestionGenerationStatus::Failed,
            ])->values(),
            'questionsPerExam' => (int) config('exam.questions_per_exam'),
            'canGenerateQuestions' => (bool) config('exam.web_generation'),
            'stats' => [
                'courses' => $courses->count(),
                'questions' => (int) $courses->sum('questions_count'),
                'sessions' => (int) $courses->sum('exam_sessions_count'),
                'completed' => ExamSession::query()->where('status', 'completed')->count(),
            ],
        ]);
    }
}
