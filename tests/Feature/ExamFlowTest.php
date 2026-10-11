<?php

use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\ExamSession;
use App\Models\Question;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('exam.questions_per_exam', 10);
});

test('dersler sayfası dersleri ve istatistikleri gösterir', function () {
    Course::factory()->create(['name' => 'Türk Dili ve Edebiyatı']);
    Course::factory()->create(['name' => 'Matematik']);

    $this->get(route('courses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('courses/index')
            ->has('courses', 2)
            ->where('stats.courses', 2));
});

test('sınav başlatılınca oturum ve cevap kayıtları oluşur', function () {
    $course = Course::factory()->create();
    Question::factory()->count(12)->withOptions()->for($course)->create();

    $response = $this->post(route('exams.store', $course));

    $session = ExamSession::sole();

    $response->assertRedirect(route('exams.show', $session));

    expect($session->status)->toBe(ExamStatus::InProgress)
        ->and($session->total_questions)->toBe(10)
        ->and($session->answers()->count())->toBe(10);
});

test('soruları olmayan ders için sınav başlatılamaz', function () {
    $course = Course::factory()->create();

    $this->from(route('courses.index'))
        ->post(route('exams.store', $course))
        ->assertRedirect(route('courses.index'))
        ->assertSessionHas('error');

    expect(ExamSession::count())->toBe(0);
});

test('sınav ekranı doğru cevabı istemciye sızdırmaz', function () {
    $course = Course::factory()->create();
    Question::factory()->count(10)->withOptions()->for($course)->create();

    $this->post(route('exams.store', $course));
    $session = ExamSession::sole();

    $this->get(route('exams.show', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/show')
            ->has('questions', 10)
            ->missing('questions.0.options.0.is_correct'));
});

test('gönderilen cevaplar doğrultusunda skor hesaplanır', function () {
    $course = Course::factory()->create();
    Question::factory()->count(10)->withOptions()->for($course)->create();

    $this->post(route('exams.store', $course));
    $session = ExamSession::sole();

    $answers = [];

    foreach ($session->answers()->with('question.options')->get()->values() as $index => $answer) {
        $options = $answer->question->options;
        $answers[$answer->question_id] = $index < 7
            ? $options->firstWhere('is_correct', true)->id
            : $options->firstWhere('is_correct', false)->id;
    }

    $this->post(route('exams.submit', $session), ['answers' => $answers])
        ->assertRedirect(route('exams.result', $session));

    $session->refresh();

    expect($session->status)->toBe(ExamStatus::Completed)
        ->and($session->correct_count)->toBe(7)
        ->and($session->scorePercentage())->toBe(70);
});

test('sonuç sayfası skoru ve soru değerlendirmesini gösterir', function () {
    $course = Course::factory()->create();
    Question::factory()->count(10)->withOptions()->for($course)->create();

    $this->post(route('exams.store', $course));
    $session = ExamSession::sole();

    $answers = [];

    foreach ($session->answers()->with('question.options')->get() as $answer) {
        $answers[$answer->question_id] = $answer->question->options
            ->firstWhere('is_correct', true)->id;
    }

    $this->post(route('exams.submit', $session), ['answers' => $answers]);

    $this->get(route('exams.result', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/result')
            ->where('session.score', 100)
            ->where('session.correct_count', 10)
            ->has('review', 10));
});

test('sınav oturumları tahmin edilemeyen ULID ile adreslenir', function () {
    $course = Course::factory()->create();
    Question::factory()->count(10)->withOptions()->for($course)->create();

    $this->post(route('exams.store', $course));
    $session = ExamSession::sole();

    expect(route('exams.show', $session))->toEndWith('/exams/'.$session->ulid);

    $this->get('/exams/'.$session->id)->assertNotFound();
});

test('başka bir tarayıcı devam eden sınavı göremez ve gönderemez', function () {
    $course = Course::factory()->create();
    Question::factory()->count(10)->withOptions()->for($course)->create();

    $this->post(route('exams.store', $course));
    $session = ExamSession::sole();

    $this->flushSession();

    $this->get(route('exams.show', $session))->assertNotFound();
    $this->post(route('exams.submit', $session), ['answers' => []])->assertNotFound();

    expect($session->refresh()->status)->toBe(ExamStatus::InProgress);
});

test('tamamlanan sınavın sonucu bağlantıyla paylaşılabilir', function () {
    $course = Course::factory()->create();
    Question::factory()->count(10)->withOptions()->for($course)->create();

    $this->post(route('exams.store', $course));
    $session = ExamSession::sole();
    $this->post(route('exams.submit', $session), ['answers' => []]);

    $this->flushSession();

    $this->get(route('exams.result', $session))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('session.id', $session->ulid));
});
