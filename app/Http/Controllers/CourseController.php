<?php

namespace App\Http\Controllers;

use App\Enums\CourseLevel;
use App\Models\Course;
use App\Models\ExamSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * Derslerin listelendiği ana panel.
     */
    public function index(Request $request): Response
    {
        $level = CourseLevel::tryFrom((string) $request->query('level')) ?? CourseLevel::Lise;

        $courses = Course::query()
            ->active()
            ->where('level', $level)
            ->withCount(['questions', 'examSessions'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('courses/index', [
            'courses' => $courses->map(static fn (Course $course): array => [
                'id' => $course->id,
                'name' => $course->name,
                'slug' => $course->slug,
                'subject' => $course->subject,
                'description' => $course->description,
                'questions_count' => $course->questions_count,
                'exam_sessions_count' => $course->exam_sessions_count,
            ])->values(),
            'questionsPerExam' => (int) config('exam.questions_per_exam'),
            'level' => $level->value,
            'levels' => array_map(static fn (CourseLevel $item): array => [
                'value' => $item->value,
                'label' => $item->label(),
            ], CourseLevel::cases()),
            'stats' => [
                'courses' => $courses->count(),
                'questions' => (int) $courses->sum('questions_count'),
                'sessions' => (int) $courses->sum('exam_sessions_count'),
                'completed' => ExamSession::query()->where('status', 'completed')->count(),
            ],
        ]);
    }
}
