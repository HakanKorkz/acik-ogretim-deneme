<?php

use App\Services\Pdf\PdfTextExtractor;

test('PDF sayfaları sırasıyla ayrı ayrı çıkarılır', function () {
    $pages = app(PdfTextExtractor::class)->extractPages(base_path('tests/Fixtures/pdf/uc-sayfa.pdf'));

    expect($pages)->toHaveCount(3)
        ->and(array_keys($pages))->toBe([1, 2, 3])
        ->and($pages[1])->toContain('Kapak')
        ->and($pages[2])->toContain('Birinci bolum metni.')
        ->and($pages[3])->toContain('Ikinci bolum metni.');
});

test('olmayan dosya için anlaşılır hata verilir', function () {
    app(PdfTextExtractor::class)->extractPages('/yok/kitap.pdf');
})->throws(RuntimeException::class, 'PDF dosyası okunamadı');

test('konumlu PDF çıkarımı sayfa numaralarını ve metni korur', function () {
    $pages = app(PdfTextExtractor::class)->extractColumnPages(base_path('tests/Fixtures/pdf/uc-sayfa.pdf'));

    expect(array_keys($pages))->toBe([1, 2, 3]);
    expect($pages[2])->toContain('Birinci bolum metni.');
    expect($pages[3])->toContain('Ikinci bolum metni.');
});
