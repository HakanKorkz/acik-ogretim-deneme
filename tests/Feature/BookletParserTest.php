<?php

use App\Services\PastExams\BookletParser;

function parsedBooklet(): array
{
    return (new BookletParser)->parse(
        json_decode(file_get_contents(base_path('tests/Fixtures/past-exam/mart-2026-1-pages.json')), true),
        json_decode(file_get_contents(base_path('tests/Fixtures/past-exam/mart-2026-1-answer-words.json')), true),
    );
}

test('gerçek kitapçık ders bölümlerine ayrılır ve her bölümün cevap anahtarı bulunur', function () {
    $sections = collect(parsedBooklet());

    expect($sections)->toHaveCount(48)
        ->and($sections->every(fn (array $section): bool => count($section['answer_key']) === 10))->toBeTrue()
        ->and($sections->pluck('name'))->toContain('SEÇMELİ ORTAK TÜRK TARİHİ – 1', 'SAĞLIK BİLGİSİ VE TRAFİK KÜLTÜRÜ – 2', 'SEÇ. TÜRK DİLİ VE EDEBİYATI – 3');

    $tarih = $sections->firstWhere('code', '132');

    expect($tarih['name'])->toBe('TARİH – 2')
        ->and($tarih['content'])->toContain('Hiyeroglif adı verilen bir duvar yazısı');
});

dataset('bilinen cevap anahtarları', [
    ['111', 'BCACDBDBCA'],
    ['132', 'DDACBDACBA'],
    ['544', 'CCBABABDBC'],
    ['547', 'AADABBCCDC'],
    ['611', 'BACDCDAABD'],
    ['817', 'ACBDBCADDB'],
    ['974', 'CACADBDBAC'],
    ['999', 'CCDBADBDBA'],
]);

test('cevap anahtarı sütun konumuna göre doğru derse atanır', function (string $code, string $expected) {
    $section = collect(parsedBooklet())->firstWhere('code', $code);

    expect(implode('', $section['answer_key']))->toBe($expected);
})->with('bilinen cevap anahtarları');

test('aynı kodu paylaşan yabancı dil dersleri ayrı tutulur', function () {
    $languages = collect(parsedBooklet())->where('code', '181');

    expect($languages->pluck('name')->sort()->values()->all())->toBe(['ALMANCA – 1', 'FRANSIZCA – 1', 'İNGİLİZCE – 1'])
        ->and($languages->pluck('answer_key')->map(fn (array $key): string => implode('', $key))->unique())->toHaveCount(3);
});
