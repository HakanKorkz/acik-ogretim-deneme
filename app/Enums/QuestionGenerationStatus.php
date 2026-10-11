<?php

namespace App\Enums;

enum QuestionGenerationStatus: string
{
    case Queued = 'queued';

    case Running = 'running';

    case Failed = 'failed';

    /**
     * Kullanıcı arayüzünde gösterilecek etiket.
     */
    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Sırada',
            self::Running => 'Üretiliyor',
            self::Failed => 'Başarısız',
        };
    }

    /**
     * Üretim hâlâ devam ediyor mu (sırada veya çalışıyor)?
     */
    public function isPending(): bool
    {
        return $this !== self::Failed;
    }
}
