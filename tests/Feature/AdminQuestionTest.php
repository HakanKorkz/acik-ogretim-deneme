<?php

use App\Enums\QuestionSource;
use App\Models\Course;
use App\Models\ExamAnswer;
use App\Models\PastExamSection;
use App\Models\Question;
use App\Models\User;
use App\Services\Questions\QuestionEditor;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

/** @return array<string, mixed> */
function questionEditData(Question $question): array
{
    $question->load('options');

    return [
        'version' => app(QuestionEditor::class)->version($question),
        'passage' => $question->passage,
        'text' => $question->text,
        'correct_option' => $question->correct_option,
        'source_page' => $question->source_page,
        'options' => $question->options->pluck('text', 'label')->all(),
    ];
}

test('yönetici soru bankasını onar kayıtla ve cevaplarıyla inceler', function () {
    $course = Course::factory()->inactive()->aokul()->create(['page_offset' => 3]);
    $question = Question::factory()->for($course)->withOptions()->create(['passage' => 'Öncül metni', 'source_page' => 12]);
    Question::factory()->for($course)->withOptions()->count(11)->create();
    Question::factory()->for($course)->create()->delete();
    Question::factory()->withOptions()->create();

    $this->actingAs($this->admin)->get(route('admin.courses.questions.index', $course))
        ->assertOk()->assertInertia(fn ($page) => $page
        ->component('admin/questions/index')->has('questions.data', 10)
        ->where('questions.total', 12)->where('questions.data.0.id', $question->id)
        ->where('questions.data.0.passage', 'Öncül metni')
        ->where('questions.data.0.correctOption', $question->correct_option)
        ->where('questions.data.0.sourcePdfPage', 15)
        ->has('questions.data.0.options', 4));
    $this->get(route('admin.courses.questions.index', [$course, 'page' => 2]))
        ->assertInertia(fn ($page) => $page->has('questions.data', 2)->where('questions.current_page', 2));
    $this->get(route('admin.courses.questions.edit', [$course, $question]))
        ->assertInertia(fn ($page) => $page->component('admin/questions/edit')
            ->where('question.version', app(QuestionEditor::class)->version($question)));
});

test('soru araması öncülleri kapsar ve kaynakla birlikte yalnızca seçilen derste çalışır', function () {
    $course = Course::factory()->create();
    $wanted = Question::factory()->for($course)->create(['passage' => 'Aranan öncül', 'source' => QuestionSource::Local]);
    Question::factory()->for($course)->create(['text' => 'Aranan', 'source' => QuestionSource::Ai]);
    Question::factory()->create(['text' => 'Aranan', 'source' => QuestionSource::Local]);

    $this->actingAs($this->admin)->get(route('admin.courses.questions.index', [$course, 'search' => 'Aranan', 'source' => 'local']))
        ->assertInertia(fn ($page) => $page->has('questions.data', 1)
            ->where('questions.data.0.id', $wanted->id)->where('questions.data.0.source', 'Yerel üretim'));
});

test('kitap metni sorumluluk aralığını kaydırmayla ve sayfalama ile gösterir', function () {
    $course = Course::factory()->withText(12)->create(['page_from' => 2, 'page_to' => 8, 'page_offset' => 2]);

    $this->actingAs($this->admin)->get(route('admin.courses.text.index', $course))
        ->assertOk()->assertInertia(fn ($page) => $page->component('admin/courses/text')
        ->has('pages.data', 5)->where('pages.total', 7)
        ->where('pages.data.0.pdfPage', 4)->where('pages.data.0.printedPage', 2)
        ->where('pages.data.0.content', $course->pages()->where('page_number', 4)->value('content')));
    $this->get(route('admin.courses.text.index', [$course, 'page' => 2]))
        ->assertInertia(fn ($page) => $page->has('pages.data', 2)->where('pages.data.0.pdfPage', 9));
    $this->get(route('admin.courses.text.index', [$course, 'scope' => 'all', 'search' => 'Sayfa 12.']))
        ->assertInertia(fn ($page) => $page->has('pages.data', 1)->where('pages.total', 1)->where('pages.data.0.pdfPage', 12));
});

test('soru düzeltmesi yeni bankada yer alır ve eski sınavın metni şıkları ve sonucu korunur', function () {
    $question = Question::factory()->withOptions()->create(['correct_option' => 'A']);
    $oldOptions = $question->options->toArray();
    $answer = ExamAnswer::factory()->for($question)->create([
        'option_id' => $question->options->first()->id,
        'option_order' => $question->options->pluck('id')->reverse()->values()->all(),
        'is_correct' => true,
    ]);
    $displayed = $answer->displayedOptions();
    $data = [...questionEditData($question), 'text' => 'Düzeltilmiş soru?', 'passage' => 'Yeni öncül', 'correct_option' => 'D', 'source_page' => 32];
    $data['options']['D'] = 'Yeni doğru cevap';
    $data['course_id'] = Course::factory()->create()->id;
    $data['source'] = 'local';

    $response = $this->actingAs($this->admin)->put(route('admin.courses.questions.update', [$question->course, $question]), $data)
        ->assertSessionHasNoErrors()->assertSessionHas('success');
    $replacement = $question->course->questions()->sole();
    $response->assertRedirect(route('admin.courses.questions.edit', [$question->course, $replacement]));
    expect($question->refresh()->trashed())->toBeTrue()
        ->and($question->options()->get()->toArray())->toBe($oldOptions)
        ->and($replacement->text)->toBe('Düzeltilmiş soru?')
        ->and($replacement->passage)->toBe('Yeni öncül')
        ->and($replacement->correct_option)->toBe('D')
        ->and($replacement->source_page)->toBe(32)
        ->and($replacement->source)->toBe(QuestionSource::Ai)
        ->and($replacement->options()->where('is_correct', true)->sole()->label)->toBe('D')
        ->and($answer->fresh()->question->text)->toBe($question->text)
        ->and($answer->fresh()->displayedOptions())->toBe($displayed)
        ->and($answer->fresh()->is_correct)->toBeTrue();

    $this->put(route('admin.courses.questions.update', [$question->course, $question]), $data)
        ->assertSessionHasErrors('version');
    expect($question->course->questions()->count())->toBe(1);
});

