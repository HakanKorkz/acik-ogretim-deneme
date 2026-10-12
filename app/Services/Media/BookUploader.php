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

    public const CLOUDINARY_DISK = 'cloudinary';

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
        $notice = $target === self::CLOUDINARY_DISK ? $this->settings->cloudinaryRejection((int) $file->getSize()) : null;
        $useCloudinary = $target === self::CLOUDINARY_DISK && $notice === null;

        $name = $course->slug.'-'.Str::lower((string) Str::ulid());

        // Yeni dosya başarıyla yazılmadan eskisi silinmez. Aksi hâlde yükleme
        // yarıda kalırsa (Cloudinary hatası, disk dolu) ders, silinmiş bir
        // dosyaya işaret eder ve kullanılamaz hâle gelirdi.
        $previous = [
            'disk' => $course->upload_disk,
            'path' => $course->file_path,
            'public_id' => $course->upload_public_id,
        ];

        if ($useCloudinary) {
            $uploaded = $this->cloudinary->uploadRaw((string) $file->getRealPath(), "{$name}.pdf");

            $course->forceFill([
                'source_url' => $uploaded['url'],
                'file_path' => null,
                'upload_disk' => self::CLOUDINARY_DISK,
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

        $this->deleteStoredFile($previous['disk'], $previous['path'], $previous['public_id']);

        return $notice;
    }

    /**
     * Dersin yüklenmiş dosyasını siler (MEB kataloğu kitaplarına dokunmaz).
     */
    public function deleteStored(Course $course): void
    {
        $this->deleteStoredFile($course->upload_disk, $course->file_path, $course->upload_public_id);
    }

    /**
     * Verilen depolama bilgisine ait dosyayı siler.
     */
    private function deleteStoredFile(?string $disk, ?string $path, ?string $publicId): void
    {
        if ($disk === self::LOCAL_DISK && filled($path)) {
            Storage::disk(self::LOCAL_DISK)->delete($path);
        }

        if ($disk === self::CLOUDINARY_DISK && filled($publicId)) {
            $this->cloudinary->destroyRaw($publicId);
        }
    }
}
