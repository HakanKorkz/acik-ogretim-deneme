<?php

namespace App\Http\Requests\Admin;

use App\Enums\CourseLevel;
use App\Enums\QuestionSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
        return [
            'level' => ['nullable', Rule::enum(CourseLevel::class)],
            'course' => ['nullable', 'string', Rule::exists('courses', 'slug')],
            'source' => ['nullable', Rule::enum(QuestionSource::class)],
            'publication' => ['nullable', Rule::in(['all', 'active', 'inactive'])],
            'metadata' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'level.enum' => 'Geçerli bir kademe seçin.',
            'course.exists' => 'Seçilen ders bulunamadı.',
            'source.enum' => 'Geçerli bir soru kaynağı seçin.',
            'publication.in' => 'Geçerli bir yayın durumu seçin.',
            'metadata.boolean' => 'Kaynak bilgisi seçimi geçerli değil.',
        ];
    }
}
