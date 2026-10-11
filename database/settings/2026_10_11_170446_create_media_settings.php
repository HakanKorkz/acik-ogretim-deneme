<?php

use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('media', function (SettingsBlueprint $blueprint): void {
            $blueprint->add('default_target', 'local');
            $blueprint->add('cloudinary_cloud_name', null);
            $blueprint->addEncrypted('cloudinary_api_key', null);
            $blueprint->addEncrypted('cloudinary_api_secret', null);
            $blueprint->add('cloudinary_folder', 'acik-ogretim');
            // Cloudinary ücretsiz planında dosya başına sınır yaklaşık 10 MB.
            $blueprint->add('cloudinary_max_megabytes', 10);
        });
    }
};
