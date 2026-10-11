<?php

namespace App\Services\PastExams;

use App\Enums\CourseLevel;
use App\Models\Course;
use Illuminate\Database\Eloquent\Collection;

/**
 * Kitapçıktaki ders adını ("SEÇMELİ BİYOLOJİ – 2", "DİN KÜL. VE AHL. BİL. – 1")
 * katalogdaki derse ("Biyoloji-6 (Seçmeli Biyoloji 2)", "Din Kültürü ve
 * Ahlak Bilgisi-1") eşler.
 *
 * Adlar sadeleştirilip (kısaltmalar açılır, sözcükler köklerine kısaltılır)
 * sözcük kümeleri karşılaştırılır. "Seçmeli" ayırt edicidir: seçmeli bir
 * sınav dersi yalnızca seçmeli bir katalog dersiyle eşleşir.
 */
class ExamCourseMatcher
{
    /**
     * Kitapçıklardaki kısaltmalar.
     *
     * @var array<string, string>
     */
    private const ABBREVIATIONS = [
        'kul' => 'kulturu', 'ahl' => 'ahlak', 'bil' => 'bilgisi', 'sec' => 'secmeli',
        'hay' => 'hayati', 'edeb' => 'edebiyati', 'tc' => 'turkiye', 't' => 'turkiye',
    ];

    /**
     * @var array<string, true>
     */
    private const IGNORED = ['ve' => true, 'dersi' => true, 'yabanci' => true, 'dil' => true, 'cumhuriyeti' => true];

    /** @var Collection<int, Course>|null */
    private ?Collection $courses = null;

    public function match(string $examName): ?Course
    {
        $examKey = $this->key($examName);

        if ($examKey === []) {
            return null;
        }

        $best = null;
        $bestScore = 0;
        $tie = false;

        foreach ($this->courses() as $course) {
            foreach ($this->aliases($course->name) as $alias) {
                $score = $this->score($alias, $examKey);

                if ($score > $bestScore) {
                    [$best, $bestScore, $tie] = [$course, $score, false];
                } elseif ($score === $bestScore && $score > 0 && $best?->isNot($course)) {
                    $tie = true;
                }
            }
        }

        return $tie ? null : $best;
    }

    /**
     * Katalog adının eşleşmede kullanılacak biçimleri: tam ad, parantez
     * içindeki ad ("Seçmeli Biyoloji 2") ve "Yabancı Dil-1-İngilizce" →
     * "İngilizce 1".
     *
     * @return list<list<string>>
     */
    private function aliases(string $name): array
    {
        $aliases = [$name];

        if (preg_match('/\(([^)]+)\)/u', $name, $matches)) {
            $aliases[] = $matches[1];
            $aliases[] = trim((string) preg_replace('/\([^)]*\)/u', '', $name));
        }

        if (preg_match('/^Yabancı Dil\s*-\s*(\d+)\s*-\s*(.+)$/iu', $name, $matches)) {
            $aliases[] = "{$matches[2]} {$matches[1]}";
        }

        return array_values(array_filter(array_map($this->key(...), $aliases)));
    }

    /**
     * 0: eşleşmez; aksi hâlde ortak sözcük sayısı (tam eşleşme en yüksek).
     *
     * @param  list<string>  $alias
     * @param  list<string>  $exam
     */
    private function score(array $alias, array $exam): int
    {
        if (end($alias) !== end($exam)) {
            return 0;
        }

        if (in_array('secme', $exam, true) !== in_array('secme', $alias, true)) {
            return 0;
        }

        if (array_diff($alias, $exam) !== []) {
            return 0;
        }

        return $alias === $exam ? 100 : count($alias);
    }

    /**
     * Adı karşılaştırılabilir sözcük köklerine çevirir; numara sona alınır.
     *
     * @return list<string>
     */
    private function key(string $name): array
    {
        $name = mb_strtolower(strtr($name, ['İ' => 'i', 'I' => 'ı']), 'UTF-8');
        $name = strtr($name, ['â' => 'a', 'î' => 'i', 'û' => 'u', 'ı' => 'i', 'ö' => 'o', 'ü' => 'u', 'ş' => 's', 'ç' => 'c', 'ğ' => 'g']);
        $words = preg_split('/[^a-z0-9]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $number = null;
        $stems = [];

        foreach ($words as $word) {
            if (ctype_digit($word)) {
                $number = $word;

                continue;
            }

            $word = self::ABBREVIATIONS[$word] ?? $word;

            if (! isset(self::IGNORED[$word])) {
                $stems[] = mb_substr($word, 0, 5);
            }
        }

        if ($stems === [] || $number === null) {
            return [];
        }

        $stems = array_values(array_unique($stems));
        sort($stems);

        return [...$stems, $number];
    }

    /**
     * @return Collection<int, Course>
     */
    private function courses(): Collection
    {
        return $this->courses ??= Course::query()->where('level', CourseLevel::Lise)->get();
    }
}
