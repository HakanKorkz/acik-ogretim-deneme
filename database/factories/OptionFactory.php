<?php

namespace Database\Factories;

use App\Models\Option;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Option>
 */
class OptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'label' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'text' => fake()->sentence(),
            'is_correct' => false,
        ];
    }

    /**
     * Doğru şık olarak işaretler.
     */
    public function correct(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_correct' => true,
        ]);
    }
}
