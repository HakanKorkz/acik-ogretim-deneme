<?php

use App\Enums\CourseLevel;
use App\Enums\ExamStatus;
use App\Jobs\GenerateCourseQuestions;
use App\Models\Course;
use App\Models\ExamAnswer;
use App\Models\ExamSession;
use App\Models\Question;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config()->set('exam.questions_per_exam', 10);
    config()->set('exam.section_minutes', 15);
    Queue::fake();

    $this->visitorId = actAsVisitor($this);
});

function courseWithQuestions(int $count = 12, array $attributes = []): Course
{
    $course = Course::factory()->create($attributes);
    Question::factory()->count($count)->withOptions()->for($course)->create();

    return $course;
}

test('giriş sayfası kademeleri ve hazır ders sayılarını gösterir', function () {
    courseWithQuestions();
    courseWithQuestions(attributes: ['level' => CourseLevel::Ortaokul]);
    Course::factory()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('home')
            ->where('levels.0.value', 'lise')
            ->where('levels.0.courses', 1)
            ->where('levels.1.courses', 1));
});

test('ders seçimi sayfası kademenin aktif derslerini soru sayılarıyla listeler', function () {
    $course = courseWithQuestions(4);
    Question::factory()->count(2)->withOptions()->pastExam()->for($course)->create();
    Course::factory()->inactive()->create();
    courseWithQuestions(attributes: ['level' => CourseLevel::Ortaokul]);

    $this->get(route('courses.index', 'lise'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('courses/index')
            ->has('courses', 1)
            ->where('courses.0.generatedCount', 4)
            ->where('courses.0.pastCount', 2)
            ->where('maxCourses', 10));

    $this->get('/sinav/universite')->assertNotFound();
});

test('en az bir, en fazla on ders seçilebilir', function () {
    $courses = collect(range(1, 11))->map(fn () => courseWithQuestions(1));

    $payload = ['level' => 'lise', 'pool' => 'mixed', 'result_mode' => 'combined'];

    $this->post(route('exams.store'), [...$payload, 'courses' => []])->assertSessionHasErrors('courses');
    $this->post(route('exams.store'), [...$payload, 'courses' => $courses->pluck('slug')->all()])->assertSessionHasErrors('courses');
    $this->post(route('exams.store'), [...$payload, 'courses' => $courses->take(10)->pluck('slug')->all()])->assertSessionHasNoErrors();

    expect(ExamSession::query()->sole()->sections()->count())->toBe(10);
});

test('pasif ya da başka kademedeki ders seçilemez', function () {
    $inactive = Course::factory()->inactive()->create();
    $ortaokul = courseWithQuestions(attributes: ['level' => CourseLevel::Ortaokul]);

    $this->post(route('exams.store'), [
        'level' => 'lise',
        'courses' => [$inactive->slug, $ortaokul->slug],
        'pool' => 'mixed',
        'result_mode' => 'combined',
    ])->assertSessionHasErrors(['courses.0', 'courses.1']);
});

test('her ders kendi bölümündedir ve dersler birbirine karışmaz', function () {
    $math = courseWithQuestions(12, ['name' => 'Matematik-1']);
    $history = courseWithQuestions(12, ['name' => 'Tarih-1']);

    $exam = startExam($this, [$math, $history]);
    $sections = $exam->sections()->with('answers.question')->get();

    expect($sections->pluck('course_id')->all())->toBe([$math->id, $history->id])
        ->and($exam->total_questions)->toBe(20);

    foreach ($sections as $section) {
        expect($section->answers)->toHaveCount(10)
            ->and($section->answers->pluck('number')->all())->toBe(range(1, 10))
            ->and($section->answers->every(fn (ExamAnswer $answer): bool => $answer->question->course_id === $section->course_id))->toBeTrue();
    }
});

test('şık sırası her sınavda karıştırılır ama aynı şıkları içerir', function () {
    $exam = startExam($this, [courseWithQuestions()]);

    foreach ($exam->answers()->with('question.options')->get() as $answer) {
        expect($answer->option_order)->toEqualCanonicalizing($answer->question->options->pluck('id')->all());
    }
});

test('sınav önce talimat ekranıyla açılır, Başlayınız ile süre işler', function () {
    $exam = startExam($this, [courseWithQuestions()]);

    $this->get(route('exams.show', $exam))
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/instructions')
            ->where('isFirstSection', true)
            ->where('section.totalQuestions', 10));

    $this->post(route('exams.start', $exam))->assertRedirect(route('exams.show', $exam));

    $section = $exam->sections()->sole();

    expect($section->started_at)->not->toBeNull()
        ->and((int) round($section->started_at->diffInMinutes($section->expires_at)))->toBe(15);

    $this->get(route('exams.show', $exam))
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/show')
            ->has('questions', 10)
            ->where('section.remainingSeconds', fn (int $seconds): bool => $seconds > 890 && $seconds <= 900)
            ->missing('questions.0.options.0.is_correct')
            ->missing('questions.0.options.0.isCorrect'));
});

