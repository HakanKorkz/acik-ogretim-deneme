<?php

use App\Ai\Agents\PastQuestionExtractor;
use App\Enums\ImportStatus;
use App\Enums\QuestionSource;
use App\Jobs\ImportPastExamSection;
use App\Jobs\ParsePastExam;
use App\Models\Course;
use App\Models\PastExam;
use App\Models\PastExamSection;
use App\Models\Question;
use App\Models\User;
use App\Services\PastExams\ExamCourseMatcher;
use App\Services\PastExams\PastExamImporter;
use App\Services\PastExams\PastExamListing;
use App\Services\Pdf\PdfTextExtractor;
use App\Services\Questions\QuestionBankImporter;
use App\Services\Questions\QuestionGenerator;
use App\Settings\AiSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function configureAi(): void
{
    AiSettings::fake([
        'provider' => 'openrouter',
        'openrouter_key' => 'test-key',
        'openai_key' => null,
        'anthropic_key' => null,
        'models' => ['test/model'],
        'questions_per_request' => 10,
    ]);
}

/**
 * @return array<string, mixed>
 */
function extractedQuestion(int $number, array $overrides = []): array
{
    return [
        'number' => $number,
        'passage' => '',
        'text' => "Kitapçıktaki {$number}. soru aşağıdakilerden hangisidir?",
        'options' => ['A) Birinci', 'B) İkinci', 'C) Üçüncü', 'D) Dördüncü'],
        'needs_figure' => false,
        ...$overrides,
    ];
}

test('MEB listesi kitapçıklara ayrıştırılır ve eşitlenir', function () {
    Http::fake(['aol.meb.gov.tr/*' => Http::response(file_get_contents(base_path('tests/Fixtures/past-exam/listing.html')))]);

    $listing = app(PastExamListing::class);

    expect($listing->sync())->toBe(['created' => 71, 'total' => 71])
        ->and($listing->sync()['created'])->toBe(0);

    $latest = PastExam::query()->orderBy('sort_order')->first();

    expect($latest->title)->toBe('14-15 Mart 2026 – 1. oturum')
        ->and($latest->session_number)->toBe(1)
        ->and($latest->source_url)->toStartWith('https://aol.meb.gov.tr/meb_iys_dosyalar/2026_04/');

    expect(PastExam::query()->where('title', 'like', '2018-2019 Eğitim Öğretim Yılı III.Dönem – 1. oturum')->exists())->toBeTrue();
});

dataset('kitapçık ve katalog adları', [
    ['DİN KÜL. VE AHL. BİL. – 1', 'Din Kültürü ve Ahlak Bilgisi-1'],
    ['TARİH – 2', 'Tarih-2'],
    ['SEÇMELİ TARİH – 2', 'Seçmeli Tarih 2'],
    ['SEÇMELİ BİYOLOJİ – 2', 'Biyoloji-6 (Seçmeli Biyoloji 2)'],
    ['İNGİLİZCE – 5', 'Yabancı Dil-5-İngilizce'],
    ['SAĞLIK BİLGİSİ VE TRAFİK KÜLTÜRÜ – 2', 'Sağlık Bilgisi-2'],
    ['SEÇ. TÜRK DİLİ VE EDEBİYATI – 3', 'Seçmeli Türk Dili ve Edebiyatı 3'],
    ['TEMEL DİNİ BİL. – 1', 'Temel Dini Bilgiler-1'],
    ['KELAM – 1', 'Kelâm-1'],
    ['SEÇMELİ MANTIK – 2', 'Mantık (Seçmeli Mantık 2)'],
]);

test('kitapçıktaki ders adı katalogdaki derse eşlenir', function (string $examName, string $catalogName) {
    foreach (['Din Kültürü ve Ahlak Bilgisi-1', 'Tarih-2', 'Seçmeli Tarih 2', 'Biyoloji-2', 'Biyoloji-6 (Seçmeli Biyoloji 2)',
        'Yabancı Dil-5-İngilizce', 'Yabancı Dil-5-Almanca', 'Sağlık Bilgisi-2', 'Türk Dili ve Edebiyatı-3',
        'Seçmeli Türk Dili ve Edebiyatı 3', 'Temel Dini Bilgiler-1', 'Kelâm-1', 'Mantık (Seçmeli Mantık 2)'] as $name) {
        Course::factory()->create(['name' => $name, 'slug' => Str::slug($name)]);
    }

    expect((new ExamCourseMatcher)->match($examName)?->name)->toBe($catalogName);
})->with('kitapçık ve katalog adları');

test('eşleşmeyen ders adı için ders döndürülmez', function () {
    Course::factory()->create(['name' => 'Tarih-2']);

    expect((new ExamCourseMatcher)->match('FİZİK – 1'))->toBeNull();
});