test('değiştirilmemiş soru gereksiz yeni sürüm oluşturmaz', function () {
    $question = Question::factory()->withOptions()->create();
    $this->actingAs($this->admin)->put(route('admin.courses.questions.update', [$question->course, $question]), questionEditData($question))
        ->assertSessionHasNoErrors()->assertRedirect(route('admin.courses.questions.edit', [$question->course, $question]));
    expect($question->refresh()->trashed())->toBeFalse()->and(Question::withTrashed()->count())->toBe(1);
});

test('çıkmış sorunun kitapçık kaynağı ve resmi cevap anahtarı düzenlemede korunur', function () {
    $course = Course::factory()->create();
    $section = PastExamSection::factory()->create(['course_id' => $course->id, 'answer_key' => ['1' => 'B']]);
    $question = Question::factory()->for($course)->pastExam()->withOptions()->create([
        'past_exam_section_id' => $section->id, 'past_exam_number' => 1, 'correct_option' => 'A',
    ]);
    $this->actingAs($this->admin)->get(route('admin.courses.questions.edit', [$course, $question]))
        ->assertInertia(fn ($page) => $page->where('question.officialAnswer', 'B')
            ->where('question.pastExamTitle', $section->pastExam->title)
            ->where('question.sourceUrl', $section->pastExam->source_url));
    $this->put(route('admin.courses.questions.update', [$course, $question]), [
        ...questionEditData($question), 'correct_option' => 'B',
    ])->assertSessionHasNoErrors();
    $replacement = $course->questions()->sole();
    expect($replacement->source)->toBe(QuestionSource::PastExam)
        ->and($replacement->past_exam_section_id)->toBe($section->id)
        ->and($replacement->past_exam_number)->toBe(1)
        ->and($replacement->correct_option)->toBe('B')
        ->and($section->refresh()->answer_key)->toBe(['1' => 'B']);
});

test('eski sekme güncel soruyu sessizce ezemez', function () {
    $question = Question::factory()->withOptions()->create();
    $data = questionEditData($question);
    $question->update(['text' => 'Başka yöneticinin düzeltmesi']);

    $this->actingAs($this->admin)->put(route('admin.courses.questions.update', [$question->course, $question]), $data)
        ->assertSessionHasErrors('version');
    expect($question->refresh()->text)->toBe('Başka yöneticinin düzeltmesi')->and(Question::withTrashed()->count())->toBe(1);
});

test('eksik veya tutarsız soru düzeltmeleri kaydedilmez', function (array $changes, string $field) {
    $question = Question::factory()->withOptions()->create();
    $data = array_replace_recursive(questionEditData($question), $changes);
    $this->actingAs($this->admin)->put(route('admin.courses.questions.update', [$question->course, $question]), $data)
        ->assertSessionHasErrors($field);
    expect($question->refresh()->trashed())->toBeFalse()->and(Question::withTrashed()->count())->toBe(1);
})->with([
    [['text' => ''], 'text'],
    [['correct_option' => 'E'], 'correct_option'],
    [['options' => ['A' => '']], 'options.A'],
    [['options' => ['A' => 'Aynı', 'B' => 'Aynı']], 'options.A'],
    [['options' => ['E' => 'Fazla şık']], 'options'],
    [['source_page' => -1], 'source_page'],
    [['version' => 'eski'], 'version'],
]);

test('başka derse ait soru adres değiştirilerek düzenlenemez', function () {
    $question = Question::factory()->withOptions()->create();
    $course = Course::factory()->create();
    $this->actingAs($this->admin)->get(route('admin.courses.questions.edit', [$course, $question]))->assertNotFound();
    $this->put(route('admin.courses.questions.update', [$course, $question]), questionEditData($question))->assertNotFound();
    expect($question->refresh()->trashed())->toBeFalse();
});

test('soru ve metin yönetimi yalnızca yöneticiye açıktır', function () {
    $question = Question::factory()->withOptions()->create();
    $urls = [
        route('admin.courses.questions.index', $question->course),
        route('admin.courses.questions.edit', [$question->course, $question]),
        route('admin.courses.text.index', $question->course),
    ];
    foreach ($urls as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
    $this->put(route('admin.courses.questions.update', [$question->course, $question]), questionEditData($question))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create());
    foreach ($urls as $url) {
        $this->get($url)->assertForbidden();
    }
    $this->put(route('admin.courses.questions.update', [$question->course, $question]), questionEditData($question))->assertForbidden();
});
