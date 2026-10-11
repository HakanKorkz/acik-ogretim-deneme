<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property string $text
 * @property string $correct_option
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Course $course
 * @property-read Collection<int, Option> $options
 */
#[Fillable(['course_id', 'text', 'correct_option'])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Sorunun ait olduğu ders.
     *
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Sorunun şıkları.
     *
     * @return HasMany<Option, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('label');
    }

    /**
     * Bu sorunun kullanıldığı sınav cevapları.
     *
     * @return HasMany<ExamAnswer, $this>
     */
    public function examAnswers(): HasMany
    {
        return $this->hasMany(ExamAnswer::class);
    }

    /**
     * Belirli bir derse ait soruları filtrele.
     *
     * @param  Builder<Question>  $query
     */
    #[Scope]
    protected function forCourse(Builder $query, Course $course): void
    {
        $query->whereBelongsTo($course);
    }
}
