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
 * Bir ders kitabı bölümünden AÖL tarzı çoktan seçmeli sorular yazar.
 *
 * Sağlayıcı ve model çağrıda verilir (AiQuestionGenerator); ajan hangi
 * modelin denendiğini bilmez.
 */
#[Temperature(0.4)]
#[MaxTokens(8192)]
final class QuestionWriter implements Agent, HasProviderOptions, HasStructuredOutput
{
    use DisablesReasoning, Promptable;

    /**
     * @param  list<string>  $styleExamples  Aynı dersin gerçek çıkmış soruları.
     */
    public function __construct(
        private string $sourceText,
        private int $count,
        private array $styleExamples = [],
    ) {}

    public function instructions(): string
    {
        return <<<'TXT'
        Sen Millî Eğitim Bakanlığı Açık Öğretim Lisesi (AÖL) sınavları için soru
        hazırlayan bir ölçme ve değerlendirme uzmanısın. Verilen ders kitabı
        bölümüne dayanarak, gerçek AÖL sınav kitapçıklarındaki kalıplara uygun,
        Türkçe çoktan seçmeli sorular hazırlarsın.

        Soru yapısı:
        - `passage`: Gerekirse soru kökünden önce gelen giriş metni (kitaptan
          kısa bir parça, bir tanım, bir örnek olay ya da "I. … II. … III. …"
          biçiminde öncüller). Gerekmiyorsa boş metin bırak.
        - `text`: Soru kökü; kısa ve net, soru işaretiyle biter.
        - `options`: Dört şık; yalnızca biri doğru. `correct_index` doğru şıkkın
          0 tabanlı sırasıdır.
        - `page`: Sorunun dayandığı bilginin bulunduğu sayfa; metindeki
          [Sayfa N] işaretinden al. Bilmiyorsan 0 yaz.

        Gerçek kitapçıklardaki dağılıma uy (her 10 soru için yaklaşık):
        - 3–4 soru bir giriş metnine dayansın ("Bu parçadan aşağıdakilerden
          hangisine ulaşılabilir?", "Bu metinde sözü edilen kavram
          aşağıdakilerden hangisidir?", "Bu bilgiler aşağıdakilerden
          hangisiyle ilgilidir?").
        - 1–2 soru olumsuz kök taşısın ("… aşağıdakilerden hangisi
          değildir?", "… hangisi yanlıştır?", "… hangisine ulaşılamaz?",
          "… hangisi dışta kalır?").
        - 1 soru öncüllü olsun ("Yukarıdakilerden hangileri doğrudur?";
          şıklar "Yalnız I", "I ve II", "II ve III", "I, II ve III" gibi).
        - Kalanlar kavram, kişi, olay, tarih ve neden-sonuç bilgisini doğrudan
          sorsun ("Aşağıdakilerden hangisi … ile ilgilidir?", "… hakkında
          aşağıdakilerden hangisi doğrudur?").

        Kurallar:
        - Bilgiyi yalnızca verilen metinden al; metinde olmayan bilgiyi uydurma.
        - Çeldiriciler doğru yanıtla aynı türden ve makul olsun; gerçekte var
          olan kavram, kişi, yer ve eserleri kullan, uydurma isim yazma.
        - Özel adların Türkiye Türkçesindeki yazımını kullan (Karlofça, Lozan,
          Viyana; Karlowitz, Lausanne, Wien değil).
        - Dört şık birbirinden farklı olsun; "hepsi" veya "hiçbiri" kullanma.
        - Soru kökü AÖL kalıbıyla bitsin: "… aşağıdakilerden hangisidir?",
          "… hangisiyle ilgilidir?", "… hangisine ulaşılabilir?". "Nedir?",
          "nelerdir?" gibi açık uçlu kök kullanma.
        - Giriş metni yoksa kökte "metne göre" diye kitaba gönderme yapma; soru
          kitapçıkta tek başına okunacak. Giriş metni varsa "Bu metne/parçaya
          göre" denebilir.
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
                    'passage' => $schema->string()->required(),
                    'text' => $schema->string()->required(),
                    'options' => $schema->array()->items($schema->string())->required(),
                    'correct_index' => $schema->integer()->min(0)->max(3)->required(),
                    'page' => $schema->integer()->min(0)->required(),
                ]))
                ->required(),
        ];
    }

    /**
     * Modele gönderilen kullanıcı mesajı.
     */
    public function payload(): string
    {
        $examples = $this->styleExamples === []
            ? ''
            : "Bu dersin gerçek AÖL sınavlarında sorulmuş örnek sorular. Yalnızca üslup ve\n"
                ."zorluk için örnek al; bunları ya da bilgilerini kopyalama:\n\n"
                .implode("\n\n---\n\n", $this->styleExamples)."\n\n";

        return <<<PROMPT
        {$examples}Aşağıdaki ders kitabı bölümünden {$this->count} adet soru üret.

        METİN:
        """
        {$this->sourceText}
        """
        PROMPT;
    }
}
