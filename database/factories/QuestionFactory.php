<?php

namespace Database\Factories;

use App\Enums\QuestionSource;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'text' => '______ boşluğu en doğru şekilde tamamlayan seçenek hangisidir? '.fake()->sentence(),
            'correct_option' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'source' => QuestionSource::Ai,
        ];
    }

    /**
     * MEB çıkmış sınav sorusu.
     */
    public function pastExam(): static
    {
        return $this->state(fn (array $attributes): array => ['source' => QuestionSource::PastExam]);
    }

    /**
     * Soruya A-D arası dört şık ekler; doğru şık `correct_option` ile eşleşir.
     */
    public function withOptions(): static
    {
        return $this->afterCreating(function (Question $question): void {
            $correctLabel = $question->correct_option;

            foreach (['A', 'B', 'C', 'D'] as $label) {
                $isCorrect = $label === $correctLabel;

                $question->options()->create([
                    'label' => $label,
                    'text' => $isCorrect ? 'Doğru seçenek' : "Çeldirici seçenek {$label}",
                    'is_correct' => $isCorrect,
                ]);
            }
        });
    }
}
