<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseLevel;
use App\Enums\CourseSource;
use App\Enums\QuestionGenerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCourseRequest;
use App\Models\BookPage;
use App\Models\Course;
use App\Services\Media\BookUploader;
use App\Settings\MediaSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * Ders kataloğu: kademeye göre, aranabilir.
     */
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'level' => ['nullable', Rule::enum(CourseLevel::class)],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $level = CourseLevel::tryFrom($filters['level'] ?? '') ?? CourseLevel::Lise;
        $search = trim($filters['search'] ?? '');

        $courses = Course::query()
            ->where('level', $level)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('subject', 'like', "%{$search}%")))
            ->withCount(['questions', 'examSessions'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/courses/index', [
            'courses' => $courses->map(fn (Course $course): array => $this->row($course))->values(),
            'filters' => ['level' => $level->value, 'search' => $search],
            'levels' => array_map(static fn (CourseLevel $item): array => [
                'value' => $item->value,
                'label' => $item->label(),
                'count' => Course::query()->where('level', $item)->count(),
            ], CourseLevel::cases()),
            'questionsPerCourse' => (int) config('exam.questions_per_course'),
        ]);
    }

    /**
     * Sayfa aralığı, kaydırma ve yayın durumu düzenleme ekranı.
     */
    public function edit(Course $course, MediaSettings $media): Response
    {
        $course->loadCount(['questions', 'examSessions', 'pages']);

        return Inertia::render('admin/courses/edit', [
            'course' => [
                ...$this->row($course),
                'description' => $course->description,
                'sourceUrl' => $course->source_url,
                'pageFrom' => $course->page_from,
                'pageTo' => $course->page_to,
                'pageOffset' => $course->page_offset,
                'pageCount' => $course->page_count,
            ],
            'scopePreview' => $course->hasText() ? $this->scopePreview($course) : null,
            'upload' => $course->source === CourseSource::Upload ? [
                'storedOn' => $course->upload_disk === 'cloudinary' ? 'Cloudinary' : ($course->upload_disk === BookUploader::LOCAL_DISK ? 'Sunucu' : null),
                'defaultTarget' => $media->default_target,
                'cloudinaryConfigured' => $media->cloudinaryConfigured(),
                'cloudinaryMaxMegabytes' => $media->cloudinary_max_megabytes,
                'maxMegabytes' => (int) config('exam.upload_max_megabytes'),
            ] : null,
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $course->fill([
            ...$request->safe()->except('is_active'),
            // İşaretsiz checkbox hiç gönderilmez; yokluğu "pasif" demektir.
            'is_active' => $request->boolean('is_active'),
        ])->save();

        return back()->with('success', "{$course->name} güncellendi.");
    }

    /**
     * Ders listesinde ve düzenleme ekranında ortak alanlar.
     *
     * @return array<string, mixed>
     */
    private function row(Course $course): array
    {
        return [
            'id' => $course->id,
            'name' => $course->name,
            'subject' => $course->subject,
            'slug' => $course->slug,
            'source' => $course->source->label(),
            'hasSource' => filled($course->source_url) || filled($course->file_path),
            'range' => $this->rangeLabel($course),
            'isActive' => $course->is_active,
            'textStatus' => $course->text_status?->value,
            'textStatusLabel' => $course->text_status?->label() ?? 'Hazırlanmadı',
            'isExtractingText' => $course->isExtractingText(),
            'sourceChanged' => $course->source_changed_at !== null,
            'questionsCount' => $course->questions_count ?? 0,
            'examSessionsCount' => $course->exam_sessions_count ?? 0,
            'isGenerating' => $course->isGeneratingQuestions(),
            'generationFailed' => $course->question_generation_status === QuestionGenerationStatus::Failed,
        ];
    }

    private function rangeLabel(Course $course): string
    {
        if ($course->page_from === null && $course->page_to === null) {
            return 'Kitabın tamamı';
        }

        return sprintf('s. %s–%s', $course->page_from ?? 1, $course->page_to ?? 'son');
    }

    /**
     * Aralığın ilk ve son sayfasından birkaç satır: kaydırmanın doğru olup
     * olmadığını yönetici gözüyle doğrulayabilsin.
     *
     * @return array{first: array{pdfPage: int, printedPage: int, excerpt: string}|null, last: array{pdfPage: int, printedPage: int, excerpt: string}|null, pages: int}
     */
    private function scopePreview(Course $course): array
    {
        $pages = $course->pagesInScope();
        $offset = $course->page_offset ?? 0;

        $describe = static function (?BookPage $page) use ($offset): ?array {
            if ($page === null) {
                return null;
            }

            $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $page->content) ?: [])));

            return [
                'pdfPage' => $page->page_number,
                'printedPage' => $page->page_number - $offset,
                'excerpt' => implode(' · ', [...array_slice($lines, 0, 2), '…', ...array_slice($lines, -2)]),
            ];
        };

        return [
            'first' => $describe($pages->first()),
            'last' => $describe($pages->last()),
            'pages' => $pages->count(),
        ];
    }
}
