<?php

namespace App\Enums;

use Laravel\Ai\Enums\Lab;

/**
 * Soru üretiminde kullanılabilen yapay zekâ sağlayıcıları.
 */
enum AiProvider: string
{
    case OpenRouter = 'openrouter';

    case OpenAi = 'openai';

    case Anthropic = 'anthropic';

    /**
     * Kullanıcı arayüzünde gösterilecek ad.
     */
    public function label(): string
    {
        return match ($this) {
            self::OpenRouter => 'OpenRouter',
            self::OpenAi => 'OpenAI',
            self::Anthropic => 'Anthropic',
        };
    }

    /**
     * laravel/ai sürücüsü.
     */
    public function lab(): Lab
    {
        return match ($this) {
            self::OpenRouter => Lab::OpenRouter,
            self::OpenAi => Lab::OpenAI,
            self::Anthropic => Lab::Anthropic,
        };
    }

    /**
     * Panelde model alanı boşken önerilen model kimlikleri.
     *
     * @return list<string>
     */
    public function suggestedModels(): array
    {
        return match ($this) {
            self::OpenRouter => ['openrouter/free'],
            self::OpenAi => ['gpt-5-mini'],
            self::Anthropic => ['claude-haiku-5-5'],
        };
    }
}
