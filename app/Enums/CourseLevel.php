<?php

namespace App\Enums;

/**
 * Dersin ait olduğu açık öğretim kademesi. Ön yüzde kullanıcı önce kademeyi
 * seçer; sınavlar kademeye göre ayrı gösterilir.
 */
enum CourseLevel: string
{
    case Lise = 'lise';

    case Ortaokul = 'ortaokul';

    /**
     * Kullanıcı arayüzünde gösterilecek ad.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lise => 'Açık Öğretim Lisesi',
            self::Ortaokul => 'Açık Öğretim Ortaokulu',
        };
    }

    /**
     * aokul.gov.tr kitap listesindeki `type` parametresi.
     */
    public function aokulType(): int
    {
        return match ($this) {
            self::Lise => 2,
            self::Ortaokul => 1,
        };
    }
}
