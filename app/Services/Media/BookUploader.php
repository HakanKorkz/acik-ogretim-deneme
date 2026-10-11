<?php

namespace App\Services\Media;

use App\Enums\CourseSource;
use App\Models\Course;
use App\Settings\MediaSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Yöneticinin yüklediği ders kitabını seçilen hedefe kaydeder ve dersi
 * bu dosyaya bağlar. Önceki dosya (yerel ya da Cloudinary) silinir.
 */
class BookUploader
{
    public const LOCAL_DISK = 'local';

    public function __construct(
        private readonly MediaSettings $settings,
        private readonly CloudinaryClient $cloudinary,
    ) {}

    /**
     * @param  'local'|'cloudinary'|null  $target  Verilmezse paneldeki varsayılan.
     * @return string|null Cloudinary istenip kullanılamadıysa nedeni.
     */
    public function store(Course $course, UploadedFile $file, ?string $target = null): ?string
    {
        $target ??= $this->settings->default_target;
        $notice = $target === 'cloudinary' ? $this->settings->cloudinaryRejection((int) $file->getSize()) : null;
        $useCloudinary = $target === 'cloudinary' && $notice === null;

        $this->deleteStored($course);

        $name = $course->slug.'-'.Str::lower((string) Str::ulid());

        if ($useCloudinary) {
            $uploaded = $this->cloudinary->uploadRaw((string) $file->getRealPath(), "{$name}.pdf");

            $course->forceFill([
                'source_url' => $uploaded['url'],
                'file_path' => null,
                'upload_disk' => 'cloudinary',
                'upload_public_id' => $uploaded['public_id'],
            ]);
        } else {
            $course->forceFill([
                'source_url' => null,
                'file_path' => $file->storeAs('books', "{$name}.pdf", self::LOCAL_DISK),
                'upload_disk' => self::LOCAL_DISK,
                'upload_public_id' => null,
            ]);
        }

        $course->forceFill([
            'source' => CourseSource::Upload,
            'text_status' => null,
            'text_extracted_at' => null,
            'page_offset' => null,
            'source_changed_at' => null,
        ])->save();

        return $notice;
    }

    /**
     * Dersin yüklenmiş dosyasını siler (MEB kataloğu kitaplarına dokunmaz).
     */
    public function deleteStored(Course $course): void
    {
        if ($course->upload_disk === self::LOCAL_DISK && filled($course->file_path)) {
            Storage::disk(self::LOCAL_DISK)->delete($course->file_path);
        }

        if ($course->upload_disk === 'cloudinary' && filled($course->upload_public_id)) {
            $this->cloudinary->destroyRaw($course->upload_public_id);
        }
    }
}
