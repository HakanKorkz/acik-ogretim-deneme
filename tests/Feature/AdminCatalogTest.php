<?php

use App\Enums\CourseLevel;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('ders listesi kademeye göre süzülür ve aranabilir', function () {
    Course::factory()->create(['name' => 'Tarih-1']);
    Course::factory()->create(['name' => 'Biyoloji-1']);
    Course::factory()->create(['name' => 'Matematik-5', 'level' => CourseLevel::Ortaokul]);

    $this->actingAs($this->admin)
        ->get(route('admin.courses.index', ['level' => 'lise', 'search' => 'tarih']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/courses/index')
            ->has('courses', 1)
            ->where('courses.0.name', 'Tarih-1')
            ->where('levels.0.count', 2)
            ->where('levels.1.count', 1));

    $this->actingAs($this->admin)
        ->get(route('admin.courses.index', ['level' => 'ortaokul']))
        ->assertInertia(fn ($page) => $page->has('courses', 1)->where('filters.level', 'ortaokul'));
});

test('yönetici kataloğu panelden eşitler', function () {
    Http::fake(['aokul.gov.tr/*' => Http::response(file_get_contents(base_path('tests/Fixtures/aokul/ortaokul.html')))]);

    $this->actingAs($this->admin)
        ->post(route('admin.catalog.sync'), ['level' => 'ortaokul'])
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, '32 yeni'));

    expect(Course::query()->where('level', CourseLevel::Ortaokul)->count())->toBe(32);
});

test('katalog alınamazsa panel hata gösterir', function () {
    Http::fake(['aokul.gov.tr/*' => Http::response('', 500)]);

    $this->actingAs($this->admin)
        ->post(route('admin.catalog.sync'), ['level' => 'lise'])
        ->assertSessionHas('error');
});

test('düzenleme ekranı aralık önizlemesini gösterir', function () {
    $course = Course::factory()->withText(6)->create(['page_from' => 2, 'page_to' => 3, 'page_offset' => 1]);

    $this->actingAs($this->admin)
        ->get(route('admin.courses.edit', $course))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/courses/edit')
            ->where('scopePreview.pages', 2)
            ->where('scopePreview.first.pdfPage', 3)
            ->where('scopePreview.first.printedPage', 2)
            ->where('scopePreview.last.pdfPage', 4));
});

test('yönetici aralığı, kaydırmayı ve yayın durumunu günceller', function () {
    $course = Course::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.courses.update', $course), [
            'page_from' => 10,
            'page_to' => 50,
            'page_offset' => 4,
            'is_active' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($course->refresh()->only(['page_from', 'page_to', 'page_offset', 'is_active']))
        ->toBe(['page_from' => 10, 'page_to' => 50, 'page_offset' => 4, 'is_active' => true]);

    $this->actingAs($this->admin)
        ->put(route('admin.courses.update', $course), ['page_from' => 10, 'page_to' => 50])
        ->assertSessionHasNoErrors();

    expect($course->refresh()->is_active)->toBeFalse();
});

test('bitiş sayfası başlangıçtan küçük olamaz', function () {
    $course = Course::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.courses.update', $course), ['page_from' => 50, 'page_to' => 10])
        ->assertSessionHasErrors('page_to');
});

test('ön yüz yalnızca seçilen kademedeki aktif dersleri listeler', function () {
    Course::factory()->create(['name' => 'Aktif Lise']);
    Course::factory()->inactive()->create(['name' => 'Pasif Lise']);
    Course::factory()->create(['name' => 'Aktif Ortaokul', 'level' => CourseLevel::Ortaokul]);

    $this->get(route('courses.index'))
        ->assertInertia(fn ($page) => $page
            ->where('level', 'lise')
            ->has('courses', 1)
            ->where('courses.0.name', 'Aktif Lise'));

    $this->get(route('courses.index', ['level' => 'ortaokul']))
        ->assertInertia(fn ($page) => $page->has('courses', 1)->where('courses.0.name', 'Aktif Ortaokul'));
});
