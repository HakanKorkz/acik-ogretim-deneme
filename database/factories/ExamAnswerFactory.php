<?php

namespace Database\Factories;

use App\Models\ExamAnswer;
use App\Models\ExamSession;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamAnswer>
 */
class ExamAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_session_id' => ExamSession::factory(),
            'question_id' => Question::factory(),
            'option_id' => null,
            'position' => fake()->numberBetween(1, 10),
            'is_correct' => false,
        ];
    }
}
