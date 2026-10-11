<?php

namespace App\Http\Requests\Admin;

use App\Enums\AiProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiSettingsRequest extends FormRequest
{
    /**
     * Yetki rota katmanında (`can:access-admin`) denetlenir.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Modeller panelde satır satır yazılır; doğrulamadan önce listeye çevrilir.
     */
    protected function prepareForValidation(): void
    {
        $models = $this->input('models');

        if (is_string($models)) {
            $this->merge([
                'models' => array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $models) ?: []))),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(AiProvider::class)],
            'openrouter_key' => ['nullable', 'string', 'max:500'],
            'openai_key' => ['nullable', 'string', 'max:500'],
            'anthropic_key' => ['nullable', 'string', 'max:500'],
            'clear_keys' => ['array'],
            'clear_keys.*' => [Rule::enum(AiProvider::class)],
            'models' => ['required', 'array', 'min:1', 'max:10'],
            'models.*' => ['string', 'max:150', 'regex:/^[A-Za-z0-9._:\/@-]+$/'],
            'questions_per_request' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'provider' => 'sağlayıcı',
            'models' => 'modeller',
            'models.*' => 'model',
            'questions_per_request' => 'istek başına soru',
        ];
    }
}