test('kitapçık indirilir, bölümlere ayrılır ve derslerle eşlenir', function () {
    Http::fake(['aol.meb.gov.tr/*' => Http::response('%PDF-sahte')]);

    $this->mock(PdfTextExtractor::class, function ($mock): void {
        $mock->shouldReceive('extractPages')->andReturn(json_decode(file_get_contents(base_path('tests/Fixtures/past-exam/mart-2026-1-pages.json')), true));
        $mock->shouldReceive('extractWords')->andReturn(json_decode(file_get_contents(base_path('tests/Fixtures/past-exam/mart-2026-1-answer-words.json')), true));
    });

    $tarih = Course::factory()->create(['name' => 'Tarih-2', 'slug' => 'tarih-2']);
    $pastExam = PastExam::factory()->create();

    expect(app(PastExamImporter::class)->parse($pastExam))->toBe(48);

    $section = $pastExam->sections()->where('code', '132')->sole();

    expect($section->course_id)->toBe($tarih->id)
        ->and($section->answer_key)->toHaveCount(10)
        ->and($pastExam->refresh()->parsed_at)->not->toBeNull()
        ->and($pastExam->sections()->whereNull('course_id')->count())->toBe(47);
});

test('pdftotext yoksa kitapçık işlenmez ve neden bildirilir', function () {
    Http::fake(['aol.meb.gov.tr/*' => Http::response('%PDF-sahte')]);

    $this->mock(PdfTextExtractor::class, function ($mock): void {
        $mock->shouldReceive('extractPages')->andReturn([1 => 'Kapak', 2 => 'CEVAP ANAHTARI']);
        $mock->shouldReceive('extractWords')->andReturn(null);
    });

    app(PastExamImporter::class)->parse(PastExam::factory()->create());
})->throws(RuntimeException::class, 'pdftotext');

test('bölüm aktarımında doğru şık her zaman cevap anahtarından gelir', function () {
    configureAi();

    $section = PastExamSection::factory()->for(Course::factory())->create([
        'answer_key' => ['1' => 'C', '2' => 'A', '3' => 'D'],
    ]);

    PastQuestionExtractor::fake([[
        'questions' => [
            extractedQuestion(1),
            extractedQuestion(2, ['passage' => "I. Birinci öncül\nII. İkinci öncül"]),
            extractedQuestion(3, ['needs_figure' => true]),
            extractedQuestion(7),
        ],
    ]]);

    expect(app(PastExamImporter::class)->importSection($section))->toBe(2);

    $questions = Question::query()->orderBy('past_exam_number')->with('options')->get();

    expect($questions)->toHaveCount(2)
        ->and($questions[0]->source)->toBe(QuestionSource::PastExam)
        ->and($questions[0]->correct_option)->toBe('C')
        ->and($questions[0]->options->firstWhere('is_correct', true)->text)->toBe('Üçüncü')
        ->and($questions[0]->options->pluck('label')->all())->toBe(['A', 'B', 'C', 'D'])
        ->and($questions[1]->passage)->toContain('Birinci öncül')
        ->and($questions[1]->correct_option)->toBe('A');

    $section->refresh();

    expect($section->status)->toBe(ImportStatus::Done)
        ->and($section->imported_count)->toBe(2)
        ->and($section->skipped_count)->toBe(1);
});

test('soru numarası atılır ve bölünmüş cümle köke birleştirilir', function () {
    configureAi();
    $section = PastExamSection::factory()->for(Course::factory())->create(['answer_key' => ['1' => 'B', '2' => 'A']]);

    PastQuestionExtractor::fake([[
        'questions' => [
            extractedQuestion(1, ['passage' => '1. Tehdit altında inançsız olduğunu söyleyen kimsenin mümin sayılması', 'text' => 'aşağıdaki kavramlardan hangisiyle ifade edilir?']),
            extractedQuestion(2, ['passage' => '2. “Ey insanlar!” (Yunus suresi, 57. ayet)', 'text' => 'Bu ayet hangisiyle ilgilidir?']),
        ],
    ]]);

    app(PastExamImporter::class)->importSection($section);

    [$first, $second] = Question::query()->orderBy('past_exam_number')->get()->all();

    expect($first->passage)->toBeNull()
        ->and($first->text)->toBe('Tehdit altında inançsız olduğunu söyleyen kimsenin mümin sayılması aşağıdaki kavramlardan hangisiyle ifade edilir?')
        ->and($second->passage)->toBe('“Ey insanlar!” (Yunus suresi, 57. ayet)')
        ->and($second->text)->toBe('Bu ayet hangisiyle ilgilidir?');
});

