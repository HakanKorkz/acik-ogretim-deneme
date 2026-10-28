<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/guide', [
            'exam' => [
                'questionsPerCourse' => (int) config('exam.questions_per_course'),
                'questionsPerExam' => (int) config('exam.questions_per_exam'),
                'sectionMinutes' => (int) config('exam.section_minutes'),
                'maxCourses' => (int) config('exam.max_courses_per_exam'),
                'passScore' => (int) config('exam.pass_score'),
                'uploadMaxMegabytes' => (int) config('exam.upload_max_megabytes'),
            ],
        ]);
    }
}
