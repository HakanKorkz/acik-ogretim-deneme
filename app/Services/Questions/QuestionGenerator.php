<?php

namespace App\Services\Questions;

/**
 * Bir metin kaynağından çoktan seçmeli sorular üreten servis sözleşmesi.
 *
 * Farklı bir üretim yöntemi eklemek için bu sözleşmeyi uygulayan yeni bir
 * sınıf yazıp container'da bağlamak yeterlidir.
 *
 * @phpstan-type GeneratedQuestion array{
 *     text: string,
 *     passage?: string|null,
 *     correct_option: string,
 *     options: array<int, array{label: string, text: string, is_correct: bool}>,
 *     source?: string,
 *     source_page?: int|null
 * }
 */
interface QuestionGenerator
{
    /**
     * Kaynak metinden çoktan seçmeli sorular üretir. Metin `[Sayfa N]`
     * işaretleri içerebilir; destekleyen üreticiler sorunun sayfasını bildirir.
     *
     * - `text`: soru kökü; `passage`: varsa giriş metni (parça, öncüller).
     * - `source`: `ai` ya da `local`.
     *
     * `$styleExamples`, aynı dersin gerçek çıkmış sorularıdır; destekleyen
     * üreticiler bunları yalnızca üslup örneği olarak kullanır.
     *
     * @param  list<string>  $styleExamples
     * @return list<GeneratedQuestion>
     */
    public function generate(string $sourceText, int $count = 10, array $styleExamples = []): array;
}
