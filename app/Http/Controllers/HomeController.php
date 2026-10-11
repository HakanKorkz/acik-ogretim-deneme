<?php

namespace App\Http\Controllers;

use App\Enums\CourseLevel;
use App\Models\Course;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Giriş sayfası: aday önce kademesini (lise / ortaokul) seçer.
     */
    public function __invoke(): Response
    {
        return Inertia::render('home', [
            'levels' => array_map(static fn (CourseLevel $level): array => [
                'value' => $level->value,
                'label' => $level->label(),
                'courses' => Course::query()->active()->where('level', $level)->has('questions')->count(),
            ], CourseLevel::cases()),
            'questionsPerCourse' => (int) config('exam.questions_per_exam'),
            'sectionMinutes' => (int) config('exam.section_minutes'),
            'passScore' => (int) config('exam.pass_score'),
        ]);
    }
}
