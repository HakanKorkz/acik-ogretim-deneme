<?php

use App\Services\Catalog\PageRange;

dataset('katalog açıklamaları', [
    'sayfasına kadar' => [
        "Akaid 1 dersini seçen öğrenciler, Akaid 11. Sınıf Ders Kitabı'nın 83. sayfasına kadar (83. sayfa dâhil) yer alan temalardan sorumludur.",
        null, 83,
    ],
    'sayfasından sonuna kadar' => [
        "Akaid 2 dersini seçen öğrenciler, Akaid 11. Sınıf Ders Kitabı'nın 84. sayfasından ders kitabının sonuna kadar yer alan temalardan sorumludur.",
        84, null,
    ],
    'Sayfaya kadar (büyük harf)' => [
        'Biyoloji 1 dersini seçen öğrenciler sınavlarda Biyoloji 9 Sınıf Ders Kitabından 79. Sayfaya kadar (79 dâhil) Temalardan sorumludur.',
        null, 79,
    ],
    'Sayfadan sonuna kadar (büyük harf)' => [
        'Kimya 2 dersini seçen öğrenciler sınavlarda Kimya 9 Sınıf Ders Kitabından 137. Sayfadan ders kitabının sonuna kadar olan Temalardan sorumludur.',
        137, null,
    ],
    'kadarki bitişik' => [
        'Tarih 1 dersini seçen öğrenciler sınavlarda Tarih 9 Sınıf Ders Kitabından 123. Sayfaya kadarki (123 dâhil) Temalardan sorumludur.',
        null, 123,
    ],
    'iki sayfa arası' => [
        'İngilizce 2 dersini seçen öğrenciler sınavlarda İngilizce ders kitabından 79. sayfadan 134. sayfaya kadar olan temalardan sorumludur.',
        79, 134,
    ],
    'çalışma kitabı da geçiyor' => [
        "İngilizce 3 dersini seçen öğrenciler, İngilizce 10. Sınıf Ders Kitabı'nın 80. sayfası (80. sayfa dâhil) ile İngilizce 10. Sınıf Çalışma Kitabı'nın 32. sayfasına kadar (32. sayfa dâhil) yer alan temalardan sorumludur.",
        null, 80,
    ],
    'sayfa geçmiyor' => [
        'SEÇMELİ EKONOMİ 1 DERSİNİ SEÇEN ÖĞRENCİLER (EKONOMİ 1-2) DERS KİTABINDAN SORUMLUDUR.',
        null, null,
    ],
]);

test('katalog açıklamasından sorumluluk aralığı çıkarılır', function (string $description, ?int $from, ?int $to) {
    $range = PageRange::fromDescription($description);

    expect($range->from)->toBe($from)
        ->and($range->to)->toBe($to);
})->with('katalog açıklamaları');

test('boş açıklama kitabın tamamıdır', function () {
    expect(PageRange::fromDescription(null)->isWholeBook())->toBeTrue();
});
