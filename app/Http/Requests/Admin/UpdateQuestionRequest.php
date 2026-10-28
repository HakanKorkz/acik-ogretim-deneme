<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionRequest extends FormRequest
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
            'version' => ['required', 'string', 'size:64'],
            'passage' => ['nullable', 'string', 'max:10000'],
            'text' => ['required', 'string', 'max:10000'],
            'options' => ['required', 'array:A,B,C,D', 'size:4'],
            'options.A' => ['required', 'string', 'max:10000'],
            'options.B' => ['required', 'string', 'max:10000'],
            'options.C' => ['required', 'string', 'max:10000'],
            'options.D' => ['required', 'string', 'max:10000'],
            'options.*' => ['distinct:strict'],
            'correct_option' => ['required', Rule::in(['A', 'B', 'C', 'D'])],
            'source_page' => ['nullable', 'integer', 'min:1', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'version.required' => 'Soru sürümü eksik. Sayfayı yenileyip tekrar deneyin.',
            'version.size' => 'Soru sürümü geçerli değil. Sayfayı yenileyin.',
            'text.required' => 'Soru kökünü yazın.',
            'text.max' => 'Soru kökü en fazla 10000 karakter olabilir.',
            'passage.max' => 'Giriş metni en fazla 10000 karakter olabilir.',
            'options.required' => 'Dört şıkkı da doldurun.',
            'options.array' => 'Yalnızca A, B, C ve D şıkları kullanılabilir.',
            'options.size' => 'Tam olarak dört şık girin.',
            'options.*.required' => 'Bu şıkkın metnini yazın.',
            'options.*.max' => 'Şık metni en fazla 10000 karakter olabilir.',
            'options.*.distinct' => 'Şık metinleri birbirinden farklı olmalıdır.',
            'correct_option.required' => 'Doğru cevabı seçin.',
            'correct_option.in' => 'Doğru cevap A, B, C veya D olmalıdır.',
            'source_page.integer' => 'Kaynak sayfası bir tam sayı olmalıdır.',
            'source_page.min' => 'Kaynak sayfası en az 1 olmalıdır.',
            'source_page.max' => 'Kaynak sayfası en fazla 2000 olabilir.',
        ];
    }
}
