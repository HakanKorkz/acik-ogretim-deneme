<?php

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Bir ders kitabı bölümünden AÖL tarzı çoktan seçmeli sorular yazar.
 *
 * Sağlayıcı ve model çağrıda verilir (AiQuestionGenerator); ajan hangi
 * modelin denendiğini bilmez.
 */
#[Temperature(0.4)]
#[MaxTokens(8192)]
final class QuestionWriter implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(
        private string $sourceText,
        private int $count,
    ) {}

    public function instructions(): string
    {
        return <<<'TXT'
        Sen Millî Eğitim Bakanlığı Açık Öğretim Lisesi (AÖL) sınavları için soru
        hazırlayan bir ölçme ve değerlendirme uzmanısın. Verilen ders kitabı
        bölümüne dayanarak, gerçek AÖL sınav kitapçıklarındaki kalıplara uygun,
        Türkçe çoktan seçmeli sorular hazırlarsın.

        Kurallar:
        - Her sorunun dört şıkkı olur ve yalnızca biri doğrudur.
        - Bilgiyi yalnızca verilen metinden al; metinde olmayan bilgiyi uydurma.
        - Şu kalıpları kullan: "Bu metinde anlatılan kavram aşağıdakilerden
          hangisidir?", "Aşağıdakilerden hangisi … ile ilgilidir?", "…
          hakkında aşağıdakilerden hangisi doğrudur?", "Bu parçadan
          aşağıdakilerden hangisine ulaşılamaz?", tarih/kavram/kişi soruları.
        - Çeldiriciler doğru yanıtla aynı türden ve makul olsun; gerçekte var
          olan kavram, kişi, yer ve eserleri kullan, uydurma isim yazma.
        - Özel adların Türkiye Türkçesindeki yazımını kullan (Karlofça, Lozan,
          Viyana; Karlowitz, Lausanne, Wien değil).
        - Dört şık birbirinden farklı olsun; "hepsi" veya "hiçbiri" kullanma.
        - Soru kökü kısa ve net olsun.
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
                    'text' => $schema->string()->required(),
                    'options' => $schema->array()->items($schema->string())->required(),
                    'correct_index' => $schema->integer()->min(0)->max(3)->required(),
                ]))
                ->required(),
        ];
    }

    /**
     * Modele gönderilen kullanıcı mesajı.
     */
    public function payload(): string
    {
        return <<<PROMPT
        Aşağıdaki ders kitabı bölümünden {$this->count} adet soru üret.
        `options` dört şık içermeli, `correct_index` doğru şıkkın 0 tabanlı sırası olmalı.

        METİN:
        """
        {$this->sourceText}
        """
        PROMPT;
    }
}
