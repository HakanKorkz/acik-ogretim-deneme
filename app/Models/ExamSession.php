<?php

namespace App\Models;

use App\Enums\CourseLevel;
use App\Enums\ExamStatus;
use App\Enums\QuestionPool;
use App\Enums\ResultMode;
use Carbon\CarbonImmutable;
use Database\Factories\ExamSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $ulid
 * @property int|null $course_id İlk ders (geriye uyumluluk için).
 * @property CourseLevel $level
 * @property string|null $visitor_id
 * @property QuestionPool $question_pool
 * @property ResultMode $result_mode
 * @property int|null $section_minutes
 * @property int|null $user_id
 * @property int $total_questions
 * @property int|null $correct_count
 * @property ExamStatus $status
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Course|null $course
 * @property-read Collection<int, ExamSection> $sections
 * @property-read Collection<int, ExamAnswer> $answers
 */
#[Fillable([
    'course_id',
    'level',
    'visitor_id',
    'question_pool',
    'result_mode',
    'section_minutes',
    'user_id',
    'total_questions',
    'correct_count',
    'status',
    'started_at',
    'completed_at',
])]
class ExamSession extends Model
{
    /** @use HasFactory<ExamSessionFactory> */
    use HasFactory, HasUlids;

    /**
     * ULID ile otomatik doldurulacak sütunlar. Birincil anahtar sayısal kalır.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * Route model binding için tahmin edilemeyen ULID kullanılır.
     */
    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * Oturumun ait olduğu ders.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Sınavın ders bölümleri, çözülme sırasıyla.
     *
     * @return HasMany<ExamSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(ExamSection::class)->orderBy('position');
    }

    /**
     * Sıradaki (henüz tamamlanmamış ilk) bölüm.
     */
    public function currentSection(): ?ExamSection
    {
        return $this->sections->first(fn (ExamSection $section): bool => ! $section->isCompleted());
    }

    /**
     * Oturumdaki cevaplar.
     *
     * @return HasMany<ExamAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class)->orderBy('position');
    }

    /**
     * Sınav tamamlanmış mı?
     */
    public function isCompleted(): bool
    {
        return $this->status === ExamStatus::Completed;
    }

    /**
     * Yüzdelik başarı puanı.
     */
    public function scorePercentage(): int
    {
        if ($this->total_questions === 0) {
            return 0;
        }

        return (int) round(($this->correct_count ?? 0) / $this->total_questions * 100);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExamStatus::class,
            'level' => CourseLevel::class,
            'question_pool' => QuestionPool::class,
            'result_mode' => ResultMode::class,
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
