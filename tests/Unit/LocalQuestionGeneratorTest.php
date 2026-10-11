<?php

use App\Services\Questions\LocalQuestionGenerator;

test('metinden yapısal olarak geçerli çoktan seçmeli sorular üretir', function () {
    $questions = (new LocalQuestionGenerator)->generate(examGeneratorSampleText(), 10);

    expect($questions)->not->toBeEmpty();
    expect(count($questions))->toBeLessThanOrEqual(10);

    foreach ($questions as $question) {
        expect($question['text'])->not->toBeEmpty();

        expect($question['options'])->toHaveCount(4);

        expect(collect($question['options'])->pluck('label')->all())
            ->toBe(['A', 'B', 'C', 'D']);

        $correctOptions = collect($question['options'])->where('is_correct', true);

        expect($correctOptions)->toHaveCount(1);

        expect($question['correct_option'])->toBe($correctOptions->first()['label']);

        $optionTexts = collect($question['options'])->pluck('text');
        expect($optionTexts->unique())->toHaveCount(4);
    }
});

test('uygun cümle bulunamazsa soru üretmez', function () {
    expect((new LocalQuestionGenerator)->generate('Kısa bir metin.', 10))->toBe([]);
});

test('sıfırdan küçük soru sayısı istenirse boş dizi döner', function () {
    expect((new LocalQuestionGenerator)->generate(examGeneratorSampleText(), 0))->toBe([]);
});

test('boşluk doldurmada unvanlar özel isim çeldiricisi olarak kullanılmaz', function () {
    $text = <<<'TEXT'
    Kurtuluş Savaşı yıllarında Hacı Bayram Camii çevresindeki halk, Ankara’da toplanan Meclisi büyük bir heyecanla destekledi.
    Düşman ordusu İzmir’den içerilere doğru ilerlerken Hacı Ömer Efendi köylüleri cepheye yardım için örgütledi.
    Batı cephesindeki kuvvetler Bursa’da yeniden düzenlenerek yeni bir savunma hattı oluşturmaya çalıştı.
    Halide Edip Hanım, Konya’da yapılan büyük mitingde halkı bağımsızlık mücadelesine katılmaya çağırdı.
    Ordunun ihtiyaçları için toplanan erzak, Sivas’tan gelen kağnılarla uzun yollar aşılarak cepheye taşındı.
    Mustafa Kemal, Samsun’a çıktıktan sonra milli mücadeleyi örgütlemek için Anadolu’yu dolaşmaya başladı.
    TEXT;

    $questions = (new LocalQuestionGenerator)->generate($text, 10);

    $optionTexts = collect($questions)->flatMap(fn (array $question): array => array_column($question['options'], 'text'));

    expect($questions)->not->toBeEmpty()
        ->and($optionTexts->map(fn (string $text): string => mb_strtolower($text, 'UTF-8'))->intersect(['hacı', 'hanım', 'efendi']))->toBeEmpty();
});

test('yıl sorularında çeldiriciler doğru yanıta yakın yıllardır', function () {
    $text = <<<'TEXT'
    Osmanlı donanmasının Rus filosu tarafından yakıldığı Çeşme bozgunu 1770 yılında yaşandı ve ağır sonuçlar doğurdu.
    Bu yenilginin ardından imzalanan Küçük Kaynarca Antlaşması ile Kırım bağımsız bir devlet hâline getirildi.
    TEXT;

    $questions = (new LocalQuestionGenerator)->generate($text, 1);

    expect($questions)->toHaveCount(1);

    foreach ($questions[0]['options'] as $option) {
        expect($option['text'])->toMatch('/^\d{4}$/')
            ->and(abs((int) $option['text'] - 1770))->toBeLessThanOrEqual(60);
    }
});

test('çok sözcüklü özel adların bir parçası boşluk olarak sorulmaz', function () {
    $text = <<<'TEXT'
    Papalık ve Malta donanmalarının desteğiyle Venedikliler Sakız Adası’nı kısa bir süre için ele geçirdi.
    Osmanlı donanması daha sonra yeniden toparlanarak Venedik’ten gelen gemileri Sakız’da ağır bir yenilgiye uğrattı.
    Barış görüşmeleri uzun sürünce elçiler Viyana’da toplanarak yeni bir antlaşma metni üzerinde çalıştı.
    Lehistan’dan gelen elçiler de görüşmelere katılarak Kamaniçe meselesini yeniden gündeme getirdi.
    TEXT;

    $questions = (new LocalQuestionGenerator)->generate($text, 10);

    foreach ($questions as $question) {
        expect($question['text'])->not->toContain('Sakız ______')
            ->and(collect($question['options'])->pluck('text'))->not->toContain('Adası');
    }
});
