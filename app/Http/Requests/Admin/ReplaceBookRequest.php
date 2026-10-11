<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReplaceBookRequest extends FormRequest
{
    /**
     * Yetki rota katmanında (`can:access-admin`) denetlenir.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kitap dosyası ve depolama hedefi kuralları (ders eklemede de kullanılır).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function fileRules(): array
    {
        return [
            'book' => ['required', 'file', 'mimes:pdf', 'max:'.((int) config('exam.upload_max_megabytes') * 1024)],
            'target' => ['nullable', Rule::in(['local', 'cloudinary'])],
        ];
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return self::fileRules();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['book' => 'PDF dosyası'];
    }
}
