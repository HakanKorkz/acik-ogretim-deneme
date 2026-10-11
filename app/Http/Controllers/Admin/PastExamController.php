<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ImportPastExamSection;
use App\Jobs\ParsePastExam;
use App\Models\PastExam;
use App\Models\PastExamSection;
use App\Services\PastExams\PastExamListing;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PastExamController extends Controller
{
    /**
     * Çıkmış sınav kitapçıkları ve içe aktarma durumları.
     */
    public function index(): Response
    {
        $pastExams = PastExam::query()
            ->with(['sections.course:id,name,is_active'])
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('admin/past-exams/index', [
            'pastExams' => $pastExams->map(static fn (PastExam $pastExam): array => [
                'id' => $pastExam->id,
                'title' => $pastExam->title,
                'sourceUrl' => $pastExam->source_url,
                'status' => $pastExam->status?->value,
                'statusLabel' => $pastExam->status?->label() ?? 'İşlenmedi',
                'error' => $pastExam->error,
                'isPending' => $pastExam->status?->isPending() ?? false,
                'sectionsCount' => $pastExam->sections->count(),
                'importedQuestions' => (int) $pastExam->sections->sum('imported_count'),
                'pendingSections' => $pastExam->sections->filter(fn (PastExamSection $section): bool => $section->status?->isPending() ?? false)->count(),
                'sections' => $pastExam->sections->map(static fn (PastExamSection $section): array => [
                    'id' => $section->id,
                    'heading' => $section->heading(),
                    'course' => $section->course?->name,
                    'statusLabel' => $section->status?->label() ?? '—',
                    'status' => $section->status?->value,
                    'importedCount' => $section->imported_count,
                    'skippedCount' => $section->skipped_count,
                    'error' => $section->error,
                    'canRetry' => $section->course !== null && $section->answer_key !== null && ! ($section->status?->isPending() ?? false),
                ])->values(),
            ])->values(),
        ]);
    }

    /**
     * MEB listesini eşitler (PDF indirmez).
     */
    public function sync(PastExamListing $listing): RedirectResponse
    {
        try {
            $summary = $listing->sync();
        } catch (Throwable $exception) {
            report($exception);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "{$summary['total']} kitapçık listelendi, {$summary['created']} tanesi yeni.");
    }

    /**
     * Kitapçığı işler: bölümlere ayırır ve aktif derslerin sorularını aktarır.
     */
    public function import(PastExam $pastExam): RedirectResponse
    {
        if ($pastExam->status?->isPending()) {
            return back()->with('error', 'Bu kitapçık zaten işleniyor.');
        }

        $pastExam->forceFill(['status' => ImportStatus::Queued, 'error' => null])->save();

        ParsePastExam::dispatch($pastExam);

        return back()->with('success', "{$pastExam->title} kuyruğa alındı.");
    }

    /**
     * Tek bir bölümün aktarımını yeniden dener.
     */
    public function retrySection(PastExamSection $section): RedirectResponse
    {
        if ($section->course_id === null || $section->answer_key === null) {
            return back()->with('error', 'Bu bölüm aktarılamaz: ders eşleşmesi ya da cevap anahtarı yok.');
        }

        $section->forceFill(['status' => ImportStatus::Queued, 'error' => null])->save();

        ImportPastExamSection::dispatch($section);

        return back()->with('success', "{$section->heading()} kuyruğa alındı.");
    }
}
