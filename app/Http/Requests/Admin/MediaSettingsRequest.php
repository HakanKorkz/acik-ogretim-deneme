<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MediaSettingsRequest extends FormRequest
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
            'default_target' => ['required', Rule::in(['local', 'cloudinary'])],
            'cloudinary_cloud_name' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/'],
            'cloudinary_api_key' => ['nullable', 'string', 'max:100'],
            'cloudinary_api_secret' => ['nullable', 'string', 'max:200'],
            'clear_credentials' => ['boolean'],
            'cloudinary_folder' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9_\/-]+$/'],
            'cloudinary_max_megabytes' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
