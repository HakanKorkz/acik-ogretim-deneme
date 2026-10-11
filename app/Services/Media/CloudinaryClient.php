<?php

namespace App\Services\Media;

use App\Settings\MediaSettings;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cloudinary'nin imzalı Upload API'si üzerinden ham (raw) dosya yükler ve
 * siler. PDF'ler `raw` türünde tutulur; silme de aynı türle yapılmalıdır,
 * yoksa dosya hesapta kalıcı çöp olarak kalır.
 *
 * Resmî SDK Guzzle 7 istediği için (proje Guzzle 8 kullanıyor) doğrudan
 * Laravel HTTP istemcisi kullanılır.
 */
class CloudinaryClient
{
    private const API = 'https://api.cloudinary.com/v1_1';

    public function __construct(
        private readonly MediaSettings $settings,
    ) {}

    /**
     * @return array{url: string, public_id: string}
     */
    public function uploadRaw(string $path, string $publicId): array
    {
        $params = array_filter([
            'folder' => $this->settings->cloudinary_folder,
            'public_id' => $publicId,
            'timestamp' => (string) time(),
        ], fn (?string $value): bool => filled($value));

        $response = Http::timeout(300)
            ->attach('file', (string) file_get_contents($path), basename($path))
            ->post($this->endpoint('upload'), $this->signed($params));

        if (! $response->successful() || ! is_string($response->json('secure_url'))) {
            throw new RuntimeException('Cloudinary yüklemesi başarısız: '.($response->json('error.message') ?? "HTTP {$response->status()}"));
        }

        return [
            'url' => (string) $response->json('secure_url'),
            'public_id' => (string) $response->json('public_id'),
        ];
    }

    public function destroyRaw(string $publicId): void
    {
        Http::timeout(60)
            ->asForm()
            ->post($this->endpoint('destroy'), $this->signed([
                'public_id' => $publicId,
                'timestamp' => (string) time(),
            ]));
    }

    private function endpoint(string $action): string
    {
        return self::API.'/'.rawurlencode((string) $this->settings->cloudinary_cloud_name)."/raw/{$action}";
    }

    /**
     * İmzalanacak alanlar adlarına göre sıralanıp `ad=değer` biçiminde `&` ile
     * birleştirilir, sonuna API sırrı eklenir ve SHA-1 alınır.
     *
     * @param  array<string, string>  $params
     * @return array<string, string>
     */
    private function signed(array $params): array
    {
        ksort($params);

        $payload = implode('&', array_map(
            static fn (string $key, string $value): string => "{$key}={$value}",
            array_keys($params),
            $params,
        ));

        return [
            ...$params,
            'api_key' => (string) $this->settings->cloudinary_api_key,
            'signature' => sha1($payload.$this->settings->cloudinary_api_secret),
        ];
    }
}
