<?php

namespace App\Services\Questions;

use App\Enums\QuestionSource;
use App\Models\BookPage;
use App\Models\Course;
use App\Models\Question;
use App\Services\Books\CourseTextExtractor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Üretilen soruları dersin soru bankasına yazar.
 *
 * @phpstan-import-type GeneratedQuestion from QuestionGenerator
 */
class QuestionBankImporter
{
    /** @var array<int, string> */
    private const LABELS = ['A', 'B', 'C', 'D'];

    /**
     * Ekleme modunda yapay zekâya gönderilecek en fazla sayfa sayısı.
     */
    private const APPEND_WINDOW_PAGES = 40;

    public function __construct(
        private readonly QuestionGenerator $generator,
        private readonly CourseTextExtractor $textExtractor,
        private readonly QuestionQualityGuard $guard,
    ) {}

    /**
     * Dersin soru bankasını sorumlu olduğu sayfalardan YENİDEN üretir.
     * Kitap metni henüz çıkarılmamışsa önce çıkarılır.
     *
     * @return int Oluşturulan soru sayısı.
     */
    public function import(Course $course, int $count = 10): int
    {
        $pages = $this->scopePages($course);

        $generated = $this->usable($this->generator->generate($this->sourceText($course, $pages), $count));

        if ($generated === []) {
            return 0;
        }

        return DB::transaction(function () use ($course, $generated): int {
            $this->retireGeneratedQuestions($course);

            return $this->store($course, $generated);
        });
    }

    /**
     * Mevcut bankaya yeni sorular EKLER. Kitabın rastgele bir bölümü
     * kullanılır ve bankada zaten bulunan sorular atlanır; böylece banka
     * zamanla çeşitlenir ve sınavlar birbirini tekrar etmez.
     *
     * @return int Eklenen soru sayısı.
     */
    public function append(Course $course, int $count): int
    {
        $pages = $this->scopePages($course);

        if ($pages->count() > self::APPEND_WINDOW_PAGES) {
            $start = random_int(0, $pages->count() - self::APPEND_WINDOW_PAGES);
            $pages = $pages->slice($start, self::APPEND_WINDOW_PAGES)->values();
        }

        $generated = $this->usable($this->generator->generate($this->sourceText($course, $pages), $count));

        $existing = $course->questions()
            ->pluck('text')
            ->mapWithKeys(fn (string $text): array => [$this->normalizeText($text) => true]);

        $fresh = array_values(array_filter(
            $generated,
            fn (array $question): bool => ! $existing->has($this->normalizeText($question['text'])),
        ));

        if ($fresh === []) {
            return 0;
        }

        return DB::transaction(fn (): int => $this->store($course, $fresh));
    }

    /**
     * @return Collection<int, BookPage>
     */
    private function scopePages(Course $course): Collection
    {
        if (! $course->hasText()) {
            $this->textExtractor->extract($course);
            $course->refresh();
        }

        $pages = $course->pagesInScope();

        if (trim($pages->pluck('content')->implode('')) === '') {
            throw new RuntimeException("{$course->name} için sorumluluk aralığında metin bulunamadı.");
        }

        return $pages;
    }

    /**
     * Sayfaları, basılı numaralarıyla işaretleyerek tek metne çevirir.
     * Yapay zekâ her sorunun hangi sayfadan geldiğini bu işaretlerle bildirir.
     *
     * @param  Collection<int, BookPage>  $pages
     */
    private function sourceText(Course $course, Collection $pages): string
    {
        $offset = $course->page_offset ?? 0;

        return $pages
            ->map(fn (BookPage $page): string => '[Sayfa '.($page->page_number - $offset)."]\n".$page->content)
            ->implode("\n");
    }

    /**
     * @param  list<GeneratedQuestion>  $generated
     */
    private function store(Course $course, array $generated): int
    {
        foreach ($generated as $data) {
            $data = $this->shuffleOptions($data);

            /** @var Question $question */
            $question = $course->questions()->create([
                'passage' => filled($data['passage'] ?? null) ? $data['passage'] : null,
                'text' => $data['text'],
                'correct_option' => $data['correct_option'],
                'source' => QuestionSource::tryFrom($data['source'] ?? '') ?? QuestionSource::Local,
                'source_page' => $data['source_page'] ?? null,
            ]);

            $question->options()->createMany($data['options']);
        }

        return count($generated);
    }

    /**
     * Yeniden üretimde eski ÜRETİLMİŞ soruları bankadan çıkarır (çıkmış
     * sorulara dokunulmaz). Hiçbir sınavda kullanılmamış sorular tamamen
     * silinir; kullanılmış olanlar geçmiş sonuçlar bozulmasın diye arşivlenir.
     */
    private function retireGeneratedQuestions(Course $course): void
    {
        $generatedSources = [QuestionSource::Ai, QuestionSource::Local];

        $course->questions()->whereIn('source', $generatedSources)->doesntHave('examAnswers')->forceDelete();

        $course->questions()->whereIn('source', $generatedSources)->delete();
    }

    /**
     * Kitapçıkta tek başına anlamlı olmayan soruları eler.
     *
     * @param  list<GeneratedQuestion>  $generated
     * @return list<GeneratedQuestion>
     */
    private function usable(array $generated): array
    {
        return array_values(array_filter($generated, fn (array $question): bool => $this->guard->passes($question)));
    }

    private function normalizeText(string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $text)), 'UTF-8');
    }

    /**
     * Şıkların sırasını karıştırıp A-D olarak yeniden etiketler.
     *
     * Yapay zekâ modelleri doğru yanıtı çoğunlukla ilk şıkka koyar; bu adım
     * hangi üretici kullanılırsa kullanılsın doğru şıkkın dağılımını dengeler.
     *
     * @param  GeneratedQuestion  $question
     * @return GeneratedQuestion
     */
    private function shuffleOptions(array $question): array
    {
        $options = $question['options'];
        shuffle($options);

        $correctOption = $question['correct_option'];
        $relabeled = [];

        foreach ($options as $index => $option) {
            $label = self::LABELS[$index];

            if ($option['is_correct']) {
                $correctOption = $label;
            }

            $relabeled[] = [
                'label' => $label,
                'text' => $option['text'],
                'is_correct' => $option['is_correct'],
            ];
        }

        return [
            ...$question,
            'correct_option' => $correctOption,
            'options' => $relabeled,
        ];
    }
}
