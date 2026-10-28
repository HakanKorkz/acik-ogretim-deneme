<?php

use App\Enums\ImportStatus;
use App\Models\Course;
use App\Models\PastExam;
use App\Models\PastExamSection;
use App\Models\User;

test('kitapçık listesi yalnızca bir sayfa ve bölüm özetlerini getirir', function () {
    $exams = PastExam::factory()->count(31)->sequence(fn ($sequence) => ['sort_order' => $sequence->index])->create();
    PastExamSection::factory()->for($exams->first())->create(['code' => '111', 'status' => ImportStatus::Done, 'imported_count' => 7]);
    PastExamSection::factory()->for($exams->first())->create(['code' => '112', 'status' => ImportStatus::Queued, 'content' => str_repeat('Ham metin ', 1000)]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.past-exams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pastExams.data', 25)
            ->where('pastExams.total', 31)
            ->where('pastExams.data.0.sectionsCount', 2)
            ->where('pastExams.data.0.importedQuestions', 7)
            ->where('pastExams.data.0.pendingSections', 1)
            ->missing('pastExams.data.0.sections'));
});

test('kitapçık arama ve durum filtresi sayfalama öncesinde uygulanır', function () {
    PastExam::factory()->count(12)->sequence(fn ($sequence) => [
        'title' => 'Mart '.$sequence->index,
        'sort_order' => $sequence->index,
        'status' => ImportStatus::Done,
    ])->create();
    PastExam::factory()->create(['title' => 'Mart Bekliyor', 'status' => null]);
    PastExam::factory()->create(['title' => 'Aralık', 'status' => ImportStatus::Done]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.past-exams.index', ['search' => 'Mart', 'status' => 'done', 'page' => 2, 'per_page' => 10]))
        ->assertInertia(fn ($page) => $page
            ->has('pastExams.data', 2)
            ->where('pastExams.total', 12)
            ->where('pastExams.current_page', 2)
            ->where('filters.search', 'Mart')
            ->where('filters.status', 'done'));

    $this->get(route('admin.past-exams.index', ['status' => 'unprocessed']))
        ->assertInertia(fn ($page) => $page->has('pastExams.data', 1)->where('pastExams.data.0.title', 'Mart Bekliyor'));
});

test('aktarılan soru sıralaması bütün kitapçıklara uygulanır', function () {
    PastExam::factory()->count(10)->create();
    $last = PastExam::factory()->create(['sort_order' => 999]);
    PastExamSection::factory()->for($last)->create(['imported_count' => 10]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.past-exams.index', ['sort' => 'importedQuestions', 'direction' => 'desc', 'per_page' => 10]))
        ->assertInertia(fn ($page) => $page
            ->has('pastExams.data', 10)
            ->where('pastExams.total', 11)
            ->where('pastExams.data.0.id', $last->id)
            ->where('pastExams.data.0.importedQuestions', 10));
});

test('kitapçık başlığı ve durum sıralaması istenen yönde uygulanır', function (string $sort, string $direction, string $first) {
    PastExam::factory()->create(['title' => 'A Kitapçık', 'status' => ImportStatus::Done]);
    PastExam::factory()->create(['title' => 'Z Kitapçık', 'status' => ImportStatus::Failed]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.past-exams.index', ['sort' => $sort, 'direction' => $direction]))
        ->assertInertia(fn ($page) => $page->where('pastExams.data.0.title', $first));
})->with([
    ['title', 'asc', 'A Kitapçık'],
    ['title', 'desc', 'Z Kitapçık'],
    ['status', 'asc', 'A Kitapçık'],
    ['status', 'desc', 'Z Kitapçık'],
]);

test('bölüm ayrıntıları yalnızca istenen kitapçık için sayfalı ve ham metinsiz gelir', function () {
    $booklet = PastExam::factory()->create();
    $course = Course::factory()->create();
    PastExamSection::factory()->for($booklet)->for($course)->count(26)
        ->sequence(fn ($sequence) => ['code' => (string) (100 + $sequence->index)])
        ->create(['status' => ImportStatus::Failed, 'imported_count' => 3]);
    PastExamSection::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.past-exams.sections', $booklet))
        ->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('total', 26)
        ->assertJsonPath('data.0.course', $course->name)
        ->assertJsonPath('data.0.canRetry', true)
        ->assertJsonMissingPath('data.0.content')
        ->assertJsonMissingPath('data.0.answer_key');

    $this->getJson(route('admin.past-exams.sections', [$booklet, 'page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('current_page', 2);
});

test('devam eden veya eşleşmeyen bölüm yeniden denemeye açılmaz', function () {
    $booklet = PastExam::factory()->create();
    PastExamSection::factory()->for($booklet)->for(Course::factory())->create(['code' => '111', 'status' => ImportStatus::Queued]);
    PastExamSection::factory()->for($booklet)->create(['code' => '112', 'course_id' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.past-exams.sections', $booklet))
        ->assertOk()
        ->assertJsonPath('data.0.canRetry', false)
        ->assertJsonPath('data.1.canRetry', false);
});

test('bölüm ayrıntıları yönetici erişimi gerektirir', function () {
    $booklet = PastExam::factory()->create();

    $this->get(route('admin.past-exams.sections', $booklet))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->getJson(route('admin.past-exams.sections', $booklet))->assertForbidden();
});

test('olmayan kitapçığın bölüm ayrıntıları bulunamadı döner', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.past-exams.sections', 999999))
        ->assertNotFound();
});

test('bölüm ayrıntısında geçersiz sayfa reddedilir', function () {
    $booklet = PastExam::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->getJson(route('admin.past-exams.sections', [$booklet, 'page' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('page');
});

test('geçersiz kitapçık tablo seçenekleri reddedilir', function (array $query, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.past-exams.index', $query))
        ->assertSessionHasErrors($field);
})->with([
    [['per_page' => 1000], 'per_page'],
    [['page' => -1], 'page'],
    [['sort' => 'source_url'], 'sort'],
    [['status' => 'invalid'], 'status'],
    [['direction' => 'invalid'], 'direction'],
]);

test('kitapçık otomatik yenilemesi bölüm ayrıntılarını yüklemez', function () {
    PastExam::factory()->count(26)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.past-exams.index', ['page' => 2]))
        ->assertInertia(fn ($page) => $page
            ->reloadOnly('pastExams', fn ($reload) => $reload
                ->has('pastExams.data', 1)
                ->missing('pastExams.data.0.sections')
                ->missing('filters')
                ->missing('statuses')));
});
