<?php

namespace App\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;
use Spatie\LaravelSettings\Settings;

/**
 * Yüklenen ders kitaplarının depolama ayarları.
 *
 * Cloudinary kimlik bilgileri panelden girilir ve ŞİFRELİ saklanır; API
 * sırrı tarayıcıya hiçbir zaman gönderilmez.
 */
class MediaSettings extends Settings
{
    /** `local` (sunucunun özel diski) ya da `cloudinary`. */
    public string $default_target;

    public ?string $cloudinary_cloud_name;

    #[ShouldBeEncrypted]
    public ?string $cloudinary_api_key;

    #[ShouldBeEncrypted]
    public ?string $cloudinary_api_secret;

    /** Hesap başka projelerle ortak olabilir; dosyalar bu klasöre yüklenir. */
    public ?string $cloudinary_folder;

    /** Cloudinary'ye gönderilecek dosyanın üst sınırı (MB). */
    public int $cloudinary_max_megabytes;

    public static function group(): string
    {
        return 'media';
    }

    public function cloudinaryConfigured(): bool
    {
        return filled($this->cloudinary_cloud_name)
            && filled($this->cloudinary_api_key)
            && filled($this->cloudinary_api_secret);
    }

    /**
     * Cloudinary neden kullanılamıyor? Kullanılabiliyorsa null. Sessizce
     * yerele düşmek yanlış olurdu: yönetici dosyayı bulutta sanır.
     */
    public function cloudinaryRejection(int $bytes): ?string
    {
        if (! $this->cloudinaryConfigured()) {
            return 'Cloudinary yapılandırılmamış; dosya sunucuya kaydedildi.';
        }

        if ($bytes > $this->cloudinary_max_megabytes * 1024 * 1024) {
            return "Dosya Cloudinary sınırını ({$this->cloudinary_max_megabytes} MB) aşıyor; sunucuya kaydedildi.";
        }

        return null;
    }
}
