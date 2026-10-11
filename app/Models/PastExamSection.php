<?php

namespace App\Models;

use App\Enums\ImportStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PastExamSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Çıkmış sınav kitapçığındaki bir dersin bölümü.
 *
 * @property int $id
 * @property int $past_exam_id
 * @property int|null $course_id
 * @property string $code
 * @property string $name
 * @property string $content
 * @property array<int|string, string>|null $answer_key Soru numarası → doğru şık.
 * @property ImportStatus|null $status
 * @property int $imported_count
 * @property int $skipped_count
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read PastExam $pastExam
 * @property-read Course|null $course
 */
#[Fillable([
    'past_exam_id', 'course_id', 'code', 'name', 'content', 'answer_key',
    'status', 'imported_count', 'skipped_count', 'error',
])]
class PastExamSection extends Model
{
    /** @use HasFactory<PastExamSectionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<PastExam, $this>
     */
    public function pastExam(): BelongsTo
    {
        return $this->belongsTo(PastExam::class);
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * Kitapçıktaki başlık: "(132) TARİH – 2".
     */
    public function heading(): string
    {
        return "({$this->code}) {$this->name}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'answer_key' => 'array',
            'status' => ImportStatus::class,
        ];
    }
}
