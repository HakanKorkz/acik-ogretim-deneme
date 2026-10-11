<?php

namespace App\Models;

use App\Enums\QuestionGenerationStatus;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $file_path
 * @property string|null $description
 * @property QuestionGenerationStatus|null $question_generation_status
 * @property Carbon|null $question_generation_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $questions_count
 * @property-read int $exam_sessions_count
 */
#[Fillable(['name', 'slug', 'file_path', 'description', 'question_generation_status', 'question_generation_updated_at'])]
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
     * Route model binding için slug kullanılır.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * PDF kaynağının mutlak yolu. Göreli yollar public/ altına göre çözülür.
     */
    public function pdfAbsolutePath(): ?string
    {
        if (blank($this->file_path)) {
            return null;
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