test('cevaplar anında kaydedilir, başka sorunun şıkkı reddedilir', function () {
    $exam = startExam($this, [courseWithQuestions()]);
    $this->post(route('exams.start', $exam));

    [$first, $second] = $exam->answers()->with('question.options')->get()->take(2)->all();
    $optionId = $first->question->options->first()->id;

    $this->putJson(route('exams.answer', $exam), ['question_id' => $first->question_id, 'option_id' => $optionId])
        ->assertOk()
        ->assertJson(['saved' => true]);

    $this->putJson(route('exams.answer', $exam), ['question_id' => $second->question_id, 'option_id' => $optionId])
        ->assertStatus(409);

    expect($first->refresh()->option_id)->toBe($optionId)
        ->and($second->refresh()->option_id)->toBeNull();

    $this->get(route('exams.show', $exam))
        ->assertInertia(fn (Assert $page) => $page->where('questions.0.selectedOptionId', $optionId));
});

test('süresi dolan bölüme cevap kaydedilmez ve bölüm kendiliğinden teslim edilir', function () {
    $exam = startExam($this, [courseWithQuestions()]);
    $this->post(route('exams.start', $exam));
    $answer = $exam->answers()->with('question.options')->first();

    $this->travel(16)->minutes();

    $this->putJson(route('exams.answer', $exam), [
        'question_id' => $answer->question_id,
        'option_id' => $answer->question->options->first()->id,
    ])->assertStatus(409);

    $this->get(route('exams.show', $exam))->assertRedirect(route('exams.result', $exam));

    expect($exam->refresh()->status)->toBe(ExamStatus::Completed)
        ->and($exam->correct_count)->toBe(0);
});

