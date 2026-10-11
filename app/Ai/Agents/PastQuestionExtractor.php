<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\DisablesReasoning;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Çıkmış sınav kitapçığından çıkarılmış bir ders bölümünün ham metnini
 * soru soru yapılandırır. Metin sütunlu sayfadan geldiği için şıkların ve
 * soruların sırası karışık olabilir; içerik DEĞİŞTİRİLMEDEN ayrıştırılır.
 */
#[Temperature(0)]
#[MaxTokens(8192)]
final class PastQuestionExtractor implements Agent, HasProviderOptions, HasStructuredOutput
{
    use DisablesReasoning, Promptable;

    public function __construct(
        private string $courseHeading,
        private string $sectionText,
    ) {}

    public function instructions(): string
    {
        return <<<'TXT'
        Sana MEB Açık Öğretim Lisesi sınav kitapçığının bir ders bölümünün PDF'ten
        çıkarılmış ham metni verilecek. Metin iki-üç sütunlu sayfadan geldiği için
        bazı şıklar ya da soru parçaları yanlış yerde görünebilir (ör. 5. sorunun
        B ve D şıkları 9. sorudan sonra). Görevin soruları numaralarına göre
        eksiksiz ve AYNEN yeniden kurmak.

        Kurallar:
        - Metni yeniden yazma, düzeltme ya da özetleme; kelimeleri aynen kullan.
          Yalnızca satır sonu tirelemesini birleştir ("yolla-\nrıyla" → "yollarıyla").
        - `number`: kitapçıktaki soru numarası.
        - `passage`: Soru kökünden önce gelen metin, ayet, öncüller (I., II., III.)
          ya da madde işaretli bilgiler. Yoksa boş metin.
        - `text`: Soru kökü (genellikle "…aşağıdakilerden hangisidir?" ile biter).
        - `options`: A, B, C, D şıklarının metinleri, harf sırasıyla.
        - `needs_figure`: Soru bir görsel, grafik, tablo, harita, şekil ya da
          formül olmadan anlaşılamıyorsa ya da şıklar görsel ise true.
        - Sayfa üst/alt bilgilerini ("1. OTURUM", sayfa numaraları) yok say.
        - Metinde eksik/okunamayan bir soru varsa onu listeye ekleme.
        TXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()
                ->items($schema->object(fn (JsonSchema $schema): array => [
                    'number' => $schema->integer()->min(1)->required(),
                    'passage' => $schema->string()->required(),
                    'text' => $schema->string()->required(),
                    'options' => $schema->array()->items($schema->string())->required(),
                    'needs_figure' => $schema->boolean()->required(),
                ]))
                ->required(),
        ];
    }

    public function payload(): string
    {
        return <<<PROMPT
        Ders: {$this->courseHeading}

        HAM METİN:
        """
        {$this->sectionText}
        """
        PROMPT;
    }
}
