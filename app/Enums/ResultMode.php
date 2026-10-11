<?php

namespace App\Enums;

/**
 * Çok dersli sınavda sonuçların ne zaman gösterileceği.
 */
enum ResultMode: string
{
    /** Her ders bitince o dersin sonucu gösterilir. */
    case PerCourse = 'per_course';

    /** Tüm dersler bitince toplu sonuç gösterilir. */
    case Combined = 'combined';

    public function label(): string
    {
        return match ($this) {
            self::PerCourse => 'Her ders bitince sonucunu göster',
            self::Combined => 'Tüm dersler bitince toplu sonuç göster',
        };
    }
}
