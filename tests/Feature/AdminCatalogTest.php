<?php

use App\Enums\CourseLevel;
use App\Models\Course;
use App\Models\Question;
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
            ->has('courses.data', 1)
            ->where('courses.data.0.name', 'Tarih-1')
            ->where('levels.0.count', 2)
            ->where('levels.1.count', 1));

    $this->actingAs($this->admin)
        ->get(route('admin.courses.index', ['level' => 'ortaokul']))
        ->assertInertia(fn ($page) => $page->has('courses.data', 1)->where('filters.level', 'ortaokul'));
});

test('ders listesi varsayılan olarak yalnızca yirmi beş ders gönderir', function () {
    Course::factory()->count(31)->sequence(fn ($sequence) => [
        'name' => sprintf('Ders %02d', $sequence->index + 1),
        'sort_order' => $sequence->index,
    ])->create();

    $this->actingAs($this->admin)
        ->get(route('admin.courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('courses.data', 25)
            ->where('courses.total', 31)
            ->where('courses.per_page', 25)
            ->where('courses.current_page', 1)
            ->where('courses.last_page', 2)
            ->where('courses.data.0.name', 'Ders 01')
            ->where('courses.data.24.name', 'Ders 25'));
});

test('arama bütün kayıtları süzer ve istenen sayfanın derslerini gönderir', function () {
    Course::factory()->count(23)->sequence(fn ($sequence) => [
        'name' => sprintf('Tarih %02d', $sequence->index + 1),
        'sort_order' => $sequence->index,
    ])->create();
    Course::factory()->create(['name' => 'Biyoloji']);
    Course::factory()->create(['name' => 'Tarih Ortaokul', 'level' => CourseLevel::Ortaokul]);

    $this->actingAs($this->admin)
        ->get(route('admin.courses.index', ['search' => 'Tarih', 'page' => 2, 'per_page' => 10]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('courses.data', 10)
            ->where('courses.total', 23)
            ->where('courses.current_page', 2)
            ->where('courses.from', 11)
            ->where('courses.to', 20)
            ->where('courses.data.0.name', 'Tarih 11')
            ->where('courses.data.9.name', 'Tarih 20')
            ->where('filters.search', 'Tarih')
            ->where('levels.0.count', 24)
            ->where('levels.1.count', 1)
            ->where('courses.next_page_url', fn (string $url): bool => str_contains($url, 'search=Tarih') && str_contains($url, 'per_page=10') && str_contains($url, 'page=3')));
});

test('soru sayısına göre sıralama yalnızca geçerli sayfaya değil tüm derslere uygulanır', function () {
    Course::factory()->count(10)->create();
    $most = Course::factory()->create(['name' => 'Z Çok Sorulu']);
    $least = Course::factory()->create(['name' => 'Y Az Sorulu']);
    Question::factory()->count(3)->for($most)->create();
    Question::factory()->for($least)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.courses.index', ['sort' => 'questionsCount', 'direction' => 'desc', 'per_page' => 10]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('courses.data', 10)
            ->where('courses.total', 12)
            ->where('courses.data.0.id', $most->id)
            ->where('courses.data.0.questionsCount', 3)
            ->where('courses.data.1.id', $least->id)
            ->where('filters.sort', 'questionsCount')
            ->where('filters.direction', 'desc'));
});

test('ders adına ve yayın durumuna göre istenen yönde sıralanır', function (string $sort, string $direction, string $first) {
    Course::factory()->inactive()->create(['name' => 'A Ders']);
    Course::factory()->create(['name' => 'Z Ders']);

    $this->actingAs($this->admin)
        ->get(route('admin.courses.index', ['sort' => $sort, 'direction' => $direction]))
        ->assertInertia(fn ($page) => $page->where('courses.data.0.name', $first));
})->with([
    ['name', 'asc', 'A Ders'],
    ['name', 'desc', 'Z Ders'],
    ['isActive', 'asc', 'A Ders'],
    ['isActive', 'desc', 'Z Ders'],
]);

test('geçersiz liste boyutu sayfa veya sıralama alanı reddedilir', function (array $query, string $field) {
    $this->actingAs($this->admin)
        ->get(route('admin.courses.index', $query))
        ->assertSessionHasErrors($field);
})->with([
    [['per_page' => 1000], 'per_page'],
    [['per_page' => 0], 'per_page'],
    [['per_page' => 'all'], 'per_page'],
    [['page' => -1], 'page'],
    [['page' => 1000001], 'page'],
    [['sort' => 'name desc; DROP TABLE courses'], 'sort'],
    [['direction' => 'invalid'], 'direction'],
]);

test('yüz ders sınırındaki son sayfa ve boş sonuçlar doğru döner', function () {
    fake()->unique(true);

    Course::factory()->state(function (): array {
        fake()->unique(true);

        return [];
    })->count(101)->sequence(fn ($sequence) => [
        'name' => 'Sınır Dersi '.$sequence->index,
        'slug' => 'sinir-dersi-'.$sequence->index,
    ])->create();

    $this->actingAs($this->admin)
        ->get(route('admin.courses.index', ['per_page' => 100, 'page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->has('courses.data', 1)
            ->where('courses.total', 101)
            ->where('courses.from', 101)
            ->where('courses.to', 101));

    $this->get(route('admin.courses.index', ['search' => 'bulunmayan-ders']))
        ->assertInertia(fn ($page) => $page->has('courses.data', 0)->where('courses.total', 0));
});

test('otomatik yenileme yalnızca istenen ders sayfasını getirir', function () {
    Course::factory()->count(26)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.courses.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->reloadOnly('courses', fn ($reload) => $reload
                ->has('courses.data', 1)
                ->where('courses.current_page', 2)
                ->where('courses.total', 26)
                ->missing('levels')
                ->missing('filters')));
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

    $this->get(route('courses.index', 'lise'))
        ->assertInertia(fn ($page) => $page
            ->where('level.value', 'lise')
            ->has('courses', 1)
            ->where('courses.0.name', 'Aktif Lise'));

    $this->get(route('courses.index', 'ortaokul'))
        ->assertInertia(fn ($page) => $page->has('courses', 1)->where('courses.0.name', 'Aktif Ortaokul'));
});

test('yönetici dersi tek tıkla yayına alır ve yayından kaldırır', function () {
    $course = Course::factory()->inactive()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.courses.publish', $course))
        ->assertSessionHas('success');

    expect($course->refresh()->is_active)->toBeTrue();

    $this->actingAs($this->admin)
        ->post(route('admin.courses.publish', $course))
        ->assertSessionHas('success');

    expect($course->refresh()->is_active)->toBeFalse();
});
