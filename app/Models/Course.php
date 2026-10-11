<?php

namespace App\Models;

use App\Enums\CourseLevel;
use App\Enums\CourseSource;
use App\Enums\QuestionGenerationStatus;
use App\Enums\TextStatus;
use App\Services\Media\BookUploader;
use Carbon\CarbonImmutable;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property CourseLevel $level
 * @property string $name
 * @property string|null $subject
 * @property string $slug
 * @property CourseSource $source
 * @property string|null $external_key
 * @property string|null $source_url
 * @property string|null $file_path Yüklenen kitabın yerel diskteki yolu.
 * @property string|null $upload_disk `local` ya da `cloudinary`.
 * @property string|null $upload_public_id Cloudinary kimliği.
 * @property string|null $description
 * @property int|null $page_from
 * @property int|null $page_to
 * @property int|null $page_offset
 * @property int|null $page_count
 * @property TextStatus|null $text_status
 * @property CarbonImmutable|null $text_extracted_at
 * @property CarbonImmutable|null $source_last_modified
 * @property int|null $source_size
 * @property CarbonImmutable|null $source_changed_at
 * @property bool $is_active
 * @property int $sort_order
 * @property QuestionGenerationStatus|null $question_generation_status
 * @property Carbon|null $question_generation_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $questions_count
 * @property-read int $exam_sessions_count
 */
#[Fillable([
    'level', 'name', 'subject', 'slug', 'source', 'external_key', 'source_url', 'file_path', 'upload_disk', 'upload_public_id', 'description',
    'page_from', 'page_to', 'page_offset', 'page_count', 'text_status', 'text_extracted_at',
    'source_last_modified', 'source_size', 'source_changed_at', 'is_active', 'sort_order',
    'question_generation_status', 'question_generation_updated_at',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /**
     * Derse ait sorular.
     *
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Derse ait sınav oturumları.
     *
     * @return HasMany<ExamSession, $this>
     */
    public function examSessions(): HasMany
    {
        return $this->hasMany(ExamSession::class);
    }

    /**
     * Kitabın PDF'ten çıkarılmış sayfaları.
     *
     * @return HasMany<BookPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(BookPage::class)->orderBy('page_number');
    }

    /**
     * Dersin sorumlu olduğu aralıktaki sayfaların metni.
     *
     * Aralık, açıklamadaki BASILI sayfa numaralarıyla tutulur; PDF sırasına
     * `page_offset` ile çevrilir. Aralık yoksa kitabın tamamı kullanılır.
     *
     * @return Collection<int, BookPage>
     */
    public function pagesInScope(): Collection
    {
        $offset = $this->page_offset ?? 0;

        return $this->pages()
            ->when($this->page_from !== null, fn (Builder $query) => $query->where('page_number', '>=', $this->page_from + $offset))
            ->when($this->page_to !== null, fn (Builder $query) => $query->where('page_number', '<=', $this->page_to + $offset))
            ->get();
    }

    /**
     * Kitap metni soru üretimine hazır mı?
     */
    public function hasText(): bool
    {
        return $this->text_status === TextStatus::Ready;
    }

    /**
     * Metin çıkarma işi sırada ya da çalışıyor mu? Takılı kalan işler
     * `generation_stale_after_minutes` sonra geçersiz sayılır.
     */
    public function isExtractingText(): bool
    {
        if ($this->text_status === null || ! $this->text_status->isPending()) {
            return false;
        }

        $staleAfterMinutes = (int) config('exam.generation_stale_after_minutes');

        return $this->updated_at !== null
            && $this->updated_at->greaterThan(now()->subMinutes($staleAfterMinutes));
    }

    /**
     * Ön yüzde listelenen dersler.
     *
     * @param  Builder<Course>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Route model binding için slug kullanılır.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Kademe ve ada göre benzersiz adres. Lise ve ortaokulda aynı adlı
     * dersler olabildiğinden ortaokul derslerinin adresine kademe eklenir;
     * çakışma kalırsa sayı eklenir.
     */
    public static function uniqueSlug(CourseLevel $level, string $name): string
    {
        $base = Str::slug($level === CourseLevel::Ortaokul ? "ortaokul {$name}" : $name);
        $slug = $base;
        $suffix = 2;

        while (self::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Sunucudaki PDF'in mutlak yolu (yüklenen kitaplar özel diskte durur).
     * MEB ve Cloudinary kitaplarında null: bunlar adresinden indirilir.
     */
    public function pdfAbsolutePath(): ?string
    {
        if (blank($this->file_path)) {
            return null;
        }

        if ($this->upload_disk === BookUploader::LOCAL_DISK) {
            return Storage::disk(BookUploader::LOCAL_DISK)->path($this->file_path);
        }

        if (str_starts_with($this->file_path, DIRECTORY_SEPARATOR)) {
            return $this->file_path;
        }

        return public_path($this->file_path);
    }

    /**
     * Soru üretimi şu an sırada ya da çalışıyor mu?
     *
     * Çalışan bir işçi çökerse ders sonsuza dek "üretiliyor" görünmesin diye,
     * belirli bir süreden eski bekleyen durumlar geçersiz sayılır.
     */
    public function isGeneratingQuestions(): bool
    {
        if ($this->question_generation_status === null || ! $this->question_generation_status->isPending()) {
            return false;
        }

        $staleAfterMinutes = (int) config('exam.generation_stale_after_minutes');

        return $this->question_generation_updated_at !== null
            && $this->question_generation_updated_at->greaterThan(now()->subMinutes($staleAfterMinutes));
    }

    /**
     * Soru üretim durumunu günceller.
     */
    public function markQuestionGeneration(?QuestionGenerationStatus $status): void
    {
        $this->forceFill([
            'question_generation_status' => $status,
            'question_generation_updated_at' => $status === null ? null : now(),
        ])->save();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => CourseLevel::class,
            'source' => CourseSource::class,
            'text_status' => TextStatus::class,
            'text_extracted_at' => 'datetime',
            'source_last_modified' => 'datetime',
            'source_changed_at' => 'datetime',
            'is_active' => 'boolean',
            'question_generation_status' => QuestionGenerationStatus::class,
            'question_generation_updated_at' => 'datetime',
        ];
    }

    /**
     * Soruları üretilmiş dersleri filtrele.
     *
     * @param  Builder<Course>  $query
     */
    #[Scope]
    protected function withQuestions(Builder $query): void
    {
        $query->has('questions');
    }
}
