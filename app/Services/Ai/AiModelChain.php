<?php

namespace App\Services\Ai;

use App\Settings\AiSettings;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Ai;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Responses\StructuredAgentResponse;
use Throwable;

/**
 * Panelde seçilen sağlayıcıyla, model listesini SIRAYLA deneyerek
 * yapılandırılmış yanıt alır. Bir model hata verirse ya da yanıtı kabul
 * edilmezse sıradakine geçilir.
 */
class AiModelChain
{
    /**
     * Çalışma anında oluşturulan sağlayıcının adı.
     */
    public const PROVIDER_NAME = 'exam_questions';

    private bool $registered = false;

    public function __construct(
        private readonly AiSettings $settings,
    ) {}

    /**
     * Sağlayıcı için anahtar ve en az bir model tanımlı mı?
     */
    public function isConfigured(): bool
    {
        return $this->settings->configured();
    }

    /**
     * Modelleri sırayla dener; `$accept` boş olmayan bir sonuç döndürene
     * kadar devam eder. Hiçbiri olmazsa null döner.
     *
     * @template TResult
     *
     * @param  callable(array<string, mixed>, string): (TResult|null)  $accept  Yapılandırılmış çıktı ve modelin adı.
     * @return TResult|null
     */
    public function run(Agent $agent, string $prompt, callable $accept, ?int $timeout = null): mixed
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $this->register();

        foreach ($this->settings->models as $model) {
            try {
                $response = $agent->prompt(
                    $prompt,
                    provider: self::PROVIDER_NAME,
                    model: $model,
                    timeout: $timeout ?? (int) config('exam.ai.timeout'),
                );

                if (! $response instanceof StructuredAgentResponse) {
                    throw new \RuntimeException('Model yapılandırılmış yanıt döndürmedi.');
                }

                $result = $accept($response->toArray(), $model);

                if ($result !== null && $result !== []) {
                    return $result;
                }

                throw new \RuntimeException('Model geçerli içerik döndürmedi.');
            } catch (Throwable $exception) {
                Log::warning('Yapay zekâ isteği başarısız, sıradaki model deneniyor.', [
                    'agent' => class_basename($agent),
                    'provider' => $this->settings->provider,
                    'model' => $model,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * Seçili sağlayıcıyı panel anahtarıyla laravel/ai'ye tanıtır.
     */
    private function register(): void
    {
        if ($this->registered) {
            return;
        }

        $provider = $this->settings->activeProvider();

        Ai::build([
            'name' => self::PROVIDER_NAME,
            'driver' => $provider->lab()->value,
            'key' => $this->settings->keyFor($provider),
        ]);

        $this->registered = true;
    }
}
