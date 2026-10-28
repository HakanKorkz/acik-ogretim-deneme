<?php

namespace App\Services\Questions;

use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuestionEditor
{
    /**
     * Şıklar da sürüme dahildir; aynı saniyede yapılan düzenlemeler bile
     * birbirinin içeriğini sessizce ezmemelidir.
     */
    public function version(Question $question): string
    {
        $question->loadMissing('options');

        return hash('sha256', json_encode([
            $question->only(['id', 'passage', 'text', 'correct_option', 'source_page', 'updated_at', 'deleted_at']),
            $question->options->map->only(['id', 'label', 'text', 'is_correct'])->all(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array{version: string, passage: string|null, text: string, correct_option: string, source_page: int|null, options: array<string, string>}  $data
     */
    public function update(Question $question, array $data): Question
    {
        return DB::transaction(function () use ($question, $data): Question {
            $current = Question::withTrashed()->lockForUpdate()->findOrFail($question->id);
            $current->load('options');

            if ($current->trashed() || ! hash_equals($this->version($current), $data['version'])) {
                throw ValidationException::withMessages(['version' => 'Bu soru siz düzenlerken değişmiş. Soru listesine dönüp güncel sürümü açın.']);
            }

            $attributes = [
                'passage' => $data['passage'],
                'text' => $data['text'],
                'correct_option' => $data['correct_option'],
                'source_page' => $data['source_page'],
            ];

            if ($current->only(array_keys($attributes)) === $attributes
                && $current->options->pluck('text', 'label')->all() === $data['options']
                && $current->options->where('is_correct', true)->pluck('label')->all() === [$data['correct_option']]) {
                return $current;
            }

            $replacement = $current->replicate()->unsetRelations();
            $replacement->fill($attributes)->save();
            $replacement->options()->createMany(collect($data['options'])->map(
                static fn (string $text, string $label): array => ['label' => $label, 'text' => $text, 'is_correct' => $label === $data['correct_option']],
            )->values()->all());
            $current->delete();

            return $replacement;
        });
    }
}
