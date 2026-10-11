<?php

namespace App\Enums;

/**
 * Bir sorunun nereden geldiği.
 */
enum QuestionSource: string
{
    /** Yapay zekânın ders kitabından ürettiği soru. */
    case Ai = 'ai';

    /** Yerel (sezgisel) üreticinin ürettiği soru. */
    case Local = 'local';

    /** MEB'in yayımladığı çıkmış sınav sorusu. */
    case PastExam = 'past_exam';

    public function label(): string
    {
        return match ($this) {
            self::Ai => 'Yapay zekâ',
            self::Local => 'Otomatik',
            self::PastExam => 'Çıkmış soru',
        };
    }

    public function isGenerated(): bool
    {
        return $this !== self::PastExam;
    }
}
