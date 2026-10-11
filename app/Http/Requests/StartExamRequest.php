<?php

namespace App\Http\Requests;

use App\Enums\CourseLevel;
use App\Enums\QuestionPool;
use App\Enums\ResultMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $level = CourseLevel::tryFrom((string) $this->input('level'));

        return [
            'level' => ['required', Rule::enum(CourseLevel::class)],
            'courses' => ['required', 'array', 'min:1', 'max:'.(int) config('exam.max_courses_per_exam')],
            'courses.*' => [
                'string',
                'distinct',
                Rule::exists('courses', 'slug')
                    ->where('is_active', true)
                    ->where('level', $level->value ?? ''),
            ],
            'pool' => ['required', Rule::enum(QuestionPool::class)],
            'result_mode' => ['required', Rule::enum(ResultMode::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'courses.required' => 'En az bir ders seçin.',
            'courses.min' => 'En az bir ders seçin.',
            'courses.max' => 'En fazla :max ders seçebilirsiniz.',
            'courses.*.exists' => 'Seçilen ders bu kademede bulunmuyor.',
        ];
    }
}
