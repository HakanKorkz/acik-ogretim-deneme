<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ImportPastExamSection;
use App\Jobs\ParsePastExam;
use App\Models\PastExam;
use App\Models\PastExamSection;
use App\Services\PastExams\PastExamListing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PastExamController extends Controller
{
    /**
     * Çıkmış sınav kitapçıkları ve içe aktarma durumları.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['all', 'unprocessed', ...array_column(ImportStatus::cases(), 'value')])],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'sort' => ['nullable', Rule::in(['default', 'title', 'status', 'importedQuestions'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
        $search = trim($filters['search'] ?? '');
        $status = $filters['status'] ?? 'all';
        $sort = $filters['sort'] ?? 'default';
        $direction = $filters['direction'] ?? 'asc';
        $pastExams = PastExam::query()
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->when($status === 'unprocessed', fn ($query) => $query->whereNull('status'))
            ->when($status !== 'all' && $status !== 'unprocessed', fn ($query) => $query->where('status', $status))
            ->withCount([
                'sections',
                'sections as pending_sections' => fn ($query) => $query->whereIn('status', [ImportStatus::Queued, ImportStatus::Running]),
            ])
            ->withSum('sections as imported_questions', 'imported_count');

        $sortColumn = match ($sort) {
            'title' => 'title',
            'status' => 'status',
            'importedQuestions' => 'imported_questions',
            default => 'sort_order',
        };
        $pastExams->orderBy($sortColumn, $sort === 'default' ? 'asc' : $direction)->orderBy('id');

        return Inertia::render('admin/past-exams/index', [
            'filters' => ['search' => $search, 'status' => $status, 'sort' => $sort, 'direction' => $direction],
            'statuses' => array_map(static fn (ImportStatus $status): array => ['value' => $status->value, 'label' => $status->label()], ImportStatus::cases()),
            'pastExams' => fn () => $pastExams->paginate((int) ($filters['per_page'] ?? 25))
                ->withQueryString()->through(static fn (PastExam $pastExam): array => [
                    'id' => $pastExam->id,
                    'title' => $pastExam->title,
                    'sourceUrl' => $pastExam->source_url,
                    'status' => $pastExam->status?->value,
                    'statusLabel' => $pastExam->status?->label() ?? 'İşlenmedi',
                    'error' => $pastExam->error,
                    'isPending' => $pastExam->status?->isPending() ?? false,
                    'sectionsCount' => (int) $pastExam->getAttribute('sections_count'),
                    'importedQuestions' => (int) $pastExam->getAttribute('imported_questions'),
                    'pendingSections' => (int) $pastExam->getAttribute('pending_sections'),
                ]),
        ]);
    }

    public function sections(Request $request, PastExam $pastExam): JsonResponse
    {
        $request->validate(['page' => ['nullable', 'integer', 'min:1', 'max:1000000']]);

        return response()->json($pastExam->sections()
            ->select(['id', 'past_exam_id', 'course_id', 'code', 'name', 'status', 'imported_count', 'skipped_count', 'error', 'answer_key'])
            ->with('course:id,name,is_active')
            ->paginate(25)
            ->through(static fn (PastExamSection $section): array => [
                'id' => $section->id,
                'heading' => $section->heading(),
                'course' => $section->course?->name,
                'statusLabel' => $section->status?->label() ?? '—',
                'status' => $section->status?->value,
                'importedCount' => $section->imported_count,
                'skippedCount' => $section->skipped_count,
                'error' => $section->error,
                'canRetry' => $section->course !== null && $section->answer_key !== null && ! ($section->status?->isPending() ?? false),
            ]));
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
