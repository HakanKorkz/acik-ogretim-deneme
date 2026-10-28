<?php

use App\Services\PastExams\BookletParser;
use App\Services\Pdf\ColumnTextExtractor;

test('sütunlu gerçek kitapçıkta komşu dersin soruları akaid bölümüne karışmaz', function () {
    $xml = file_get_contents(__DIR__.'/../Fixtures/past-exam/mart-2026-1-akaid-layout.html');

    $pages = (new ColumnTextExtractor)->extract($xml);
    $sections = (new BookletParser)->parse($pages, []);
    $akaid = array_values(array_filter($sections, fn (array $section): bool => $section['code'] === '817'))[0];

    expect($akaid['content'])
        ->toContain('Akaid dersinde bir öğretmen', '10. Kur’an-ı Kerim ve hadis')
        ->not->toContain('İsrafın önlenmesi', 'Suffe ile ilgili', 'Kelam ilminin doğuşu');

    expect(strpos($akaid['content'], '3. “Tehdit'))
        ->toBeLessThan(strpos($akaid['content'], '4. “İnsanlardan'));
    expect(strpos($akaid['content'], '7. Akaid'))
        ->toBeLessThan(strpos($akaid['content'], '8. Allah’ın'));
});

test('iki sütunlu kitapçıkta ikinci ders ilk dersin devamından sonra başlar', function () {
    $xml = <<<'XML'
    <doc>
      <page width="600"><line xMin="30" yMin="20"><word>Kapak</word></line></page>
      <page width="600">
        <line xMin="330" yMin="80"><word>1.</word><word>İkinci dersin sorusu</word></line>
        <line xMin="330" yMin="40"><word>(222)</word><word>İKİNCİ – 1</word></line>
        <line xMin="30" yMin="200"><word>2.</word><word>İlk dersin devamı</word></line>
        <line xMin="30" yMin="80"><word>1.</word><word>İlk dersin sorusu</word></line>
        <line xMin="30" yMin="40"><word>(111)</word><word>İLK – 1</word></line>
      </page>
    </doc>
    XML;

    $pages = (new ColumnTextExtractor)->extract($xml);
    $sections = (new BookletParser)->parse($pages, []);

    expect($sections)->toHaveCount(2);
    expect($sections[0]['content'])->toContain('İlk dersin devamı')->not->toContain('İkinci dersin sorusu');
    expect($sections[1]['content'])->toBe('1. İkinci dersin sorusu');
});

test('belirsiz sütun düzeninde düz metinle devam edilmez', function () {
    $xml = '<doc><page width="600"/><page width="600">';

    foreach ([30, 180, 330, 480] as $x) {
        foreach ([1, 2] as $number) {
            $xml .= "<line xMin=\"{$x}\" yMin=\"100\"><word>{$number}.</word><word>Soru</word></line>";
        }
    }

    $xml .= '</page></doc>';

    expect(fn () => (new ColumnTextExtractor)->extract($xml))
        ->toThrow(RuntimeException::class, 'sütun düzeni');
});
