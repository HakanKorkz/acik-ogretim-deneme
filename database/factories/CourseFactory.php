<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $words = array_map(
            static fn (): string => fake()->unique()->word(),
            range(1, 3),
        );

        $name = Str::title(implode(' ', $words));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'file_path' => 'dersler/'.Str::slug($name).'.pdf',
            'description' => fake()->sentence(),
        ];
    }
}
