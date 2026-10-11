<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\ExamSection;
use App\Models\ExamSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSection>
 */
class ExamSectionFactory extends Factory
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
            'course_id' => Course::factory(),
            'position' => 1,
            'total_questions' => 10,
        ];
    }
}
