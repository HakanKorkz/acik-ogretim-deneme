<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseLevel;
use App\Enums\QuestionSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuestionExportRequest;
use App\Models\Course;
use App\Models\Question;
use App\Services\Questions\QuestionExcelExporter;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionExportController extends Controller
{
    public function index(QuestionExcelExporter $exporter): Response
    {
        $total = Question::query()->count();
        $eligible = $exporter->eligibleQuery()->count();

        return Inertia::render('admin/questions/export', [
            'courses' => Course::query()->has('questions')->orderBy('name')->get()
                ->map(static fn (Course $course): array => [
                    'slug' => $course->slug,
                    'name' => $course->name,
                    'level' => $course->level->value,
                ]),
            'levels' => array_map(static fn (CourseLevel $level): array => ['value' => $level->value, 'label' => $level->label()], CourseLevel::cases()),
            'sources' => array_map(static fn (QuestionSource $source): array => ['value' => $source->value, 'label' => $source->label()], QuestionSource::cases()),
            'stats' => ['total' => $total, 'eligible' => $eligible, 'excluded' => $total - $eligible],
        ]);
    }

    public function download(QuestionExportRequest $request, QuestionExcelExporter $exporter): BinaryFileResponse
    {
        $filters = $request->validated();
        $query = $exporter->eligibleQuery()
            ->when($filters['level'] ?? null, fn (Builder $query, string $level): Builder => $query
                ->whereHas('course', fn (Builder $query): Builder => $query->where('level', $level)))
            ->when($filters['course'] ?? null, fn (Builder $query, string $course): Builder => $query
                ->whereHas('course', fn (Builder $query): Builder => $query->where('slug', $course)))
            ->when($filters['source'] ?? null, fn (Builder $query, string $source): Builder => $query->where('source', $source))
            ->when(in_array($filters['publication'] ?? null, ['active', 'inactive'], true), fn (Builder $query): Builder => $query
                ->whereHas('course', fn (Builder $query): Builder => $query->where('is_active', $filters['publication'] === 'active')));

        $path = $exporter->export($query, $request->boolean('metadata'));

        return response()->download($path, 'soru-bankasi-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }
}
