<?php

namespace App\Services\Ai;

use App\Enums\AiProvider;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Seçili sağlayıcının kullanılabilir model kimliklerini canlı olarak çeker.
 *
 * - OpenRouter model listesi herkese açıktır.
 * - OpenAI ve Anthropic, geçerli bir API anahtarı ister.
 *
 * Sonuç bir saat önbellekte tutulur. Hata durumunda istisna atar; çağıran taraf
 * önerilen modellere düşer.
 */
class ProviderModelCatalog
{
    private const CACHE_TTL_MINUTES = 60;

    /**
     * @return list<string> Alfabetik sıralı model kimlikleri.
     *
     * @throws RuntimeException Liste alınamazsa.
     */
    public function list(AiProvider $provider, ?string $key): array
    {
        return match ($provider) {
            AiProvider::OpenRouter => $this->openRouter(),
            AiProvider::OpenAi => $this->openAi($this->requireKey($provider, $key)),
            AiProvider::Anthropic => $this->anthropic($this->requireKey($provider, $key)),
        };
    }

    /**
     * @return list<string>
     */
    private function openRouter(): array
    {
        return $this->cached('openrouter', fn (): array => $this->ids(
            Http::timeout(10)->acceptJson()->get('https://openrouter.ai/api/v1/models')->throw()->json('data'),
        ));
    }

    /**
     * @return list<string>
     */
    private function openAi(string $key): array
    {
        return $this->cached('openai:'.sha1($key), fn (): array => $this->ids(
            Http::timeout(10)->withToken($key)->acceptJson()->get('https://api.openai.com/v1/models')->throw()->json('data'),
        ));
    }

    /**
     * @return list<string>
     */
    private function anthropic(string $key): array
    {
        return $this->cached('anthropic:'.sha1($key), fn (): array => $this->ids(
            Http::timeout(10)
                ->withHeaders(['x-api-key' => $key, 'anthropic-version' => '2023-06-01'])
                ->acceptJson()
                ->get('https://api.anthropic.com/v1/models')
                ->throw()
                ->json('data'),
        ));
    }

    private function requireKey(AiProvider $provider, ?string $key): string
    {
        if (blank($key)) {
            throw new RuntimeException("{$provider->label()} için önce bir API anahtarı girin.");
        }

        return $key;
    }

    /**
     * Sağlayıcı yanıtından model kimliklerini çıkarır.
     *
     * @return list<string>
     */
    private function ids(mixed $data): array
    {
        if (! is_array($data)) {
            throw new RuntimeException('Sağlayıcı model listesini beklenen biçimde döndürmedi.');
        }

        $ids = [];

        foreach ($data as $model) {
            if (is_array($model) && is_string($model['id'] ?? null) && $model['id'] !== '') {
                $ids[] = $model['id'];
            }
        }

        $ids = array_values(array_unique($ids));
        sort($ids);

        if ($ids === []) {
            throw new RuntimeException('Sağlayıcı hiç model döndürmedi.');
        }

        return $ids;
    }

    /**
     * @param  Closure(): list<string>  $fetch
     * @return list<string>
     */
    private function cached(string $suffix, Closure $fetch): array
    {
        /** @var list<string> $models */
        $models = Cache::remember("ai:models:{$suffix}", now()->addMinutes(self::CACHE_TTL_MINUTES), $fetch);

        return $models;
    }
}
