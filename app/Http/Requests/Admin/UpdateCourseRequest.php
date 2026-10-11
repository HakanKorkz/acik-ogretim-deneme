<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCourseRequest extends FormRequest
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
            'page_from' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'page_to' => ['nullable', 'integer', 'min:1', 'max:2000', 'gte:page_from'],
            'page_offset' => ['nullable', 'integer', 'min:-20', 'max:200'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'page_from' => 'başlangıç sayfası',
            'page_to' => 'bitiş sayfası',
            'page_offset' => 'sayfa kaydırması',
        ];
    }
}
