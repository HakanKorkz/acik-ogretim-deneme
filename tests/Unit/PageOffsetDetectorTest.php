<?php

use App\Services\Books\PageOffsetDetector;

test('sayfa kenarındaki basılı numaralardan kaydırma bulunur', function () {
    $pages = [
        1 => "Kapak\nDers Kitabı",
        2 => "İçindekiler\nÜnite 1 ........ 3",
        3 => "1\nBirinci ünite giriş metni.",
        4 => "Metin devam ediyor.\n2",
        5 => "3\nÜçüncü sayfa.",
        6 => "Dördüncü sayfa metni.\n4",
    ];

    expect((new PageOffsetDetector)->detect($pages))->toBe(2);
});

test('yeterli kanıt yoksa kaydırma tahmin edilmez', function () {
    $pages = [
        1 => 'Kapak',
        2 => "Metin\n1",
        3 => 'Numarasız sayfa',
    ];

    expect((new PageOffsetDetector)->detect($pages))->toBeNull();
});

test('metin içindeki sayılar kaydırmayı bozmaz', function () {
    $pages = [];

    foreach (range(1, 10) as $pdfPage) {
        $pages[$pdfPage] = "{$pdfPage}\nSatır\nOrta satırda 1920 yılı ve 45 sayısı geçer.\nSon satır";
    }

    expect((new PageOffsetDetector)->detect($pages))->toBe(0);
});
