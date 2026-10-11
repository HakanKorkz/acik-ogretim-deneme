<?php

namespace App\Services\Questions;

use App\Models\Course;
use App\Models\Question;
use App\Services\Pdf\PdfTextExtractor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuestionBankImporter
{
    /** @var array<int, string> */
    private const LABELS = ['A', 'B', 'C', 'D'];

    public function __construct(
        private readonly QuestionGenerator $generator,
        private readonly PdfTextExtractor $extractor,
    ) {}

    /**
     * Bir dersin PDF kaynağından soruları üretip veritabanına kaydeder.
     *
     * @return int Oluşturulan soru sayısı.
     */
    public function import(Course $course, int $count = 10, ?int $maxPages = null): int
    {
        $path = $course->pdfAbsolutePath();

        if ($path === null) {
            throw new RuntimeException("Ders için PDF yolu tanımlı değil: {$course->name}");
        }

        $text = $this->extractor->extract($path, $maxPages);

        $generated = $this->generator->generate($text, $count);

        if ($generated === []) {
            return 0;
        }

        return DB::transaction(function () use ($course, $generated): int {
            $this->retireExistingQuestions($course);

            foreach ($generated as $data) {
                $data = $this->shuffleOptions($data);

                /** @var Question $question */
                $question = $course->questions()->create([
                    'text' => $data['text'],
                    'correct_option' => $data['correct_option'],
                ]);

                $question->options()->createMany($data['options']);
            }

            return count($generated);
        });
    }

    /**
     * Dersteki soruları, seçenekleriyle birlikte rastgele döndürür.
     *
     * @return Collection<int, Question>
     */
    public function randomQuestionsFor(Course $course, int $count): Collection
    {
        return $course->questions()
            ->with('options')
            ->inRandomOrder()
            ->limit($count)
            ->get();
    }

    /**
     * Yeniden üretimde eski soruları bankadan çıkarır. Hiçbir sınavda
     * kullanılmamış sorular tamamen silinir; kullanılmış olanlar geçmiş
     * sonuçlar bozulmasın diye arşivlenir (soft delete).
     */
    private function retireExistingQuestions(Course $course): void
    {
        $course->questions()->doesntHave('examAnswers')->forceDelete();

        $course->questions()->delete();
    }

    /**
     * Şıkların sırasını karıştırıp A-D olarak yeniden etiketler.
     *
     * Yapay zekâ modelleri doğru yanıtı çoğunlukla ilk şıkka koyar; bu adım
     * hangi üretici kullanılırsa kullanılsın doğru şıkkın dağılımını dengeler.
     *
     * @param  array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>}  $question
     * @return array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>}
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
            'text' => $question['text'],
            'correct_option' => $correctOption,
            'options' => $relabeled,
        ];
    }
}
