<?php

namespace Database\Factories;

use App\Models\PastExam;
use App\Models\PastExamSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PastExamSection>
 */
class PastExamSectionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'past_exam_id' => PastExam::factory(),
            'code' => (string) fake()->numberBetween(100, 999),
            'name' => 'TARİH – 2',
            'content' => '1. Örnek soru kökü aşağıdakilerden hangisidir? A) Bir B) İki C) Üç D) Dört',
            'answer_key' => ['1' => 'A'],
        ];
    }
}