test('her ders bitince sonucu gösterme seçeneğinde ders sonucu ekranına gidilir', function () {
    $math = courseWithQuestions(10, ['name' => 'Matematik-1']);
    $history = courseWithQuestions(10, ['name' => 'Tarih-1']);
    $exam = startExam($this, [$math, $history], resultMode: 'per_course');

    $this->post(route('exams.start', $exam));
    $first = $exam->sections()->where('position', 1)->sole();

    $this->post(route('exams.finish', $exam), ['answers' => answersFor($first, 6)])
        ->assertRedirect(route('exams.sections.result', [$exam, 1]));

    $this->get(route('exams.sections.result', [$exam, 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/section-result')
            ->where('result.course', 'Matematik-1')
            ->where('result.correct', 6)
            ->where('result.wrong', 4)
            ->where('result.score', 60)
            ->where('result.passed', true)
            ->where('hasNextSection', true));

    $this->get(route('exams.show', $exam))
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/instructions')
            ->where('section.course', 'Tarih-1')
            ->where('isFirstSection', false));
});

test('toplu sonuç seçeneğinde dersler arka arkaya çözülür ve sonda sonuç belgesi gösterilir', function () {
    $math = courseWithQuestions(10, ['name' => 'Matematik-1']);
    $history = courseWithQuestions(10, ['name' => 'Tarih-1']);
    $exam = startExam($this, [$math, $history]);

    $this->post(route('exams.start', $exam));
    $this->post(route('exams.finish', $exam), ['answers' => answersFor($exam->sections()->where('position', 1)->sole(), 7)])
        ->assertRedirect(route('exams.show', $exam));

    $this->post(route('exams.start', $exam));
    $this->post(route('exams.finish', $exam), ['answers' => answersFor($exam->sections()->where('position', 2)->sole(), 4)])
        ->assertRedirect(route('exams.result', $exam));

    $exam->refresh();

    expect($exam->status)->toBe(ExamStatus::Completed)
        ->and($exam->correct_count)->toBe(11);

    $this->get(route('exams.result', $exam))
        ->assertInertia(fn (Assert $page) => $page
            ->component('exams/result')
            ->has('results', 2)
            ->where('results.0.score', 70)
            ->where('results.0.passed', true)
            ->where('results.1.score', 40)
            ->where('results.1.passed', false)
            ->where('totals.passed', 1)
            ->where('totals.correct', 11)
            ->has('results.0.review', 10));
});

test('aynı ziyaretçi yeni sınavda daha önce görmediği soruları alır', function () {
    $course = courseWithQuestions(20);

    $first = startExam($this, [$course])->answers()->pluck('question_id');
    $second = startExam($this, [$course])->answers()->pluck('question_id');

    expect($first->intersect($second))->toBeEmpty()
        ->and($first->merge($second)->unique())->toHaveCount(20);
});

test('görülmemiş soru azalınca derse arka planda yeni soru eklenir', function () {
    config()->set('exam.bank_topup_unseen_threshold', 20);
    $course = courseWithQuestions(12);

    startExam($this, [$course]);

    Queue::assertPushed(GenerateCourseQuestions::class, fn (GenerateCourseQuestions $job): bool => $job->course->is($course) && $job->append);
});

test('banka üst sınıra ulaştıysa yeni soru üretilmez', function () {
    config()->set('exam.bank_max_per_course', 12);

    startExam($this, [courseWithQuestions(12)]);

    Queue::assertNotPushed(GenerateCourseQuestions::class);
});

test('yalnızca çıkmış sorular seçilince üretilmiş sorular gelmez', function () {
    $course = courseWithQuestions(10);
    Question::factory()->count(3)->withOptions()->pastExam()->for($course)->create();

    $exam = startExam($this, [$course], pool: 'past_exam');

    expect($exam->answers()->count())->toBe(3)
        ->and($exam->answers()->with('question')->get()->every(fn (ExamAnswer $answer): bool => $answer->question->source->value === 'past_exam'))->toBeTrue();
});

test('seçilen türde hiç soru yoksa sınav kurulmaz', function () {
    $course = courseWithQuestions(10);

    $this->from(route('courses.index', 'lise'))
        ->post(route('exams.store'), ['level' => 'lise', 'courses' => [$course->slug], 'pool' => 'past_exam', 'result_mode' => 'combined'])
        ->assertRedirect(route('courses.index', 'lise'))
        ->assertSessionHas('error');

    expect(ExamSession::query()->count())->toBe(0);
});

test('başka bir tarayıcı devam eden sınavı göremez ve çözemez', function () {
    $exam = startExam($this, [courseWithQuestions()]);

    actAsVisitor($this);

    $this->get(route('exams.show', $exam))->assertNotFound();
    $this->post(route('exams.start', $exam))->assertNotFound();
    $this->post(route('exams.finish', $exam))->assertNotFound();

    expect($exam->sections()->sole()->started_at)->toBeNull();
});

test('sınav adresi tahmin edilemeyen ULID kullanır ve sonuç bağlantıyla paylaşılabilir', function () {
    $exam = startExam($this, [courseWithQuestions()]);

    expect(route('exams.show', $exam))->toEndWith('/exams/'.$exam->ulid);
    $this->get('/exams/'.$exam->id)->assertNotFound();

    $this->post(route('exams.start', $exam));
    $this->post(route('exams.finish', $exam), ['answers' => []]);

    actAsVisitor($this);

    $this->get(route('exams.result', $exam))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('exam.id', $exam->ulid));
});
