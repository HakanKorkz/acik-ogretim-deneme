<?php

namespace App\Http\Requests\Admin;

use App\Enums\CourseLevel;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    /**
     * Yetki rota katmanında (`can:access-admin`) denetlenir.
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
            'level' => ['required', Rule::enum(CourseLevel::class)],
            'subject' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'page_from' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'page_to' => ['nullable', 'integer', 'min:1', 'max:2000', 'gte:page_from'],
            ...ReplaceBookRequest::fileRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['subject' => 'ders grubu', 'name' => 'ders adı', 'book' => 'PDF dosyası'];
    }
}
