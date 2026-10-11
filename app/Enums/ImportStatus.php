<?php

namespace App\Enums;

/**
 * Çıkmış sınav kitapçığı ve bölümlerinin içe aktarma durumu.
 */
enum ImportStatus: string
{
    case Queued = 'queued';

    case Running = 'running';

    case Done = 'done';

    case Skipped = 'skipped';

    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Sırada',
            self::Running => 'İşleniyor',
            self::Done => 'Tamamlandı',
            self::Skipped => 'Atlandı',
            self::Failed => 'Başarısız',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Queued || $this === self::Running;
    }
}
