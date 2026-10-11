<?php

namespace App\Services\Questions;

/**
 * Üretilen bir sorunun kitapçıkta tek başına anlamlı olup olmadığını denetler.
 *
 * Yapay zekâ modelleri bazen öncülleri yazmadan "Yukarıdakilerden hangileri
 * doğrudur?" diye sorar, giriş metni olmadan "metne göre" der ya da ders
 * kitabındaki alıştırma numaralarını ("21. soru") şık yapar. Bu sorular
 * öğrenciye gösterilmeden elenir.
 *
 * @phpstan-import-type GeneratedQuestion from QuestionGenerator
 */
class QuestionQualityGuard
{
    /**
     * Öncül isteyen kök ya da şık ("Yalnız I", "I ve II", "I, II ve III").
     */
    private const PREMISE_STEM = '/\b(yukarıdaki(?:ler(?:den|in))?|hangileri)\b/iu';

    private const PREMISE_OPTION = '/^(yalnız\s+)?(I{1,3}|IV|V)(\s*(,|ve)\s*(I{1,3}|IV|V))*\.?$/u';

    /**
     * Giriş metnine gönderme yapan kök.
     */
    private const PASSAGE_REFERENCE = '/(\b(bu|yukarıdaki)\s+(metin|parça|bilgi|ayet|hadis|şiir|tablo|örnek|açıklama)|\b(metne|metinde|metinden|metnin|parçaya|parçada|parçadan|parçanın)\b)/iu';

    /**
     * Ders kitabının kendi alıştırma/değerlendirme bölümlerine atıf.
     */
    private const TEXTBOOK_EXERCISE = '/(\b\d+\.\s*soru\b|\betkinli[kğ]|ünite değerlendirme|ölçme ve değerlendirme)/iu';

    /**
     * AÖL kitapçığında görülmeyen açık uçlu kök.
     */
    private const OPEN_ENDED = '/\b(nedir|nelerdir|kimdir|kimlerdir|nerededir|ne zamandır|niçin|neden)\s*\?\s*$/iu';

    /**
     * @param  GeneratedQuestion  $question
     */
    public function passes(array $question): bool
    {
        return $this->problem($question) === null;
    }

    /**
     * Sorunun neden elendiği; geçerliyse null.
     *
     * @param  GeneratedQuestion  $question
     */
    public function problem(array $question): ?string
    {
        $stem = trim($question['text']);
        $passage = trim((string) ($question['passage'] ?? ''));
        $optionTexts = array_map(static fn (array $option): string => trim($option['text']), $question['options']);

        if (! str_contains($stem, '?')) {
            return 'Soru kökü soru cümlesi değil.';
        }

        if (preg_match(self::OPEN_ENDED, $stem)) {
            return 'Açık uçlu kök (nedir/nelerdir).';
        }

        $hasPremises = (bool) preg_match('/(^|\s)I\.\s.+(^|\s)II\.\s/su', $passage);
        $asksPremises = preg_match(self::PREMISE_STEM, $stem)
            || array_filter($optionTexts, static fn (string $text): bool => (bool) preg_match(self::PREMISE_OPTION, $text)) !== [];

        if ($asksPremises && ! $hasPremises) {
            return 'Öncüllü soru ama öncüller yok.';
        }

        if ($passage === '' && preg_match(self::PASSAGE_REFERENCE, $stem)) {
            return 'Giriş metnine gönderme var ama giriş metni yok.';
        }

        foreach ([$stem, ...$optionTexts] as $text) {
            if (preg_match(self::TEXTBOOK_EXERCISE, $text)) {
                return 'Ders kitabının alıştırmalarına atıf.';
            }
        }

        return null;
    }
}
