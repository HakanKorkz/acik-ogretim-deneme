<?php

namespace App\Enums;

enum ExamStatus: string
{
    case InProgress = 'in_progress';

    case Completed = 'completed';

    /**
     * Kullanıcı arayüzünde gösterilecek etiket.
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Devam ediyor',
            self::Completed => 'Tamamlandı',
        };
    }
}
