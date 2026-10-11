<?php

use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('ai', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('provider', 'openrouter');

            // Anahtarlar panelden girilene kadar boş kalır; o sürede .env kullanılır.
            $blueprint->addEncrypted('openrouter_key', null);
            $blueprint->addEncrypted('openai_key', null);
            $blueprint->addEncrypted('anthropic_key', null);

            $models = config('exam.ai.initial_models');

            $blueprint->add('models', is_array($models) && $models !== [] ? array_values($models) : ['openrouter/free']);
            $blueprint->add('questions_per_request', (int) config('exam.ai.initial_questions_per_request', 10));
        });
    }
};
