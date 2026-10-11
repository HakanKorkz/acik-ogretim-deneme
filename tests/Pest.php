<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Soru üretici testlerinde kullanılan örnek metin.
 */
function examGeneratorSampleText(): string
{
    return <<<'TEXT'
    Edebiyat, insanların duygularını ve düşüncelerini estetik bir biçimde anlatan bir sanattır. Roman, olayları geniş bir zaman diliminde ve ayrıntılı biçimde anlatan uzun bir türdür. Hikâye, kısa ve özlü anlatımıyla okuyucuyu derinden etkileyen bir türdür. Tiyatro, sahnede sergilenmek üzere yazılan metinlerden oluşan bir sanattır. Deneme, yazarın kendi düşüncelerini samimi bir üslupla okuyucuya aktardığı bir türdür. Makale, bir konuyu kanıtlarla açıklayan bilimsel bir yazı türüdür. Biyografi, tanınmış kişilerin yaşam öykülerini anlatan bir eserdir. Destan, milletlerin kahramanlıklarını anlatan uzun manzum bir eserdir. Masal, olağanüstü olayları hayal gücüyle anlatan geleneksel bir türdür. Fıkra, güncel olayları kısa ve alaycı biçimde yorumlayan bir yazıdır.
    TEXT;
}
