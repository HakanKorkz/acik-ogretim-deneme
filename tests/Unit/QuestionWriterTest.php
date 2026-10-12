<?php

use App\Ai\Agents\QuestionWriter;

test('özel sistem istemi verilmezse yerleşik varsayılan kullanılır', function () {
    $instructions = (new QuestionWriter('örnek metin', 1))->instructions();

    expect($instructions)->toBe(QuestionWriter::defaultInstructions())
        ->and($instructions)->toContain('AÖL');
});

test('özel sistem istemi verilirse varsayılan yerine o kullanılır', function () {
    $writer = new QuestionWriter('örnek metin', 1, [], 'Yalnızca şu yönergeye uy.');

    expect($writer->instructions())->toBe('Yalnızca şu yönergeye uy.');
});
