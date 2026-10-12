<?php

use App\Enums\AiProvider;
use App\Services\Ai\ProviderModelCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Cache::flush();
});

test('OpenRouter model listesi anahtarsız alınır ve sıralanır', function () {
    Http::fake([
        'openrouter.ai/*' => Http::response(['data' => [['id' => 'z/model'], ['id' => 'a/model'], ['id' => 'a/model']]]),
    ]);

    expect(app(ProviderModelCatalog::class)->list(AiProvider::OpenRouter, null))
        ->toBe(['a/model', 'z/model']);
});

test('OpenAI ve Anthropic model listesi için anahtar ister', function () {
    Http::fake();

    expect(fn () => app(ProviderModelCatalog::class)->list(AiProvider::OpenAi, null))
        ->toThrow(RuntimeException::class, 'API anahtarı');

    expect(fn () => app(ProviderModelCatalog::class)->list(AiProvider::Anthropic, '   '))
        ->toThrow(RuntimeException::class, 'API anahtarı');

    Http::assertNothingSent();
});

test('Anthropic isteği doğru başlıklarla yapılır', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['data' => [['id' => 'claude-sonnet-5'], ['id' => 'claude-haiku-5']]]),
    ]);

    expect(app(ProviderModelCatalog::class)->list(AiProvider::Anthropic, 'gizli-anahtar'))
        ->toBe(['claude-haiku-5', 'claude-sonnet-5']);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-api-key', 'gizli-anahtar')
        && $request->hasHeader('anthropic-version', '2023-06-01'));
});

test('OpenAI isteği bearer anahtarıyla yapılır', function () {
    Http::fake([
        'api.openai.com/*' => Http::response(['object' => 'list', 'data' => [['id' => 'gpt-5-mini']]]),
    ]);

    expect(app(ProviderModelCatalog::class)->list(AiProvider::OpenAi, 'sk-test'))
        ->toBe(['gpt-5-mini']);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer sk-test'));
});

test('boş model listesi hata sayılır', function () {
    Http::fake(['openrouter.ai/*' => Http::response(['data' => []])]);

    expect(fn () => app(ProviderModelCatalog::class)->list(AiProvider::OpenRouter, null))
        ->toThrow(RuntimeException::class, 'hiç model');
});
