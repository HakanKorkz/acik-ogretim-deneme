<?php

use App\Enums\CourseLevel;
use App\Enums\CourseSource;
use App\Models\Course;
use App\Services\Catalog\AokulCatalog;
use App\Services\Catalog\CatalogEntry;
use App\Services\Catalog\CatalogSynchronizer;
use Illuminate\Support\Facades\Http;

function fakeAokulCatalog(): void
{
    Http::fake([
        'aokul.gov.tr/Kitaplar/Kitaplar.aspx?type=2' => Http::response(file_get_contents(base_path('tests/Fixtures/aokul/lise.html'))),
        'aokul.gov.tr/Kitaplar/Kitaplar.aspx?type=1' => Http::response(file_get_contents(base_path('tests/Fixtures/aokul/ortaokul.html'))),
    ]);
}

function catalogEntry(array $overrides = []): CatalogEntry
{
    return new CatalogEntry(...[
        'subject' => 'AKAİD',
        'name' => 'Akaid-1',
        'externalKey' => '2/1/1',
        'pdfUrl' => 'http://aokul.gov.tr/Kitaplar/Kitaplar/2/1/1/akaid_1_2.pdf',
        'description' => "Akaid 11. Sınıf Ders Kitabı'nın 83. sayfasına kadar (83. sayfa dâhil) yer alan temalardan sorumludur.",
        'sortOrder' => 1,
        ...$overrides,
    ]);
}

test('lise kataloğu gerçek sayfa yapısından ayrıştırılır', function () {
    $entries = app(AokulCatalog::class)->parse(file_get_contents(base_path('tests/Fixtures/aokul/lise.html')));

    expect($entries)->toHaveCount(145);

    $akaid = $entries[0];

    expect($akaid->subject)->toBe('AKAİD')
        ->and($akaid->name)->toBe('Akaid-1')
        ->and($akaid->externalKey)->toBe('2/1/1')
        ->and($akaid->pdfUrl)->toBe('http://aokul.gov.tr/Kitaplar/Kitaplar/2/1/1/akaid_1_2.pdf')
        ->and($akaid->pageRange()->to)->toBe(83);

    $withSpace = collect($entries)->first(fn (CatalogEntry $entry): bool => str_contains($entry->pdfUrl, 'ekonomi'));

    expect($withSpace->pdfUrl)->toContain('ekonomi%201-2.pdf');
});

test('ortaokul kataloğu ayrıştırılır', function () {
    $entries = app(AokulCatalog::class)->parse(file_get_contents(base_path('tests/Fixtures/aokul/ortaokul.html')));

    expect($entries)->toHaveCount(32)
        ->and(collect($entries)->pluck('subject')->unique())->toContain('MATEMATİK');
});

test('beklenmeyen sayfada ders bulunamazsa hata verilir ve veri silinmez', function () {
    Http::fake(['aokul.gov.tr/*' => Http::response('<html><body>Bakımdayız</body></html>')]);
    Course::factory()->aokul()->create();

    expect(fn () => app(CatalogSynchronizer::class)->sync(CourseLevel::Lise))
        ->toThrow(RuntimeException::class, 'ders bulunamadı');

    expect(Course::query()->count())->toBe(1);
});

test('senkronizasyon yeni dersleri pasif ekler, ikinci çalıştırmada değişiklik yapmaz', function () {
    fakeAokulCatalog();
    $synchronizer = app(CatalogSynchronizer::class);

    expect($synchronizer->sync(CourseLevel::Lise))->toBe(['created' => 145, 'updated' => 0, 'unchanged' => 0])
        ->and($synchronizer->sync(CourseLevel::Ortaokul)['created'])->toBe(32)
        ->and($synchronizer->sync(CourseLevel::Lise))->toBe(['created' => 0, 'updated' => 0, 'unchanged' => 145]);

    $akaid = Course::query()->where('slug', 'akaid-2')->sole();

    expect($akaid->is_active)->toBeFalse()
        ->and($akaid->source)->toBe(CourseSource::Aokul)
        ->and($akaid->level)->toBe(CourseLevel::Lise)
        ->and($akaid->page_from)->toBe(84)
        ->and($akaid->page_to)->toBeNull()
        ->and(Course::query()->where('level', CourseLevel::Ortaokul)->where('slug', 'like', 'ortaokul-%')->count())->toBe(32);
});

test('elle düzeltilen aralık açıklama değişmedikçe korunur', function () {
    $synchronizer = app(CatalogSynchronizer::class);
    $synchronizer->apply(CourseLevel::Lise, [catalogEntry()]);

    $course = Course::query()->sole();
    $course->update(['page_from' => 5, 'page_to' => 70]);

    $synchronizer->apply(CourseLevel::Lise, [catalogEntry()]);
    expect($course->refresh()->only(['page_from', 'page_to']))->toBe(['page_from' => 5, 'page_to' => 70]);

    $synchronizer->apply(CourseLevel::Lise, [catalogEntry(['description' => 'Kitabın 90. sayfasına kadar (90. sayfa dâhil) sorumludur.'])]);
    expect($course->refresh()->only(['page_from', 'page_to']))->toBe(['page_from' => null, 'page_to' => 90]);
});

test('metni çıkarılmış dersin PDF adresi değişirse güncelleme işareti konur', function () {
    $synchronizer = app(CatalogSynchronizer::class);
    $synchronizer->apply(CourseLevel::Lise, [catalogEntry()]);

    $course = Course::query()->sole();
    $course->forceFill(['text_extracted_at' => now()])->save();

    $synchronizer->apply(CourseLevel::Lise, [catalogEntry(['pdfUrl' => 'http://aokul.gov.tr/Kitaplar/Kitaplar/2/1/1/akaid_2026.pdf'])]);

    expect($course->refresh()->source_url)->toEndWith('akaid_2026.pdf')
        ->and($course->source_changed_at)->not->toBeNull();
});

test('catalog:sync komutu iki kademeyi de eşitler', function () {
    fakeAokulCatalog();

    $this->artisan('catalog:sync')->assertSuccessful();

    expect(Course::query()->count())->toBe(177);
});

test('catalog:check-updates değişen kitabı işaretler', function () {
    $course = Course::factory()->aokul()->withText()->create([
        'source_last_modified' => now()->subMonth(),
        'source_size' => 1000,
    ]);
    $unchanged = Course::factory()->aokul('2/1/2')->withText()->create([
        'source_last_modified' => now()->subMonth()->startOfSecond(),
        'source_size' => 2000,
    ]);

    Http::fake(function ($request) use ($unchanged) {
        return $request->url() === $unchanged->source_url
            ? Http::response('', 200, ['Content-Length' => '2000', 'Last-Modified' => $unchanged->source_last_modified->toRfc7231String()])
            : Http::response('', 200, ['Content-Length' => '5555', 'Last-Modified' => now()->toRfc7231String()]);
    });

    $this->artisan('catalog:check-updates')->assertSuccessful();

    expect($course->refresh()->source_changed_at)->not->toBeNull()
        ->and($unchanged->refresh()->source_changed_at)->toBeNull();
});
