<?php

use App\Enums\CourseSource;
use App\Enums\TextStatus;
use App\Jobs\ExtractCourseText;
use App\Models\Course;
use App\Models\User;
use App\Services\Books\CourseTextExtractor;
use App\Services\Pdf\PdfTextExtractor;
use App\Settings\MediaSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $this->admin = User::factory()->admin()->create();
});

function configureCloudinary(int $maxMegabytes = 10): void
{
    MediaSettings::fake([
        'default_target' => 'local',
        'cloudinary_cloud_name' => 'demo',
        'cloudinary_api_key' => '123456',
        'cloudinary_api_secret' => 'gizli-sir',
        'cloudinary_folder' => 'acik-ogretim',
        'cloudinary_max_megabytes' => $maxMegabytes,
    ]);
}

/**
 * @return array<string, mixed>
 */
function newCoursePayload(array $overrides = []): array
{
    return [
        'level' => 'lise',
        'subject' => 'TARİH',
        'name' => 'Tarih Ek Kitap',
        'page_from' => 3,
        'page_to' => 90,
        'book' => UploadedFile::fake()->create('kitap.pdf', 500, 'application/pdf'),
        'target' => 'local',
        ...$overrides,
    ];
}

test('PDF ile eklenen ders sunucuya kaydedilir ve metni kuyruğa alınır', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.courses.store'), newCoursePayload())
        ->assertSessionHas('success');

    $course = Course::query()->sole();

    expect($course->source)->toBe(CourseSource::Upload)
        ->and($course->is_active)->toBeFalse()
        ->and($course->slug)->toBe('tarih-ek-kitap')
        ->and($course->page_from)->toBe(3)
        ->and($course->upload_disk)->toBe('local')
        ->and($course->text_status)->toBe(TextStatus::Queued)
        ->and($course->pdfAbsolutePath())->toBe(Storage::disk('local')->path($course->file_path));

    Storage::disk('local')->assertExists($course->file_path);
    Queue::assertPushed(ExtractCourseText::class);
});

test('yalnızca PDF yüklenebilir', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.courses.store'), newCoursePayload(['book' => UploadedFile::fake()->create('kitap.docx', 10)]))
        ->assertSessionHasErrors('book');

    expect(Course::query()->count())->toBe(0);
});

test('Cloudinary yapılandırılmamışsa dosya sunucuya kaydedilir ve neden söylenir', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.courses.store'), newCoursePayload(['target' => 'cloudinary']))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Cloudinary yapılandırılmamış'));

    expect(Course::query()->sole()->upload_disk)->toBe('local');
});

test('Cloudinary sınırını aşan dosya sunucuya kaydedilir', function () {
    configureCloudinary(maxMegabytes: 1);
    Http::fake();

    $this->actingAs($this->admin)
        ->post(route('admin.courses.store'), newCoursePayload([
            'target' => 'cloudinary',
            'book' => UploadedFile::fake()->create('buyuk.pdf', 2048, 'application/pdf'),
        ]))
        ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'sınırını (1 MB) aşıyor'));

    expect(Course::query()->sole()->upload_disk)->toBe('local');
    Http::assertNothingSent();
});

test('Cloudinary\'ye imzalı istekle raw olarak yüklenir', function () {
    configureCloudinary();
    $this->freezeTime();

    Http::fake(['api.cloudinary.com/*' => Http::response([
        'secure_url' => 'https://res.cloudinary.com/demo/raw/upload/acik-ogretim/tarih.pdf',
        'public_id' => 'acik-ogretim/tarih.pdf',
    ])]);

    $this->actingAs($this->admin)
        ->post(route('admin.courses.store'), newCoursePayload(['target' => 'cloudinary']))
        ->assertSessionHas('success');

    $course = Course::query()->sole();

    expect($course->upload_disk)->toBe('cloudinary')
        ->and($course->upload_public_id)->toBe('acik-ogretim/tarih.pdf')
        ->and($course->source_url)->toBe('https://res.cloudinary.com/demo/raw/upload/acik-ogretim/tarih.pdf')
        ->and($course->file_path)->toBeNull()
        ->and($course->pdfAbsolutePath())->toBeNull();

    Http::assertSent(function (Request $request): bool {
        $fields = collect($request->data())->mapWithKeys(fn (array $part): array => [$part['name'] => $part['contents']]);
        $signed = "folder={$fields['folder']}&public_id={$fields['public_id']}&timestamp={$fields['timestamp']}";

        return $request->url() === 'https://api.cloudinary.com/v1_1/demo/raw/upload'
            && $fields['api_key'] === '123456'
            && $fields['signature'] === sha1($signed.'gizli-sir')
            && $fields['timestamp'] === (string) time();
    });
});

