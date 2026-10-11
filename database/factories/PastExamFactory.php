<?php

namespace Database\Factories;

use App\Models\PastExam;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PastExam>
 */
class PastExamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $session = fake()->numberBetween(1, 3);

        return [
            'title' => "14-15 Mart 2026 – {$session}. oturum",
            'period' => '14-15 Mart 2026',
            'session_number' => $session,
            'source_url' => 'https://aol.meb.gov.tr/meb_iys_dosyalar/'.fake()->unique()->uuid().'.pdf',
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
