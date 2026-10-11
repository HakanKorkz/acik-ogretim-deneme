<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseLevel;
use App\Enums\CourseSource;
use App\Enums\TextStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReplaceBookRequest;
use App\Http\Requests\Admin\StoreCourseRequest;
use App\Jobs\ExtractCourseText;
use App\Models\Course;
use App\Services\Media\BookUploader;
use App\Settings\MediaSettings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MEB kataloğunda olmayan dersler için PDF yükleyerek ders ekleme ve
 * yüklenmiş kitabı değiştirme. Metin hemen arka planda çıkarılır.
 */
class CourseBookController extends Controller
{
    public function create(MediaSettings $media): Response
    {
        return Inertia::render('admin/courses/create', [
            'levels' => array_map(static fn (CourseLevel $level): array => [
                'value' => $level->value,
                'label' => $level->label(),
                'subjects' => Course::query()->where('level', $level)->whereNotNull('subject')->distinct()->orderBy('subject')->pluck('subject'),
            ], CourseLevel::cases()),
            'upload' => $this->uploadOptions($media),
        ]);
    }

    public function store(StoreCourseRequest $request, BookUploader $uploader): RedirectResponse
    {
        $level = CourseLevel::from($request->validated('level'));

        $course = Course::query()->create([
            ...$request->safe()->only(['subject', 'name', 'description', 'page_from', 'page_to']),
            'level' => $level,
            'slug' => Course::uniqueSlug($level, $request->validated('name')),
            'source' => CourseSource::Upload,
            'is_active' => false,
        ]);

        return $this->storeBook($course, $request, $uploader, "{$course->name} eklendi; kitabın metni hazırlanıyor.");
    }

    public function update(ReplaceBookRequest $request, Course $course, BookUploader $uploader): RedirectResponse
    {
        if ($course->source === CourseSource::Aokul) {
            return back()->with('error', 'MEB kataloğundaki derslerin kitabı değiştirilemez.');
        }

        return $this->storeBook($course, $request, $uploader, "{$course->name} kitabı değiştirildi; metin yeniden hazırlanıyor.");
    }

    private function storeBook(Course $course, ReplaceBookRequest|StoreCourseRequest $request, BookUploader $uploader, string $message): RedirectResponse
    {
        $notice = $uploader->store($course, $request->file('book'), $request->validated('target'));

        $course->forceFill(['text_status' => TextStatus::Queued])->save();
        ExtractCourseText::dispatch($course);

        return to_route('admin.courses.edit', $course)
            ->with($notice === null ? 'success' : 'error', $notice === null ? $message : "{$message} {$notice}");
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadOptions(MediaSettings $media): array
    {
        return [
            'defaultTarget' => $media->default_target,
            'cloudinaryConfigured' => $media->cloudinaryConfigured(),
            'cloudinaryMaxMegabytes' => $media->cloudinary_max_megabytes,
            'maxMegabytes' => (int) config('exam.upload_max_megabytes'),
        ];
    }
}