test('kitap değiştirilince eski dosya silinir', function () {
    configureCloudinary();
    Http::fake(['api.cloudinary.com/*' => Http::response(['result' => 'ok'])]);

    $local = Course::factory()->create(['upload_disk' => 'local', 'file_path' => 'books/eski.pdf']);
    Storage::disk('local')->put('books/eski.pdf', '%PDF');

    $this->actingAs($this->admin)
        ->post(route('admin.courses.book.update', $local), ['book' => UploadedFile::fake()->create('yeni.pdf', 100, 'application/pdf'), 'target' => 'local'])
        ->assertSessionHas('success');

    Storage::disk('local')->assertMissing('books/eski.pdf');
    Storage::disk('local')->assertExists($local->refresh()->file_path);

    $cloud = Course::factory()->create(['upload_disk' => 'cloudinary', 'upload_public_id' => 'acik-ogretim/eski.pdf', 'file_path' => null]);

    $this->actingAs($this->admin)
        ->post(route('admin.courses.book.update', $cloud), ['book' => UploadedFile::fake()->create('yeni.pdf', 100, 'application/pdf'), 'target' => 'local'])
        ->assertSessionHas('success');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.cloudinary.com/v1_1/demo/raw/destroy'
        && $request['public_id'] === 'acik-ogretim/eski.pdf');
});

test('MEB kataloğundaki dersin kitabı değiştirilemez', function () {
    $course = Course::factory()->aokul()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.courses.book.update', $course), ['book' => UploadedFile::fake()->create('yeni.pdf', 100, 'application/pdf')])
        ->assertSessionHas('error');

    expect($course->refresh()->source)->toBe(CourseSource::Aokul);
});

test('Cloudinary\'deki kitap metin çıkarmak için indirilir', function () {
    Http::fake(['res.cloudinary.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/pdf/uc-sayfa.pdf')))]);

    $course = Course::factory()->create([
        'upload_disk' => 'cloudinary',
        'file_path' => null,
        'source_url' => 'https://res.cloudinary.com/demo/raw/upload/kitap.pdf',
    ]);

    expect(app(CourseTextExtractor::class)->extract($course))->toBe(3);
});

test('sunucudaki yüklenmiş kitabın metni diskten çıkarılır', function () {
    Http::fake();
    $this->mock(PdfTextExtractor::class)->shouldReceive('extractPages')->once()->andReturn([1 => 'Birinci sayfa.']);

    $course = Course::factory()->create(['upload_disk' => 'local', 'file_path' => 'books/kitap.pdf']);
    Storage::disk('local')->put('books/kitap.pdf', '%PDF');

    expect(app(CourseTextExtractor::class)->extract($course))->toBe(1);
    Http::assertNothingSent();
});

test('depolama ayarları kaydedilir, sır tarayıcıya gönderilmez ve şifreli durur', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.media.update'), [
            'default_target' => 'cloudinary',
            'cloudinary_cloud_name' => 'demo',
            'cloudinary_api_key' => '123456',
            'cloudinary_api_secret' => 'gizli-sir',
            'cloudinary_folder' => 'acik-ogretim',
            'cloudinary_max_megabytes' => 10,
        ])
        ->assertSessionHas('success');

    $settings = app(MediaSettings::class)->refresh();

    expect($settings->cloudinaryConfigured())->toBeTrue()
        ->and($settings->default_target)->toBe('cloudinary')
        ->and(DB::table('settings')->where('group', 'media')->where('name', 'cloudinary_api_secret')->value('payload'))->not->toContain('gizli-sir');

    $this->actingAs($this->admin)
        ->get(route('admin.settings.media.edit'))
        ->assertDontSee('gizli-sir')
        ->assertInertia(fn ($page) => $page->where('settings.credentialsSet', true));

    $this->actingAs($this->admin)
        ->put(route('admin.settings.media.update'), [
            'default_target' => 'local',
            'cloudinary_cloud_name' => 'demo',
            'cloudinary_max_megabytes' => 10,
            'clear_credentials' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect(app(MediaSettings::class)->refresh()->cloudinaryConfigured())->toBeFalse();
});
