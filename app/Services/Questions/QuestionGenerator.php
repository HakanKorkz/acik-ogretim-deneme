<?php

namespace App\Services\Questions;

/**
 * Bir metin kaynağından çoktan seçmeli sorular üreten servis sözleşmesi.
 *
 * Farklı bir üretim yöntemi (ör. harici bir yapay zeka servisi) eklemek için
 * bu sözleşmeyi uygulayan yeni bir sınıf yazıp container'da bağlamak yeterlidir.
 */
interface QuestionGenerator
{
    /**
     * Kaynak metinden çoktan seçmeli sorular üretir.
     *
     * @return array<int, array{
     *     text: string,
     *     correct_option: string,
     *     options: array<int, array{label: string, text: string, is_correct: bool}>
     * }>
     */
    public function generate(string $sourceText, int $count = 10): array;
}
