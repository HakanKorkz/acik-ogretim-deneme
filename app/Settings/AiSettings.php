<?php

namespace App\Settings;

use App\Ai\Agents\QuestionWriter;
use App\Enums\AiProvider;
use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

/**
 * Yapay zekâ ile soru üretimi ayarları.
 *
 * Anahtarlar panelden girilir ve ŞİFRELİ saklanır; tarayıcıya hiçbir zaman
 * geri gönderilmez. Panelde anahtar yoksa .env'deki değer kullanılır.
 * Model listesi SIRALIDIR: ilk model denenir, yanıt vermezse sıradaki.
 */
class AiSettings extends Settings
{
    public string $provider;

    #[ShouldBeEncrypted]
    public ?string $openrouter_key;

    #[ShouldBeEncrypted]
    public ?string $openai_key;

    #[ShouldBeEncrypted]
    public ?string $anthropic_key;

    /** @var list<string> */
    public array $models;

    /** Tek istekte istenecek soru sayısı. */
    public int $questions_per_request;

    /**
     * Soru üreten ajanın sistem istemi. Boş (null) ise kod içindeki
     * varsayılan istem kullanılır (`QuestionWriter::defaultInstructions()`).
     */
    public ?string $system_prompt;

    public static function group(): string
    {
        return 'ai';
    }

    /**
     * Etkin sistem istemi: panelde özelleştirilmişse onu, yoksa varsayılanı döndürür.
     */
    public function systemPrompt(): string
    {
        return filled($this->system_prompt)
            ? $this->system_prompt
            : QuestionWriter::defaultInstructions();
    }

    public function activeProvider(): AiProvider
    {
        return AiProvider::tryFrom($this->provider) ?? AiProvider::OpenRouter;
    }

    /**
     * Sağlayıcının anahtarı: önce panel, yoksa .env.
     */
    public function keyFor(AiProvider $provider): ?string
    {
        $panelKey = match ($provider) {
            AiProvider::OpenRouter => $this->openrouter_key,
            AiProvider::OpenAi => $this->openai_key,
            AiProvider::Anthropic => $this->anthropic_key,
        };

        if (filled($panelKey)) {
            return $panelKey;
        }

        $envKey = config("exam.ai.keys.{$provider->value}");

        return is_string($envKey) && $envKey !== '' ? $envKey : null;
    }

    /**
     * Anahtar panelden mi girilmiş (yoksa .env'den mi geliyor)?
     */
    public function hasPanelKey(AiProvider $provider): bool
    {
        return filled(match ($provider) {
            AiProvider::OpenRouter => $this->openrouter_key,
            AiProvider::OpenAi => $this->openai_key,
            AiProvider::Anthropic => $this->anthropic_key,
        });
    }

    /**
     * Seçili sağlayıcı için anahtar ve en az bir model tanımlı mı?
     */
    public function configured(): bool
    {
        return $this->keyFor($this->activeProvider()) !== null && $this->models !== [];
    }
}
