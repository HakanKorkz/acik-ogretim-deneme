<?php

namespace App\Ai\Concerns;

use Laravel\Ai\Enums\Lab;

/**
 * OpenRouter'daki "düşünen" (reasoning) modellerde muhakemeyi kapatır.
 *
 * Aksi hâlde ücretsiz modeller çıktı bütçesinin çoğunu muhakemeye harcar
 * ve yapılandırılmış yanıt (JSON) yarım ya da boş kalır.
 */
trait DisablesReasoning
{
    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        $driver = $provider instanceof Lab ? $provider->value : $provider;

        return $driver === Lab::OpenRouter->value
            ? ['reasoning' => ['enabled' => false]]
            : [];
    }
}
