<?php

use App\Ai\Agents\QuestionWriter;
use App\Models\User;
use App\Settings\AiSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

test('ayar sayfası anahtarları tarayıcıya göndermez', function () {
    $settings = app(AiSettings::class);
    $settings->openrouter_key = 'gizli-panel-anahtari';
    $settings->save();

    $this->actingAs($this->admin)
        ->get(route('admin.settings.ai.edit'))
        ->assertOk()
        ->assertDontSee('gizli-panel-anahtari')
        ->assertInertia(fn ($page) => $page
            ->component('admin/settings/ai')
            ->where('providers.0.value', 'openrouter')
            ->where('providers.0.keySource', 'panel'));
});

test('yönetici olmayan kullanıcı ayarlara erişemez', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.settings.ai.edit'))
        ->assertForbidden();
});

test('ayarlar kaydedilir ve anahtar veritabanında şifreli durur', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.ai.update'), [
            'provider' => 'anthropic',
            'anthropic_key' => 'sk-ant-yeni',
            'models' => "claude-haiku-5-5\nclaude-sonnet-5-5",
            'questions_per_request' => 8,
        ])
        ->assertRedirect(route('admin.settings.ai.edit'))
        ->assertSessionHas('success');

    $settings = app(AiSettings::class)->refresh();

    expect($settings->provider)->toBe('anthropic')
        ->and($settings->anthropic_key)->toBe('sk-ant-yeni')
        ->and($settings->models)->toBe(['claude-haiku-5-5', 'claude-sonnet-5-5'])
        ->and($settings->questions_per_request)->toBe(8);

    $stored = DB::table('settings')->where('group', 'ai')->where('name', 'anthropic_key')->value('payload');

    expect($stored)->not->toContain('sk-ant-yeni');
});

test('boş anahtar alanı mevcut anahtarı korur, silme işareti kaldırır', function () {
    $settings = app(AiSettings::class);
    $settings->openrouter_key = 'eski-anahtar';
    $settings->openai_key = 'openai-anahtari';
    $settings->save();

    $this->actingAs($this->admin)
        ->put(route('admin.settings.ai.update'), [
            'provider' => 'openrouter',
            'openrouter_key' => '',
            'clear_keys' => ['openai'],
            'models' => 'openrouter/free',
            'questions_per_request' => 10,
        ])
        ->assertSessionHasNoErrors();

    $settings->refresh();

    expect($settings->openrouter_key)->toBe('eski-anahtar')
        ->and($settings->openai_key)->toBeNull();
});

test('geçersiz model adı ve sağlayıcı reddedilir', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.ai.update'), [
            'provider' => 'bilinmeyen',
            'models' => "geçerli/model\nboşluklu model",
            'questions_per_request' => 50,
        ])
        ->assertSessionHasErrors(['provider', 'models.1', 'questions_per_request']);
});

test('bağlantı testi ilk yanıt veren modeli bildirir', function () {
    $settings = app(AiSettings::class);
    $settings->provider = 'openrouter';
    $settings->openrouter_key = 'test-key';
    $settings->models = ['ilk/model'];
    $settings->save();

    QuestionWriter::fake([[
        'questions' => [['text' => 'Soru?', 'options' => ['A', 'B', 'C', 'D'], 'correct_index' => 0]],
    ]]);

    $this->actingAs($this->admin)
        ->from(route('admin.settings.ai.edit'))
        ->post(route('admin.settings.ai.test'))
        ->assertRedirect(route('admin.settings.ai.edit'))
        ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'ilk/model'));
});

test('anahtar yoksa bağlantı testi istek göndermeden hata verir', function () {
    QuestionWriter::fake();
    config()->set('exam.ai.keys.openrouter', null);

    $this->actingAs($this->admin)
        ->post(route('admin.settings.ai.test'))
        ->assertSessionHas('error');

    QuestionWriter::assertNeverPrompted();
});

test('sistem promptu kaydedilir ve özelleştirilmiş olarak kullanılır', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.ai.update'), [
            'provider' => 'openrouter',
            'models' => 'openrouter/free',
            'questions_per_request' => 10,
            'system_prompt' => 'Yalnızca şu yönergeye uy.',
        ])
        ->assertSessionHasNoErrors();

    $settings = app(AiSettings::class)->refresh();

    expect($settings->system_prompt)->toBe('Yalnızca şu yönergeye uy.')
        ->and($settings->systemPrompt())->toBe('Yalnızca şu yönergeye uy.');
});

test('sistem promptu boş bırakılınca ya da varsayılana eşitse null saklanır', function () {
    $this->actingAs($this->admin)
        ->put(route('admin.settings.ai.update'), [
            'provider' => 'openrouter',
            'models' => 'openrouter/free',
            'questions_per_request' => 10,
            'system_prompt' => 'Önce özel.',
        ])
        ->assertSessionHasNoErrors();

    expect(app(AiSettings::class)->refresh()->system_prompt)->toBe('Önce özel.');

    foreach (['', QuestionWriter::defaultInstructions()] as $value) {
        $this->actingAs($this->admin)
            ->put(route('admin.settings.ai.update'), [
                'provider' => 'openrouter',
                'models' => 'openrouter/free',
                'questions_per_request' => 10,
                'system_prompt' => $value,
            ])
            ->assertSessionHasNoErrors();

        expect(app(AiSettings::class)->refresh()->system_prompt)->toBeNull();
    }
});

test('model listesi uç noktası canlı listeyi sıralı döndürür', function () {
    Cache::flush();
    Http::fake(['openrouter.ai/*' => Http::response(['data' => [['id' => 'x/y'], ['id' => 'a/b']]])]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.settings.ai.models'), ['provider' => 'openrouter'])
        ->assertOk()
        ->assertJson(['source' => 'live'])
        ->assertJsonPath('models', ['a/b', 'x/y']);
});

test('model listesi alınamazsa sağlayıcının önerilerine düşer', function () {
    Cache::flush();
    Http::fake(['openrouter.ai/*' => Http::response('hata', 500)]);

    $this->actingAs($this->admin)
        ->postJson(route('admin.settings.ai.models'), ['provider' => 'openrouter'])
        ->assertOk()
        ->assertJson(['source' => 'fallback'])
        ->assertJsonPath('models', ['openrouter/free']);
});

test('yönetici olmayan kullanıcı model listesini çekemez', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('admin.settings.ai.models'), ['provider' => 'openrouter'])
        ->assertForbidden();
});
