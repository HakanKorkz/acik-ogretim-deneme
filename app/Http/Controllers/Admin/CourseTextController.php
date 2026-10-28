<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TextStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ExtractCourseText;
use App\Models\BookPage;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourseTextController extends Controller
{
    public function index(Request $request, Course $course): Response
    {
        $filters = $request->validate([
            'scope' => ['nullable', Rule::in(['responsible', 'all'])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000000'],
        ]);
        $scope = $filters['scope'] ?? 'responsible';
        $search = trim($filters['search'] ?? '');
        $offset = $course->page_offset ?? 0;

        return Inertia::render('admin/courses/text', [
            'course' => [...$course->only(['slug', 'name']), 'sourceUrl' => $course->source_url, 'level' => $course->level->value],
            'filters' => ['scope' => $scope, 'search' => $search],
            'pages' => fn () => $course->pages()
                ->when($scope === 'responsible' && $course->page_from !== null, fn ($query) => $query->where('page_number', '>=', $course->page_from + $offset))
                ->when($scope === 'responsible' && $course->page_to !== null, fn ($query) => $query->where('page_number', '<=', $course->page_to + $offset))
                ->when($search !== '', fn ($query) => $query->where('content', 'like', "%{$search}%"))
                ->paginate(5)->withQueryString()->through(static fn (BookPage $page): array => [
                    'pdfPage' => $page->page_number,
                    'printedPage' => $page->page_number - $offset,
                    'content' => $page->content,
                ]),
        ]);
    }

    /**
     * Kitap metnini (yeniden) çıkarma işini kuyruğa alır.
     */
    public function store(Course $course): RedirectResponse
    {
        if (blank($course->source_url) && blank($course->file_path)) {
            return back()->with('error', 'Bu ders için bir PDF kaynağı tanımlı değil.');
        }

        if ($course->isExtractingText()) {
            return back()->with('error', 'Bu kitabın metni zaten çıkarılıyor.');
        }

        $course->forceFill(['text_status' => TextStatus::Queued])->save();

        ExtractCourseText::dispatch($course);

        return back()->with('success', "{$course->name} kitabının metni hazırlanıyor.");
    }
}
