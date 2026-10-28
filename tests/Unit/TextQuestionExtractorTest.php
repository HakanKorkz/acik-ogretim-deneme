<?php

use App\Services\PastExams\BookletParser;
use App\Services\PastExams\TextQuestionExtractor;
use App\Services\Pdf\ColumnTextExtractor;

test('akaid bölümündeki on metin sorusu kaynak sırasıyla eksiksiz ayrıştırılır', function () {
    $xml = file_get_contents(__DIR__.'/../Fixtures/past-exam/mart-2026-1-akaid-layout.html');
    $pages = (new ColumnTextExtractor)->extract($xml);
    $sections = (new BookletParser)->parse($pages, []);
    $akaid = array_values(array_filter($sections, fn (array $section): bool => $section['code'] === '817'))[0];

    $questions = (new TextQuestionExtractor)->extract($akaid['content'], 10);

    expect($questions)->toHaveCount(10);
    expect(array_column($questions, 'number'))->toBe(range(1, 10));
    expect($questions[6]['passage'])->toContain('Akaid dersinde bir öğretmen', '3. Öğrenci:');
    expect($questions[6]['text'])->toContain('araştırmıştır?');
    expect($questions[6]['options'])->toBe(['Metîn', 'Rahim', 'Hâfız', 'Settâr']);
    expect($questions[9]['options'][3])->toBe('İnsanlardan sonra yaratılmışlardır.');
});

test('eksik karışık veya görsele bağlı metin doğrudan kabul edilmez', function (string $text, int $count) {
    expect((new TextQuestionExtractor)->extract($text, $count))->toBeNull();
})->with([
    'eksik şık' => ["1. Hangisidir?\nA) Bir\nB) İki\nC) Üç", 1],
    'karışık şık sırası' => ["1. Hangisidir?\nA) Bir\nC) Üç\nB) İki\nD) Dört", 1],
    'görsel atfı' => ["1. Şekle göre hangisidir?\nA) Bir\nB) İki\nC) Üç\nD) Dört", 1],
    'atlanan soru numarası' => ["1. Hangisidir?\nA) Bir\nB) İki\nC) Üç\nD) Dört\n3. Hangisidir?\nA) Bir\nB) İki\nC) Üç\nD) Dört", 2],
    'boş son şık' => ["1. Hangisidir?\nA) Bir\nB) İki\nC) Üç\nD)", 1],
    'eksik soru kökü' => ["1. Bu metne göre\nA) Bir\nB) İki\nC) Üç\nD) Dört", 1],
]);

test('satır sonu tireleri birleştirilir ve şık devamı korunur', function () {
    $questions = (new TextQuestionExtractor)->extract("1. Aşağıdaki kavram-\nlardan hangisidir?\nA) Birinci\nseçenek\nB) İkinci\nC) Üçüncü\nD) Dördüncü", 1);

    expect($questions[0]['text'])->toBe('Aşağıdaki kavramlardan hangisidir?');
    expect($questions[0]['options'][0])->toBe('Birinci seçenek');
});

test('görsel atfı olmayan şekilde sözcüğü metin aktarımını engellemez', function () {
    $questions = (new TextQuestionExtractor)->extract("1. Bu şekilde açıklanan kavram hangisidir?\nA) Bir\nB) İki\nC) Üç\nD) Dört", 1);

    expect($questions)->toHaveCount(1);
});
