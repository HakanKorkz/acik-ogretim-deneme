<?php

namespace App\Http\Controllers;

use App\Enums\CourseLevel;
use App\Enums\QuestionPool;
use App\Enums\QuestionSource;
use App\Enums\ResultMode;
use App\Models\Course;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * Kademenin derslerinden sınav seçimi: 1–10 ders, soru havuzu ve
     * sonuçların gösterim biçimi.
     */
    public function index(CourseLevel $level): Response
    {
        $courses = Course::query()
            ->active()
            ->where('level', $level)
            ->withCount([
                'questions as past_count' => fn ($query) => $query->where('source', QuestionSource::PastExam),
                'questions as generated_count' => fn ($query) => $query->whereIn('source', [QuestionSource::Ai, QuestionSource::Local]),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('courses/index', [
            'level' => ['value' => $level->value, 'label' => $level->label()],
            'courses' => $courses->map(static fn (Course $course): array => [
                'slug' => $course->slug,
                'name' => $course->name,
                'subject' => $course->subject,
                'pastCount' => (int) $course->getAttribute('past_count'),
                'generatedCount' => (int) $course->getAttribute('generated_count'),
            ])->values(),
            'pools' => array_map(static fn (QuestionPool $pool): array => [
                'value' => $pool->value,
                'label' => $pool->label(),
            ], QuestionPool::cases()),
            'resultModes' => array_map(static fn (ResultMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ], ResultMode::cases()),
            'maxCourses' => (int) config('exam.max_courses_per_exam'),
            'questionsPerCourse' => (int) config('exam.questions_per_exam'),
            'sectionMinutes' => (int) config('exam.section_minutes'),
        ]);
    }
}
