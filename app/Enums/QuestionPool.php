<?php

namespace App\Enums;

/**
 * Öğrencinin sınav için seçtiği soru havuzu.
 */
enum QuestionPool: string
{
    case PastExam = 'past_exam';

    case Generated = 'generated';

    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::PastExam => 'Yalnızca çıkmış sorular',
            self::Generated => 'Yapay zekâ destekli sorular',
            self::Mixed => 'Karışık',
        };
    }

    /**
     * Havuza giren soru kaynakları.
     *
     * @return list<QuestionSource>
     */
    public function sources(): array
    {
        return match ($this) {
            self::PastExam => [QuestionSource::PastExam],
            self::Generated => [QuestionSource::Ai, QuestionSource::Local],
            self::Mixed => QuestionSource::cases(),
        };
    }
}
