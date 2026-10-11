<?php

use App\Services\Questions\QuestionQualityGuard;

function guardQuestion(string $text, ?string $passage = null, array $options = ['Tevekkül', 'Tefekkür', 'Tevazu', 'Teklif']): array
{
    return [
        'text' => $text,
        'passage' => $passage,
        'correct_option' => 'A',
        'options' => array_map(
            fn (string $option, int $index): array => ['label' => ['A', 'B', 'C', 'D'][$index], 'text' => $option, 'is_correct' => $index === 0],
            $options,
            array_keys($options),
        ),
    ];
}

dataset('geçerli sorular', [
    'doğrudan kavram' => [guardQuestion('Aşağıdakilerden hangisi tefekkür ile ilgilidir?')],
    'olumsuz kök' => [guardQuestion('Aşağıdakilerden hangisi iman esaslarından biri değildir?')],
    'metinli soru' => [guardQuestion('Bu parçadan aşağıdakilerden hangisine ulaşılabilir?', 'İnsan, ahirette amellerinden hesaba çekilecektir.')],
    'öncüllü soru' => [guardQuestion(
        'Yukarıdakilerden hangileri doğrudur?',
        "I. Akaid inanç esaslarını inceler.\nII. Kelam akla dayalı delil kullanır.\nIII. Fıkıh ibadetleri inceler.",
        ['Yalnız I', 'I ve II', 'II ve III', 'I, II ve III'],
    )],
]);

dataset('elenen sorular', [
    'öncülsüz öncül sorusu' => [guardQuestion('Yukarıdakilerden hangileri doğrudur?', null, ['II ve III', 'Yalnız I', 'I ve II', 'I, II ve III']), 'öncüller yok'],
    'şıklar öncül ama metin yok' => [guardQuestion('Hangisi doğrudur?', 'Akaid inanç esaslarını inceler.', ['Yalnız I', 'I ve II', 'II ve III', 'I, II ve III']), 'öncüller yok'],
    'metinsiz metne göre' => [guardQuestion('Bu metne göre aşağıdakilerden hangisi doğrudur?'), 'giriş metni yok'],
    'metinsiz metinde yer almaz' => [guardQuestion('Kader inancıyla ilgili aşağıdaki kavramlardan hangisi metinde yer almaz?'), 'giriş metni yok'],
    'kitap alıştırması' => [guardQuestion('Akıl ve irade ile ilgili ayetleri sorulan soru hangisidir?', null, ['21. soru', '20. soru', '22. soru', '19. soru']), 'alıştırma'],
    'açık uçlu' => [guardQuestion('Şirk nedir?'), 'Açık uçlu'],
    'soru değil' => [guardQuestion('Tefekkür bir kavramdır.'), 'soru cümlesi değil'],
]);

test('kitapçıkta anlamlı sorular geçer', function (array $question) {
    expect((new QuestionQualityGuard)->problem($question))->toBeNull();
})->with('geçerli sorular');

test('kitapçıkta anlamsız sorular elenir', function (array $question, string $reason) {
    expect((new QuestionQualityGuard)->problem($question))->toContain($reason);
})->with('elenen sorular');