test('bölüm yeniden aktarılınca aynı sorular tekrar eklenmez', function () {
    configureAi();
    $section = PastExamSection::factory()->for(Course::factory())->create(['answer_key' => ['1' => 'B']]);

    PastQuestionExtractor::fake([
        ['questions' => [extractedQuestion(1)]],
        ['questions' => [extractedQuestion(1)]],
    ]);

    app(PastExamImporter::class)->importSection($section);
    app(PastExamImporter::class)->importSection($section);

    expect(Question::query()->count())->toBe(1);
});

test('ders eşleşmesi ya da yapay zekâ yoksa bölüm aktarılmaz', function () {
    $unmatched = PastExamSection::factory()->create(['course_id' => null]);

    expect(fn () => app(PastExamImporter::class)->importSection($unmatched))->toThrow(RuntimeException::class, 'katalogda');

    config()->set('exam.ai.keys.openrouter', null);
    AiSettings::fake(['provider' => 'openrouter', 'openrouter_key' => null, 'openai_key' => null, 'anthropic_key' => null, 'models' => ['a'], 'questions_per_request' => 10]);
    $matched = PastExamSection::factory()->for(Course::factory())->create();

    expect(fn () => app(PastExamImporter::class)->importSection($matched))->toThrow(RuntimeException::class, 'Yapay zekâ ayarlanmamış');
});

test('kitapçık işi yalnızca yayındaki derslerin bölümlerini kuyruğa alır', function () {
    Queue::fake();

    $pastExam = PastExam::factory()->create();
    $active = PastExamSection::factory()->for($pastExam)->for(Course::factory())->create(['code' => '132']);
    $inactive = PastExamSection::factory()->for($pastExam)->for(Course::factory()->inactive())->create(['code' => '136']);
    $unmatched = PastExamSection::factory()->for($pastExam)->create(['code' => '999', 'course_id' => null]);

    $this->mock(PastExamImporter::class)->shouldReceive('parse')->once()->andReturn(3);

    app()->call([new ParsePastExam($pastExam), 'handle']);

    Queue::assertPushed(ImportPastExamSection::class, 1);
    Queue::assertPushed(ImportPastExamSection::class, fn (ImportPastExamSection $job): bool => $job->section->is($active));

    expect($inactive->refresh()->status)->toBe(ImportStatus::Skipped)
        ->and($unmatched->refresh()->error)->toContain('eşleşen ders yok')
        ->and($pastExam->refresh()->status)->toBe(ImportStatus::Done);
});

test('yönetici kitapçıkları görür, listeyi eşitler, içe aktarır ve bölümü yeniden dener', function () {
    Queue::fake();
    Http::fake(['aol.meb.gov.tr/*' => Http::response(file_get_contents(base_path('tests/Fixtures/past-exam/listing.html')))]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.past-exams.sync'))->assertSessionHas('success');

    $pastExam = PastExam::query()->orderBy('sort_order')->first();
    $section = PastExamSection::factory()->for($pastExam)->for(Course::factory())->create(['status' => ImportStatus::Failed]);

    $this->actingAs($admin)
        ->get(route('admin.past-exams.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/past-exams/index')
            ->has('pastExams', 71)
            ->where('pastExams.0.sections.0.canRetry', true));

    $this->actingAs($admin)->post(route('admin.past-exams.import', $pastExam))->assertSessionHas('success');
    Queue::assertPushed(ParsePastExam::class);

    $this->actingAs($admin)->post(route('admin.past-exam-sections.retry', $section))->assertSessionHas('success');
    Queue::assertPushed(ImportPastExamSection::class);

    $this->actingAs(User::factory()->create())->get(route('admin.past-exams.index'))->assertForbidden();
});

test('soru üretiminde dersin çıkmış soruları üslup örneği olarak verilir', function () {
    $received = new ArrayObject;

    app()->instance(QuestionGenerator::class, new class($received) implements QuestionGenerator
    {
        public function __construct(private ArrayObject $received) {}

        public function generate(string $sourceText, int $count = 10, array $styleExamples = []): array
        {
            $this->received['examples'] = $styleExamples;

            return [];
        }
    });

    $course = Course::factory()->withText()->create();
    Question::factory()->count(2)->withOptions()->pastExam()->for($course)->create(['text' => 'Gerçek çıkmış soru kökü hangisidir?']);
    Question::factory()->count(3)->withOptions()->for($course)->create();

    app(QuestionBankImporter::class)->import($course, 5);

    expect($received['examples'])->toHaveCount(2)
        ->and($received['examples'][0])->toContain('Gerçek çıkmış soru kökü')->toContain('A) ');
});
