<?php

use App\Enums\TextStatus;
use App\Jobs\ExtractCourseText;
use App\Models\Course;
use App\Models\User;
use App\Services\Books\CourseTextExtractor;
use App\Services\Pdf\PdfTextExtractor;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

test('MEB kitabı indirilir, sayfaları kaydedilir ve geçici dosya silinir', function () {
    Http::fake([
        'aokul.gov.tr/*' => Http::response(
            file_get_contents(base_path('tests/Fixtures/pdf/uc-sayfa.pdf')),
            200,
            ['Last-Modified' => 'Thu, 24 Sep 2026 18:23:00 GMT', 'Content-Length' => '1234'],
        ),
    ]);

    $course = Course::factory()->aokul()->create();

    $saved = app(CourseTextExtractor::class)->extract($course);

    $course->refresh();

    expect($saved)->toBe(3)
        ->and($course->text_status)->toBe(TextStatus::Ready)
        ->and($course->page_count)->toBe(3)
        ->and($course->source_size)->toBe(1234)
        ->and($course->source_last_modified->toDateTimeString())->toBe('2026-09-24 18:23:00')
        ->and($course->pages()->pluck('page_number')->all())->toBe([1, 2, 3])
        ->and($course->pages()->where('page_number', 2)->value('content'))->toContain('Birinci bolum metni.')
        ->and(File::glob(storage_path('app/private/tmp/*.pdf')))->toBe([]);
});

test('kaydırma tespit edilir ve aralıktaki sayfalar basılı numaraya göre seçilir', function () {
    $this->mock(PdfTextExtractor::class)
        ->shouldReceive('extractPages')
        ->andReturn([
            1 => 'Kapak',
            2 => 'İçindekiler',
            3 => "1\nGiriş",
            4 => "Bölüm\n2",
            5 => "3\nDevam",
            6 => "Son\n4",
        ]);

    $course = Course::factory()->create(['page_from' => 2, 'page_to' => 3]);
    File::ensureDirectoryExists(public_path('dersler'));
    File::put(public_path($course->file_path), '%PDF');

    try {
        app(CourseTextExtractor::class)->extract($course);
    } finally {
        File::delete(public_path($course->file_path));
    }

    $course->refresh();

    expect($course->page_offset)->toBe(2)
        ->and($course->pagesInScope()->pluck('page_number')->all())->toBe([4, 5]);
});

test('indirme başarısız olursa anlaşılır hata verilir', function () {
    Http::fake(['aokul.gov.tr/*' => Http::response('yok', 404)]);

    app(CourseTextExtractor::class)->extract(Course::factory()->aokul()->create());
})->throws(RuntimeException::class, 'Kitap indirilemedi (HTTP 404)');

test('yönetici metin çıkarma işini kuyruğa alır', function () {
    Queue::fake();
    $course = Course::factory()->aokul()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.courses.text.store', $course))
        ->assertSessionHas('success');

    Queue::assertPushed(ExtractCourseText::class);

    expect($course->refresh()->text_status)->toBe(TextStatus::Queued)
        ->and($course->isExtractingText())->toBeTrue();
});

test('başarısız metin işi dersi işaretler', function () {
    $course = Course::factory()->aokul()->create();

    (new ExtractCourseText($course))->failed(new RuntimeException('hata'));

    expect($course->refresh()->text_status)->toBe(TextStatus::Failed);
});
