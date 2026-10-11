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
 * @property int|null $exam_section_id
 * @property int $question_id
 * @property int|null $option_id
 * @property int $position Kitapçıktaki genel sıra.
 * @property int|null $number Dersin bölümündeki soru numarası (1–10).
 * @property list<int>|null $option_order Bu sınavdaki şık gösterim sırası.
 * @property bool $is_correct
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ExamSession $examSession
 * @property-read Question $question
 * @property-read Option|null $option
 */
#[Fillable(['exam_session_id', 'exam_section_id', 'question_id', 'option_id', 'position', 'number', 'option_order', 'is_correct'])]
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
     * Cevabın ait olduğu ders bölümü.
     *
     * @return BelongsTo<ExamSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ExamSection::class, 'exam_section_id');
    }

    /**
     * Sorunun şıkları, bu sınavdaki gösterim sırasıyla ve A–D olarak
     * yeniden etiketlenmiş hâlde. Aynı soru başka bir sınavda farklı sırayla
     * görünür; doğruluk şıkkın kimliğiyle denetlendiği için etkilenmez.
     *
     * @return list<array{id: int, label: string, text: string, is_correct: bool}>
     */
    public function displayedOptions(): array
    {
        $options = $this->question->options->keyBy('id');
        $order = $this->option_order ?? $options->keys()->all();
        $labels = ['A', 'B', 'C', 'D', 'E'];
        $displayed = [];

        foreach (array_values($order) as $index => $optionId) {
            $option = $options->get($optionId);

            if ($option === null) {
                continue;
            }

            $displayed[] = [
                'id' => $option->id,
                'label' => $labels[$index] ?? $option->label,
                'text' => $option->text,
                'is_correct' => $option->is_correct,
            ];
        }

        return $displayed;
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
            'option_order' => 'array',
        ];
    }
}
