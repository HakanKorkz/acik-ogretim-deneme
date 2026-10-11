<?php

namespace Database\Factories;

use App\Models\BookPage;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookPage>
 */
class BookPageFactory extends Factory
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
            'page_number' => fake()->unique()->numberBetween(1, 400),
            'content' => fake()->paragraphs(3, true),
        ];
    }
}
