<?php

namespace App\Models;

use Database\Factories\ExamAnswerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $exam_session_id
 * @property int $question_id
 * @property int|null $option_id
 * @property int $position
 * @property bool $is_correct
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExamSession $examSession
 * @property-read Question $question
 * @property-read Option|null $option
 */
#[Fillable(['exam_session_id', 'question_id', 'option_id', 'position', 'is_correct'])]
class ExamAnswer extends Model
{
    /** @use HasFactory<ExamAnswerFactory> */
    use HasFactory;

    /**
     * Cevabın ait olduğu oturum.
     *
     * @return BelongsTo<ExamSession, $this>
     */
    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    /**
     * Cevabın ait olduğu soru. Soru bankası yenilenip soru arşivlenmiş olsa
     * bile geçmiş sonuçlar görüntülenebilsin diye arşivlenenler de dâhildir.
     *
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    /**
     * Kullanıcının seçtiği şık (boş bırakılmışsa null).
     *
     * @return BelongsTo<Option, $this>
     */
    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class);
    }

    /**
     * Soru cevaplanmış mı?
     */
    public function isAnswered(): bool
    {
        return $this->option_id !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }
}
