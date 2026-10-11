<?php

namespace App\Enums;

/**
 * Ders kitabı metninin sayfa sayfa çıkarılma durumu.
 */
enum TextStatus: string
{
    case Queued = 'queued';

    case Running = 'running';

    case Ready = 'ready';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Sırada',
            self::Running => 'Çıkarılıyor',
            self::Ready => 'Hazır',
            self::Failed => 'Başarısız',
        };
    }

    /**
     * Çıkarma işlemi hâlâ sürüyor mu?
     */
    public function isPending(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
