<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\Question;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Yönetim panelinin özet ekranı.
     */
    public function __invoke(): Response
    {
        $recentSessions = ExamSession::query()
            ->with('course:id,name')
            ->latest()
            ->limit(10)
            ->get();

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'courses' => Course::query()->count(),
                'coursesWithQuestions' => Course::query()->has('questions')->count(),
                'questions' => Question::query()->count(),
                'sessions' => ExamSession::query()->count(),
                'completedSessions' => ExamSession::query()->where('status', ExamStatus::Completed)->count(),
            ],
            'recentSessions' => $recentSessions->map(static fn (ExamSession $session): array => [
                'id' => $session->ulid,
                'course' => $session->course->name,
                'status' => $session->status->label(),
                'isCompleted' => $session->isCompleted(),
                'score' => $session->isCompleted() ? $session->scorePercentage() : null,
                'startedAt' => $session->started_at?->toIso8601String(),
            ])->values(),
        ]);
    }
}
