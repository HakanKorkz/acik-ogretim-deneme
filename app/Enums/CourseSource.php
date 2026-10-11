<?php

namespace App\Enums;

/**
 * Ders kitabının nereden geldiği.
 */
enum CourseSource: string
{
    /** MEB aokul.gov.tr kataloğu; PDF barındırılmaz, adresinden indirilir. */
    case Aokul = 'aokul';

    /** Yöneticinin yüklediği PDF. */
    case Upload = 'upload';

    public function label(): string
    {
        return match ($this) {
            self::Aokul => 'MEB kataloğu',
            self::Upload => 'Yüklenen PDF',
        };
    }
}
