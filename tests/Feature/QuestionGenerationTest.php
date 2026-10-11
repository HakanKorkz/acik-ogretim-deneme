<?php

use App\Enums\QuestionGenerationStatus;
use App\Jobs\GenerateCourseQuestions;
use App\Models\Course;
use App\Models\Question;
use App\Models\User;
use App\Services\Questions\QuestionBankImporter;
use Illuminate\Support\Facades\Queue;

test('misafir ve yönetici olmayan kullanıcı soru üretemez', function () {
    Queue::fake();
    $course = Course::factory()->create();

    $this->post(route('admin.courses.questions.store', $course))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->post(route('admin.courses.questions.store', $course))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

test('üretim isteği kuyruğa alınır ve ders sırada olarak işaretlenir', function () {
    Queue::fake();
    $course = Course::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.courses.index'))
        ->post(route('admin.courses.questions.store', $course))
        ->assertRedirect(route('admin.courses.index'))
        ->assertSessionHas('success');

    Queue::assertPushed(GenerateCourseQuestions::class, fn (GenerateCourseQuestions $job): bool => $job->course->is($course));

    expect($course->refresh()->question_generation_status)->toBe(QuestionGenerationStatus::Queued)
        ->and($course->isGeneratingQuestions())->toBeTrue();
});

test('devam eden üretim varken yeni iş kuyruğa alınmaz', function () {
    Queue::fake();
    $course = Course::factory()->create();
    $course->markQuestionGeneration(QuestionGenerationStatus::Running);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.courses.questions.store', $course))
        ->assertSessionHas('error');

    Queue::assertNothingPushed();
});

test('takılı kalmış eski üretim durumu yeni üretimi engellemez', function () {
    Queue::fake();
    $course = Course::factory()->create();
    $course->markQuestionGeneration(QuestionGenerationStatus::Running);

    $this->travel(config('exam.generation_stale_after_minutes') + 1)->minutes();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.courses.questions.store', $course))
        ->assertSessionHas('success');

    Queue::assertPushed(GenerateCourseQuestions::class);
});

test('mevcut soruları olan ders yeniden üretime alınabilir', function () {
    Queue::fake();
    $course = Course::factory()->create();
    Question::factory()->count(3)->withOptions()->for($course)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.courses.questions.store', $course))
        ->assertSessionHas('success');

    Queue::assertPushed(GenerateCourseQuestions::class);
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

test('yönetici ders listesinde üretim durumunu görür', function () {
    $course = Course::factory()->create();
    $course->markQuestionGeneration(QuestionGenerationStatus::Running);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.courses.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/courses/index')
            ->where('courses.0.isGenerating', true));
});
