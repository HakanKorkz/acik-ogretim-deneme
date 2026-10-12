<?php

use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('ai', function (SettingsBlueprint $blueprint): void {
            // Boş (null) ise QuestionWriter'ın gömülü varsayılan istemi kullanılır.
            $blueprint->add('system_prompt', null);
        });
    }
};
