<?php

use App\Enums\QuestionSource;
use App\Models\Course;
use App\Models\Question;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/** @return list<list<string>> */
function exportedQuestionRows(TestResponse $response): array
{
    $response->assertOk()->assertDownload();
    $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $path = $response->baseResponse->getFile()->getPathname();
    $zip = new ZipArchive;

    try {
        expect($zip->open($path))->toBeTrue();
        $document = new DOMDocument;
        expect($document->loadXML($zip->getFromName('xl/worksheets/sheet1.xml')))->toBeTrue();
        expect($document->getElementsByTagName('f')->length)->toBe(0);
        $rows = [];

        foreach ($document->getElementsByTagName('row') as $row) {
            $cells = [];

            foreach ($row->getElementsByTagName('c') as $cell) {
                expect($cell->getAttribute('t'))->toBe('inlineStr');
                $cells[] = $cell->textContent;
            }

            $rows[] = $cells;
        }

        return $rows;
    } finally {
        $zip->close();
        unlink($path);
    }
}

test('misafir soru dışa aktarma sayfasına ve dosyasına erişemez', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with(['admin.questions.export.index', 'admin.questions.export.download']);

test('yönetici olmayan kullanıcı soru dışa aktaramaz', function (string $route) {
    $this->actingAs(User::factory()->create())->get(route($route))->assertForbidden();
})->with(['admin.questions.export.index', 'admin.questions.export.download']);

test('yönetici dışa aktarılabilecek soru sayısını görür', function () {
    Question::factory()->withOptions()->create();
    Question::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.questions.export.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/questions/export')
            ->where('stats.total', 2)
            ->where('stats.eligible', 1)
            ->where('stats.excluded', 1));
});

test('excel istenen şemayı türkçe metni öncülleri ve doğru şıkkı korur', function () {
    $course = Course::factory()->create(['name' => 'İşletme']);
    $question = Question::factory()->withOptions()->for($course)->create([
        'passage' => "I. Güçlü yanlar\nII. Zayıf yanlar",
        'text' => "SWOT analizinde 'W' neyi ifade eder? <&>",
        'correct_option' => 'B',
    ]);
    $question->options()->where('label', 'A')->update(['text' => '=1+1']);
    $question->options()->where('label', 'B')->update(['text' => 'Zayıf Yanlar']);

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.questions.export.download'));

    $rows = exportedQuestionRows($response);
    expect($rows)->toHaveCount(2);
    expect($rows[0])->toBe(['ders', 'konu', 'zorluk', 'soru', 'A', 'B', 'C', 'D', 'cevap']);
    expect($rows[1])->toBe([
        'İşletme', '', '', "I. Güçlü yanlar\nII. Zayıf yanlar\n\nSWOT analizinde 'W' neyi ifade eder? <&>",
        '=1+1', 'Zayıf Yanlar', 'Çeldirici seçenek C', 'Çeldirici seçenek D', 'B',
    ]);
});

test('arşivlenmiş ve biçimi tutarsız sorular excel dosyasına girmez', function () {
    $valid = Question::factory()->withOptions()->create(['text' => 'Geçerli soru?']);
    $archived = Question::factory()->withOptions()->create();
    $archived->delete();
    Question::factory()->create();
    $emptyOption = Question::factory()->withOptions()->create();
    $emptyOption->options()->where('label', 'A')->update(['text' => '   ']);
    $multipleAnswers = Question::factory()->withOptions()->create(['correct_option' => 'B']);
    $multipleAnswers->options()->where('label', 'A')->update(['is_correct' => true]);
    $mismatch = Question::factory()->withOptions()->create(['correct_option' => 'A']);
    $mismatch->update(['correct_option' => 'D']);
    Question::factory()->withOptions()->create(['text' => ' ']);

    $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.questions.export.download'));

    $rows = exportedQuestionRows($response);
    expect($rows)->toHaveCount(2);
    expect($rows[1][3])->toBe($valid->text);
});

test('ders kademe kaynak ve yayın filtreleri excel içeriğine uygulanır', function (string $filter) {
    $target = Course::factory()->create(['level' => 'lise', 'is_active' => true]);
    Question::factory()->withOptions()->for($target)->create(['source' => QuestionSource::PastExam, 'text' => 'Seçilen soru?']);
    $other = Course::factory()->inactive()->create(['level' => 'ortaokul']);
    Question::factory()->withOptions()->for($other)->create(['source' => QuestionSource::Local]);
    $filters = ['course' => $target->slug, 'level' => 'lise', 'source' => 'past_exam', 'publication' => 'active'];

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.questions.export.download', [$filter => $filters[$filter]]));

    $rows = exportedQuestionRows($response);
    expect($rows)->toHaveCount(2);
    expect($rows[1][3])->toBe('Seçilen soru?');
})->with(['course', 'level', 'source', 'publication']);

test('kaynak bilgisi seçilirse soru kimliği ve kaynak sayfası eklenir', function () {
    $question = Question::factory()->withOptions()->create(['source_page' => 42]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.questions.export.download', ['metadata' => 1]));

    $rows = exportedQuestionRows($response);
    expect(array_slice($rows[0], 9))->toBe(['soru_id', 'kademe', 'kaynak', 'kaynak_url', 'kaynak_sayfa']);
    expect($rows[1][9])->toBe((string) $question->id);
    expect($rows[1][11])->toBe('ai');
    expect($rows[1][13])->toBe('42');
});

test('eşleşen soru yoksa boş eğitim dosyası yerine açıklama gösterilir', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.questions.export.index'))
        ->get(route('admin.questions.export.download'))
        ->assertRedirect(route('admin.questions.export.index'))
        ->assertSessionHasErrors(['export' => 'Seçiminize uygun, dört şıklı ve tutarlı cevap anahtarı olan soru bulunamadı.']);
});

test('excel hücre sınırını aşan soru kırpılmadan hata döner', function () {
    Question::factory()->withOptions()->create(['text' => str_repeat('a', 32768)]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.questions.export.download'))
        ->assertSessionHasErrors(['export' => '2. satırda Excel’in metin sınırlarını aşan içerik var. Veri kaybını önlemek için dosya oluşturulmadı.']);
});

test('geçersiz dışa aktarma filtresi reddedilir', function (array $filters, string $field, string $message) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.questions.export.download', $filters))
        ->assertSessionHasErrors([$field => $message]);
})->with([
    [['level' => 'invalid'], 'level', 'Geçerli bir kademe seçin.'],
    [['course' => 'yok'], 'course', 'Seçilen ders bulunamadı.'],
    [['source' => 'invalid'], 'source', 'Geçerli bir soru kaynağı seçin.'],
    [['publication' => 'invalid'], 'publication', 'Geçerli bir yayın durumu seçin.'],
    [['metadata' => 'invalid'], 'metadata', 'Kaynak bilgisi seçimi geçerli değil.'],
]);
