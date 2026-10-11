<?php

use App\Enums\QuestionGenerationStatus;
use App\Jobs\GenerateCourseQuestions;
use App\Models\Course;
use App\Models\Question;
use App\Services\Questions\QuestionBankImporter;
use Illuminate\Support\Facades\Queue;

test('web üzerinden üretim kapalıyken istek reddedilir', function () {
    config()->set('exam.web_generation', false);
    Queue::fake();
    $course = Course::factory()->create();

    $this->post(route('courses.questions.store', $course))->assertForbidden();

    Queue::assertNothingPushed();
});

test('üretim isteği kuyruğa alınır ve ders sırada olarak işaretlenir', function () {
    config()->set('exam.web_generation', true);
    Queue::fake();
    $course = Course::factory()->create();

    $this->from(route('courses.index'))
        ->post(route('courses.questions.store', $course))
        ->assertRedirect(route('courses.index'))
        ->assertSessionHas('success');

    Queue::assertPushed(GenerateCourseQuestions::class, fn (GenerateCourseQuestions $job): bool => $job->course->is($course));

    expect($course->refresh()->question_generation_status)->toBe(QuestionGenerationStatus::Queued)
        ->and($course->isGeneratingQuestions())->toBeTrue();
});

test('devam eden üretim varken yeni iş kuyruğa alınmaz', function () {
    config()->set('exam.web_generation', true);
    Queue::fake();
    $course = Course::factory()->create();
    $course->markQuestionGeneration(QuestionGenerationStatus::Running);

    $this->post(route('courses.questions.store', $course))->assertSessionHas('error');

    Queue::assertNothingPushed();
});

test('takılı kalmış eski üretim durumu yeni üretimi engellemez', function () {
    config()->set('exam.web_generation', true);
    Queue::fake();
    $course = Course::factory()->create();
    $course->markQuestionGeneration(QuestionGenerationStatus::Running);

    $this->travel(config('exam.generation_stale_after_minutes') + 1)->minutes();

    $this->post(route('courses.questions.store', $course))->assertSessionHas('success');

    Queue::assertPushed(GenerateCourseQuestions::class);
});

test('soru üretim isteği IP başına sınırlandırılır', function () {
    config()->set('exam.web_generation', true);
    Queue::fake();

    foreach (range(1, 3) as $attempt) {
        $this->post(route('courses.questions.store', Course::factory()->create()))->assertRedirect();
    }

    $this->post(route('courses.questions.store', Course::factory()->create()))->assertTooManyRequests();
});

test('başarılı iş üretim durumunu temizler', function () {
    $course = Course::factory()->create();
    $course->markQuestionGeneration(QuestionGenerationStatus::Queued);

    $this->mock(QuestionBankImporter::class)
        ->shouldReceive('import')
        ->once()
        ->andReturn(40);

    app()->call([new GenerateCourseQuestions($course), 'handle']);

    expect($course->refresh()->question_generation_status)->toBeNull();
});

test('başarısız iş dersi başarısız olarak işaretler', function () {
    $course = Course::factory()->create();

    (new GenerateCourseQuestions($course))->failed(new RuntimeException('hata'));

    expect($course->refresh()->question_generation_status)->toBe(QuestionGenerationStatus::Failed)
        ->and($course->isGeneratingQuestions())->toBeFalse();
});

test('ders listesi üretim durumunu ve sınav uzunluğunu gösterir', function () {
    config()->set('exam.questions_per_exam', 20);
    config()->set('exam.web_generation', true);
    $course = Course::factory()->create();
    $course->markQuestionGeneration(QuestionGenerationStatus::Running);
    Question::factory()->count(3)->withOptions()->for(Course::factory())->create();

    $this->get(route('courses.index'))
        ->assertInertia(fn ($page) => $page
            ->where('questionsPerExam', 20)
            ->where('canGenerateQuestions', true)
            ->where('courses', fn ($courses): bool => collect($courses)->firstWhere('id', $course->id)['is_generating'] === true));
});
