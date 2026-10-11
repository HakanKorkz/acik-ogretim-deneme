<?php

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\ExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSession>
 */
class ExamSessionFactory extends Factory
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
            'user_id' => null,
            'total_questions' => 10,
            'correct_count' => null,
            'status' => ExamStatus::InProgress,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    /**
     * Tamamlanmış sınav oturumu.
     */
    public function completed(int $correctCount = 7): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ExamStatus::Completed,
            'correct_count' => $correctCount,
            'completed_at' => now(),
        ]);
    }
}
