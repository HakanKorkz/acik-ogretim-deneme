<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ExamSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sınavın tek bir dersi. Dersler sırayla çözülür; her bölümün kendi süresi
 * ve sonucu vardır.
 *
 * @property int $id
 * @property int $exam_session_id
 * @property int $course_id
 * @property int $position Sınavdaki sırası (1'den).
 * @property int $total_questions
 * @property int|null $correct_count
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read ExamSession $examSession
 * @property-read Course $course
 * @property-read Collection<int, ExamAnswer> $answers
 */
#[Fillable([
    'exam_session_id', 'course_id', 'position', 'total_questions', 'correct_count',
    'started_at', 'expires_at', 'completed_at',
])]
class ExamSection extends Model
{
    /** @use HasFactory<ExamSectionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<ExamAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class)->orderBy('position');
    }

    public function hasStarted(): bool
    {
        return $this->started_at !== null;
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Süre doldu mu?
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && now()->greaterThanOrEqualTo($this->expires_at);
    }

    /**
     * Kalan süre (saniye).
     */
    public function remainingSeconds(): int
    {
        if ($this->expires_at === null) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    /**
     * 100 üzerinden puan (AÖL'de her derste 10 soru, her soru 10 puan).
     */
    public function score(): int
    {
        if ($this->total_questions === 0) {
            return 0;
        }

        return (int) round(($this->correct_count ?? 0) / $this->total_questions * 100);
    }

    public function isPassed(): bool
    {
        return $this->score() >= (int) config('exam.pass_score');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
