<?php

namespace App\Services\Questions;

/**
 * PDF'ten çıkarılan ham metni kullanarak, harici bir servise ihtiyaç duymadan
 * Açık Öğretim Lisesi sınav kalıplarına yakın çoktan seçmeli sorular üreten
 * yerel (sezgisel) üretici.
 *
 * Gerçek sınavlardaki baskın kalıplar örnek alınır:
 *  1. Metinden kavram: "Aşağıdaki metinde anlatılan kavram hangisidir?"
 *     Gövde bir tanım cümlesidir, doğru yanıt o tanıma ait kavramdır.
 *  2. Kavramdan tanım: "«Roman» ile ilgili aşağıdakilerden hangisi doğrudur?"
 *     Doğru yanıt o kavramın tanımıdır.
 *  3. Boşluk doldurma: özel isim / tarih-sayı / güçlü isim hedeflenir; çeldirici
 *     ve doğru yanıt aynı türden seçilir.
 */
class LocalQuestionGenerator implements QuestionGenerator
{
    /** @var array<int, string> */
    private const SELECTION_LABELS = ['A', 'B', 'C', 'D'];

    /** @var array<int, string> */
    private const CONCEPT_STEMS = [
        'Aşağıdaki metinde anlatılan kavram aşağıdakilerden hangisidir?',
        'Bu parçada açıklanan kavram aşağıdakilerden hangisidir?',
        'Aşağıda verilen metinde söz edilen kavram aşağıdakilerden hangisidir?',
    ];

    /** @var array<int, string> */
    private const DEFINITION_STEMS = [
        '“%s” ile ilgili aşağıdakilerden hangisi doğrudur?',
        'Aşağıdakilerden hangisi “%s” ile ilgilidir?',
        '“%s” hakkında aşağıdakilerden hangisi söylenebilir?',
    ];

    /** @var array<int, string> */
    private const STOPWORDS = [
        'acaba', 'ama', 'ancak', 'artık', 'asla', 'aslında', 'ayrıca', 'az', 'bana', 'bazen',
        'bazı', 'bazıları', 'belki', 'ben', 'benden', 'beni', 'benim', 'beri', 'beş', 'bile',
        'bir', 'biraz', 'birçok', 'biri', 'birkaç', 'birşey', 'biz', 'bizden', 'bize', 'bizi',
        'bizim', 'böyle', 'böylece', 'bu', 'buna', 'bunda', 'bundan', 'bunlar', 'bunları',
        'bunların', 'bunu', 'bunun', 'burada', 'bütün', 'çok', 'çünkü', 'da', 'daha', 'dahi',
        'de', 'değil', 'diğer', 'diye', 'dolayı', 'dört', 'edecek', 'eden', 'ederek', 'edilecek',
        'ediliyor', 'edilmesi', 'ediyor', 'eğer', 'elbette', 'en', 'etmesi', 'etti', 'ettiği',
        'ettiğini', 'gibi', 'göre', 'halen', 'hangi', 'hariç', 'hatta', 'hem', 'henüz', 'hep',
        'hepsi', 'her', 'herhangi', 'herkes', 'herkesin', 'hiç', 'hiçbir', 'için', 'içinde',
        'iki', 'ile', 'ilgili', 'ise', 'işte', 'kadar', 'karşın', 'kendi', 'kendine', 'kendini',
        'kendisi', 'kez', 'ki', 'kim', 'kimi', 'kimse', 'kısaca', 'madem', 'mi', 'mı', 'mu',
        'mü', 'nasıl', 'ne', 'neden', 'nedenle', 'nerde', 'nerede', 'nereye', 'neyse', 'niçin',
        'niye', 'o', 'olarak', 'oldu', 'olduğu', 'olduğunu', 'olmadı', 'olmadığı', 'olmak',
        'olması', 'olmayan', 'olsun', 'olup', 'olur', 'olursa', 'on', 'ona', 'ondan', 'onlar',
        'onlara', 'onları', 'onların', 'onu', 'onun', 'orada', 'öyle', 'oysa', 'pek', 'rağmen',
        'sadece', 'sanırım', 'sanki', 'şayet', 'senden', 'seni', 'senin', 'şey', 'şeyden',
        'şeye', 'şeyi', 'şeyler', 'şimdi', 'şöyle', 'şu', 'şuna', 'şunda', 'şundan', 'şunları',
        'şunu', 'tarafından', 'tüm', 'tümü', 'üç', 'üzere', 'var', 'vardı', 've', 'veya',
        'veyahut', 'ya', 'yani', 'yaparak', 'yapılan', 'yapılması', 'yapıyor', 'yapmak',
        'yaptı', 'yaptığı', 'yaptığını', 'yaptıkları', 'yerine', 'yine', 'yoksa',
        'zaten', 'zira',
    ];

    /**
     * Soru üretmeye uygun olmayan (yönerge, künye, etkinlik) cümleleri
     * elemek için kullanılan ifadeler.
     *
     * @var array<int, string>
     */
    private const NOISE_PATTERNS = [
        'aşağıdaki', 'aşağıda', 'yukarıdaki', 'arkadaşlarınızla', 'söyleyiniz',
        'yazınız', 'cevaplayınız', 'inceleyerek', 'paylaşınız', 'oluşturunuz',
        'belirtiniz', 'tartışınız', 'tamamlayınız', 'doldurunuz', 'hazırlayınız',
        'araştırınız', 'gözlemleyiniz', 'uygulayınız', 'dinleyiniz', 'izleyiniz',
        'sınıfta', 'etkinlik', 'tabloyu', 'görselleri', 'karekodda',
        'her hakkı saklıdır', 'isbn', 'millî eğitim bakanlığı', 'tel.:',
        'yayınları', 'ders kitabı', 'kitabın', 'baskı', 'öğretmen',
        'yayımlanamaz', 'yayımlan', 'alınıp', 'surette', 'kısmen', 'ticaret ünvanı',
        'düzenleyebilir', 'paylaşmak üzere', 'sunumunuzu', 'akran', 'kartı',
        'dijital ortam', 'formu', 'sınıfça', 'grupça',
        '➡', '→', '▶', '►', '»',
        'açıklayınız', 'cevap veriniz', 'başlatmak', 'gönüllü', 'katılınız',
        'paylaşarak', 'zinciri', 'etkinliği', 'sorularınız', 'cevaplarınız',
        'düşüncelerinizi', 'görüşlerinizi', 'not alınız', 'not alın',
        'veriniz', 'yazalım', 'söyleyelim', 'dolduralım', 'işaretleyiniz',
        'karşılaştırınız', 'karşılaştır', 'tahayyül', 'ölçüt', 'tabloda',
        'inceleyiniz', 'araştırı', 'tamamlayın', 'eşleştiriniz',
        'işaretlediyseniz', 'işaretleyiniz', 'gözden geçir', 'performans görev',
        'görevinde', 'film şeridi', 'seçeneklerini', 'davranışlarınıza',
        'beklenmektedir', 'gözden geçiriniz', 'yapınız', 'tasarladığınız',
        'gösteriniz', 'özen göster', 'metninizi yaz',
    ];

    /**
     * Kavram (tanım öznesi) olması mantıksız sözcükler.
     *
     * @var array<int, string>
     */
    private const SUBJECT_BLACKLIST = [
        'hayır', 'evet', 'örneğin', 'ancak', 'ayrıca', 'yani', 'kısaca',
        'böylece', 'sonuç', 'özet', 'bunun', 'şöyle', 'aşağıdaki', 'yukarıdaki',
    ];

    /**
     * Fiil çekimlerini (yapılmıştır, olmaktadır, gider, yazar...) yaklaşık
     * olarak ayırt eden ekler. Bu eklerle biten sözcükler boşluk hedefi olmaz.
     *
     * @var array<int, string>
     */
    private const VERB_LIKE_SUFFIXES = [
        'dır', 'dir', 'dur', 'dür', 'tır', 'tir', 'tur', 'tür',
        'mış', 'miş', 'muş', 'müş', 'yor', 'ecek', 'acak', 'malı', 'meli',
        'mekte', 'makta', 'arak', 'erek', 'ince', 'ınca', 'iken', 'dığı',
        'diği', 'duğu', 'düğü', 'acağı', 'eceği', 'larak', 'lerek', 'madan',
        'meden', 'ları', 'leri',
    ];

    /**
     * Büyük harfle yazılsa da özel isim yerine unvan olarak kullanılan
     * sözcükler. Boşluk hedefi ya da çeldirici olarak anlamsız şıklar üretir
     * (ör. "Ankara" sorusunda "Hanım", "Hacı").
     *
     * @var array<int, string>
     */
    private const TITLE_WORDS = [
        'hanım', 'bey', 'paşa', 'hacı', 'efendi', 'ağa', 'sultan', 'han', 'hoca',
        'dede', 'baba', 'ana', 'şeyh', 'molla', 'çelebi', 'hatun', 'gazi', 'reis',
        'hazreti', 'hz', 'sayın', 'bay', 'bayan', 'doktor', 'profesör',
        // Görsel/şekil atıfları: "(Harita 1.3)", "(Görsel 2.1)".
        'harita', 'görsel', 'tablo', 'şekil', 'grafik', 'resim', 'ünite', 'bölüm',
    ];

    /**
     * Sayı/yıl çeldiricisi üretirken kullanılan sapmalar.
     *
     * @var array<int, int>
     */
    private const NUMBER_OFFSETS = [-23, -12, -7, -4, 3, 6, 11, 19];

    public function generate(string $sourceText, int $count = 10): array
    {
        if ($count < 1) {
            return [];
        }

        $sentences = $this->extractSentences($sourceText);

        if ($sentences === []) {
            return [];
        }

        $definitions = $this->extractDefinitions($sentences);

        /** @var array<string, true> $usedAnswers */
        $usedAnswers = [];

        /** @var array<int, array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>, answer_key: string}> $questions */
        $questions = [];

        // 1) Tanım tabanlı sorular — "kavramdan tanım" ve "metinden kavram"
        //    kalıpları dönüşümlü üretilir.
        foreach ($definitions as $index => $definition) {
            if (count($questions) >= $count) {
                break;
            }

            $question = $index % 2 === 0
                ? $this->makeConceptFromTextQuestion($definition, $definitions, $usedAnswers)
                : $this->makeDefinitionQuestion($definition, $definitions, $usedAnswers);

            if ($question === null) {
                continue;
            }

            $questions[] = $question;
            $usedAnswers[mb_strtolower($definition['subject'], 'UTF-8')] = true;
        }

        // 2) Kalan sorular boşluk doldurma olarak üretilir.
        if (count($questions) < $count) {
            $questions = array_merge(
                $questions,
                $this->buildClozeQuestions($sentences, $count - count($questions), $usedAnswers),
            );
        }

        return array_map(
            static fn (array $question): array => [
                'text' => $question['text'],
                'correct_option' => $question['correct_option'],
                'options' => $question['options'],
            ],
            $questions,
        );
    }

    /**
     * "Aşağıdaki metinde anlatılan kavram hangisidir?" sorusu üretir.
     * Gövde tanım, doğru yanıt kavramdır.
     *
     * @param  array{subject: string, predicate: string}  $definition
     * @param  array<int, array{subject: string, predicate: string}>  $definitions
     * @param  array<string, true>  $usedAnswers
     * @return array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>, answer_key: string}|null
     */
    private function makeConceptFromTextQuestion(array $definition, array $definitions, array $usedAnswers): ?array
    {
        $distractors = $this->pickDistractors(
            pool: $definitions,
            exclude: $definition['subject'],
            value: static fn (array $item): string => $item['subject'],
            targetLength: mb_strlen($definition['subject']),
            usedAnswers: $usedAnswers,
            tolerance: 6,
        );

        if (count($distractors) < 3) {
            return null;
        }

        $body = $this->presentSentence($definition['predicate']);
        $stem = $this->randomFrom(self::CONCEPT_STEMS);

        return $this->assembleQuestion(
            prompt: $stem."\n\n".$body,
            answerText: $definition['subject'],
            distractorTexts: $distractors,
            answerKey: mb_strtolower($definition['subject'], 'UTF-8'),
        );
    }

    /**
     * "«Roman» ile ilgili aşağıdakilerden hangisi doğrudur?" sorusu üretir.
     * Doğru yanıt kavramın tanımıdır.
     *
     * @param  array{subject: string, predicate: string}  $definition
     * @param  array<int, array{subject: string, predicate: string}>  $definitions
     * @param  array<string, true>  $usedAnswers
     * @return array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>, answer_key: string}|null
     */
    private function makeDefinitionQuestion(array $definition, array $definitions, array $usedAnswers): ?array
    {
        $distractors = $this->pickDistractors(
            pool: $definitions,
            exclude: $definition['subject'],
            value: static fn (array $item): string => $item['predicate'],
            targetLength: mb_strlen($definition['predicate']),
            usedAnswers: $usedAnswers,
            tolerance: 45,
            excludeValue: $definition['predicate'],
        );

        if (count($distractors) < 3) {
            return null;
        }

        $stem = sprintf($this->randomFrom(self::DEFINITION_STEMS), $definition['subject']);

        return $this->assembleQuestion(
            prompt: $stem,
            answerText: $this->presentSentence($definition['predicate']),
            distractorTexts: array_map($this->presentSentence(...), $distractors),
            answerKey: mb_strtolower($definition['subject'], 'UTF-8'),
        );
    }

    /**
     * Havuzdan, hariç tutulan öğe dışındaki benzer uzunluktaki değerleri seçer.
     *
     * @template T
     *
     * @param  array<int, T>  $pool
     * @param  callable(T): string  $value
     * @param  array<string, true>  $usedAnswers
     * @return array<int, string>
     */
    private function pickDistractors(
        array $pool,
        string $exclude,
        callable $value,
        int $targetLength,
        array $usedAnswers,
        int $tolerance,
        ?string $excludeValue = null,
    ): array {
        $excludeKey = mb_strtolower($exclude, 'UTF-8');
        $excludeValueKey = $excludeValue === null ? null : mb_strtolower($excludeValue, 'UTF-8');
        $candidates = [];

        foreach ($pool as $item) {
            $text = $value($item);
            $textKey = mb_strtolower($text, 'UTF-8');

            if ($textKey === $excludeKey || $text === $exclude || $textKey === $excludeValueKey) {
                continue;
            }

            $length = mb_strlen($text);

            if ($length < $targetLength - $tolerance || $length > $targetLength + $tolerance) {
                continue;
            }

            $candidates[mb_strtolower($text, 'UTF-8')] = $text;
        }

        $candidates = array_values(array_filter(
            $candidates,
            static fn (string $text): bool => ! isset($usedAnswers[mb_strtolower($text, 'UTF-8')]),
        ));

        shuffle($candidates);

        return array_slice($candidates, 0, 3);
    }

    /**
     * Kaynak metni, soru üretmeye uygun cümlelere ayırır.
     *
     * @return array<int, string>
     */
    private function extractSentences(string $text): array
    {
        $normalized = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text);
        $normalized = preg_replace('/[ \t]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\n{2,}/u', "\n", $normalized) ?? $normalized;
        // PDF satır kaydırmalarında birleşen sözcükleri ayır (ör. "temeliEbedî").
        $normalized = preg_replace('/(?<=[\p{Ll}])(?=[\p{Lu}])/u', ' ', $normalized) ?? $normalized;

        $rawParts = preg_split('/(?<=[.!?])\s+|\n+/u', $normalized) ?: [];

        $sentences = [];

        foreach ($rawParts as $part) {
            $sentence = trim(preg_replace('/\s+/u', ' ', $part) ?? $part);

            if ($sentence === '') {
                continue;
            }

            $length = mb_strlen($sentence);
            $wordCount = count(preg_split('/\s+/u', $sentence) ?: []);

            if ($length < 40 || $length > 260 || $wordCount < 7) {
                continue;
            }

            if (! preg_match('/[.!]$/u', $sentence)) {
                continue;
            }

            if (str_contains($sentence, '______') || $this->isNoisy($sentence)) {
                continue;
            }

            if (preg_match_all('/\d/u', $sentence) > 8) {
                continue;
            }

            // PDF'te cümle sanılan şık satırları (ör. "D) ...").
            if (preg_match('/^[A-D]\)/u', $sentence)) {
                continue;
            }

            // Başlık ile birleşen cümleler ("Kısa Başlık: uzun gövde...").
            $colonPosition = mb_strpos($sentence, ':');

            if ($colonPosition !== false) {
                $beforeColon = mb_substr($sentence, 0, $colonPosition);

                if (count(preg_split('/\s+/u', trim($beforeColon)) ?: []) < 8) {
                    continue;
                }
            }

            $sentences[$sentence] = true;
        }

        return array_keys($sentences);
    }

    /**
     * "Terim, ... bir ...tür." ve "..., ... denir." biçimindeki tanımları çıkarır.
     *
     * Liste/etkinlik cümlelerini elemek için: özne tek sözcük ve büyük harfle
     * başlamalı; gövde "bir" içermeli ve bir ek-fiille (dır/dir/...) bitmelidir;
     * ek-fiilden önceki son sözcük fiil çekimi olmamalıdır.
     *
     * @param  array<int, string>  $sentences
     * @return array<int, array{subject: string, predicate: string}>
     */
    private function extractDefinitions(array $sentences): array
    {
        $definitions = [];

        foreach ($sentences as $sentence) {
            $subject = null;
            $predicate = null;

            if (preg_match('/^(?<subject>[\p{Lu}][\p{L}]{2,24}),\s+(?<rest>.+)$/u', $sentence, $matches)) {
                $subject = trim($matches['subject']);
                $rest = trim($matches['rest']);

                if (
                    preg_match('/\bbir\b/u', $rest)
                    && preg_match('/(?:dır|dir|dur|dür|tır|tir|tur|tür)\.$/u', $rest)
                ) {
                    $predicate = trim((string) preg_replace('/(?:dır|dir|dur|dür|tır|tir|tur|tür)\.$/u', '', $rest));
                }
            } elseif (preg_match('/^(?<subject>[\p{Lu}][^!?]{2,50}?),\s+(?<predicate>[^!?]{20,200}?)\s+(?:denir|demektir)\.$/u', $sentence, $matches)) {
                $subject = trim($matches['subject']);
                $predicate = trim($matches['predicate']);
            } elseif (preg_match('/^(?<subject>[\p{Lu}][^!?]{2,50}?),\s+(?<predicate>[^!?]{20,200}?)\s+(?:olarak tanımlanır|olarak adlandırılır|olarak ifade edilir|diye adlandırılır)\.$/u', $sentence, $matches)) {
                $subject = trim($matches['subject']);
                $predicate = trim($matches['predicate']);
            }

            if ($subject === null || $predicate === null) {
                continue;
            }

            if ($this->isImplausibleSubject($subject)) {
                continue;
            }

            if (count(preg_split('/\s+/u', $predicate) ?: []) < 4) {
                continue;
            }

            // Ek-fiilden önceki son sözcük fiil çekimi olan cümleler tanım değildir.
            $lastWord = $this->lastWord($predicate);

            if ($lastWord === null || $this->looksLikeVerb(mb_strtolower($lastWord, 'UTF-8'))) {
                continue;
            }

            $definitions[mb_strtolower($subject, 'UTF-8')] = [
                'subject' => $subject,
                'predicate' => $predicate,
            ];
        }

        return array_values($definitions);
    }

    private function isImplausibleSubject(string $subject): bool
    {
        $lower = mb_strtolower($subject, 'UTF-8');

        return $this->isStopword($lower)
            || in_array($lower, self::SUBJECT_BLACKLIST, true)
            || $this->isNoisy($lower);
    }

    private function lastWord(string $text): ?string
    {
        $tokens = preg_split('/[\s,;:]+/u', trim($text)) ?: [];
        $tokens = array_values(array_filter($tokens, static fn (string $token): bool => $token !== ''));

        return $tokens === [] ? null : (string) end($tokens);
    }

    /**
     * Özel isim, tarih/sayı ve güçlü isim havuzlarını oluşturur.
     *
     * Özel isim havuzuna yalnızca metinde gerçekten özel isim gibi kullanılan
     * sözcükler girer: kesme işaretiyle ek almış ("Ankara'nın") ya da cümle
     * ortasında en az iki kez büyük harfle geçmiş olmalıdır. Ayrıca her özel
     * ismin aldığı hâl ekleri tutulur; bu sayede yer adlarına yer adları,
     * kişi adlarına kişi adları çeldirici olarak seçilebilir.
     *
     * @param  array<int, string>  $sentences
     * @return array{proper: array<int, string>, number: array<int, string>, noun: array<int, string>, proper_cases: array<string, array<string, true>>}
     */
    private function collectPools(array $sentences): array
    {
        $pools = ['proper' => [], 'number' => [], 'noun' => []];

        // Aynı sözcüğün başka bir cümlede küçük harfle geçmesi, onun özel isim
        // değil sıradan bir sözcük olduğunu gösterir (ör. "beden", "hazırlanan").
        $lowercaseWords = [];

        /** @var array<string, array<string, true>> $properCases */
        $properCases = [];

        /** @var array<string, int> $properOccurrences */
        $properOccurrences = [];

        foreach ($sentences as $sentence) {
            preg_match_all('/[\p{L}\d]+/u', $sentence, $matches);

            foreach ($matches[0] as $token) {
                // Yalnızca gerçekten küçük harfle yazılmış sözcükleri say; böylece
                // özel isimler (Ankara, Haldun...) yanlışlıkla elenmez.
                if (mb_strtolower($token, 'UTF-8') === $token) {
                    $lowercaseWords[$token] = true;
                }
            }

            preg_match_all('/(\p{Lu}[\p{L}]{2,})[\x{0027}\x{2019}]([\p{L}]+)/u', $sentence, $suffixMatches, PREG_SET_ORDER);

            foreach ($suffixMatches as $suffixMatch) {
                $key = mb_strtolower($suffixMatch[1], 'UTF-8');
                $properCases[$key][$this->caseOfSuffix(mb_strtolower($suffixMatch[2], 'UTF-8'))] = true;
            }
        }

        foreach ($sentences as $sentence) {
            preg_match_all('/[\p{L}\d]+/u', $sentence, $matches);
            $compoundWords = $this->compoundNameWords($sentence);

            foreach ($matches[0] as $index => $token) {
                $category = $this->classifyToken($token, $index, true);

                if ($category === null) {
                    continue;
                }

                $key = $category === 'number' ? $token : mb_strtolower($token, 'UTF-8');

                if ($category === 'proper') {
                    if (isset($lowercaseWords[$key]) || isset($compoundWords[$token]) || in_array($key, self::TITLE_WORDS, true)) {
                        continue;
                    }

                    $properOccurrences[$key] = ($properOccurrences[$key] ?? 0) + 1;
                }

                $pools[$category][$key] = $token;
            }
        }

        $pools['proper'] = array_filter(
            $pools['proper'],
            static fn (string $token, string $key): bool => isset($properCases[$key]) || ($properOccurrences[$key] ?? 0) >= 2,
            ARRAY_FILTER_USE_BOTH,
        );

        return [
            'proper' => array_values($pools['proper']),
            'number' => array_values($pools['number']),
            'noun' => array_values($pools['noun']),
            'proper_cases' => $properCases,
        ];
    }

    /**
     * Cümlede çok sözcüklü bir özel adın (ör. "Erdel Prensliği", "Sakız Adası",
     * "Merzifonlu Kara Mustafa Paşa") parçası olan sözcükleri döndürür. Bu
     * sözcüklerden yalnızca birini boşluk yapmak anlamsız sorular üretir.
     *
     * @return array<string, true>
     */
    private function compoundNameWords(string $sentence): array
    {
        preg_match_all('/\p{Lu}[\p{L}]*(?:[ \t]+\p{Lu}[\p{L}]*)+/u', $sentence, $matches);

        $words = [];

        foreach ($matches[0] as $name) {
            foreach (preg_split('/\s+/u', $name) ?: [] as $word) {
                $words[$word] = true;
            }
        }

        return $words;
    }

    /**
     * Kesme işaretinden sonra gelen eki kaba bir hâl sınıfına çevirir.
     * Yer adları çoğunlukla bulunma/ayrılma, kişi adları ilgi/belirtme eki alır.
     */
    private function caseOfSuffix(string $suffix): string
    {
        return match (true) {
            (bool) preg_match('/^n?[dt][ae]n/u', $suffix) => 'ablative',
            (bool) preg_match('/^n?[dt][ae]/u', $suffix) => 'locative',
            (bool) preg_match('/^n?[ıiuü]n/u', $suffix) => 'genitive',
            (bool) preg_match('/^[yn]?[ae]$/u', $suffix) => 'dative',
            (bool) preg_match('/^[yn]?[ıiuü]$/u', $suffix) => 'accusative',
            (bool) preg_match('/^l[ıiuü]/u', $suffix) => 'origin',
            default => 'other',
        };
    }

    /**
     * Boşluk doldurma soruları üretir.
     *
     * @param  array<int, string>  $sentences
     * @param  array<string, true>  $usedAnswers
     * @return array<int, array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>, answer_key: string}>
     */
    private function buildClozeQuestions(array $sentences, int $needed, array $usedAnswers): array
    {
        $pools = $this->collectPools($sentences);
        $questions = [];

        // Önce yalnızca özel isim ve tarih/sayı hedeflenir; yetmezse düz isimlere
        // düşülür. Şiir/kısa satırlar (70 karakter altı) tamamen elenir.
        foreach ([false, true] as $allowNouns) {
            foreach ($sentences as $sentence) {
                if (count($questions) >= $needed) {
                    break 2;
                }

                if (! $this->isClozeEligible($sentence)) {
                    continue;
                }

                $question = $this->buildClozeFromSentence($sentence, $pools, $usedAnswers, $allowNouns);

                if ($question === null) {
                    continue;
                }

                $questions[] = $question;
                $usedAnswers[$question['answer_key']] = true;
            }
        }

        return $questions;
    }

    /**
     * @param  array{proper: array<int, string>, number: array<int, string>, noun: array<int, string>, proper_cases: array<string, array<string, true>>}  $pools
     * @param  array<string, true>  $usedAnswers
     * @return array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>, answer_key: string}|null
     */
    private function buildClozeFromSentence(string $sentence, array $pools, array $usedAnswers, bool $allowNouns): ?array
    {
        preg_match_all('/[\p{L}\d]+/u', $sentence, $matches);
        $compoundWords = $this->compoundNameWords($sentence);

        foreach ($matches[0] as $index => $token) {
            $category = $this->classifyToken($token, $index, $allowNouns);

            if ($category === null) {
                continue;
            }

            $key = $category === 'number' ? $token : mb_strtolower($token, 'UTF-8');

            if (isset($usedAnswers[$key])) {
                continue;
            }

            // Yalnızca havuza girebilmiş (gerçekten özel isim olan) sözcükler hedeflenir.
            if ($category === 'proper' && (isset($compoundWords[$token]) || ! in_array($token, $pools['proper'], true))) {
                continue;
            }

            $distractors = $this->pickPoolDistractors($category, $token, $pools, $usedAnswers);

            // Cümlede zaten geçen bir değer çeldirici olamaz (ör. "______-1691").
            $distractors = array_values(array_filter(
                $distractors,
                static fn (string $distractor): bool => ! preg_match('/(?<![\p{L}\d])'.preg_quote($distractor, '/').'(?![\p{L}\d])/u', $sentence),
            ));

            if (count($distractors) < 3) {
                continue;
            }

            $pattern = '/(?<![\p{L}\d])'.preg_quote($token, '/').'(?![\p{L}\d])/u';
            $prompt = preg_replace($pattern, '______', $sentence, 1);

            if ($prompt === null || $prompt === $sentence) {
                continue;
            }

            return $this->assembleQuestion(
                prompt: $prompt,
                answerText: $this->presentToken($token, $category),
                distractorTexts: $distractors,
                answerKey: $key,
            );
        }

        return null;
    }

    /**
     * Boşluk doldurma için cümle uygun mu? Şiir/etkinlik/liste satırları elenir.
     */
    private function isClozeEligible(string $sentence): bool
    {
        if (mb_strlen($sentence) < 70) {
            return false;
        }

        if (str_contains($sentence, '•') || str_contains($sentence, '·')) {
            return false;
        }

        if (preg_match('/^\s*[—–-]/u', $sentence)) {
            return false;
        }

        // Tam bir cümle olması için büyük harfle başlamalı.
        if (! preg_match('/^\p{Lu}/u', $sentence)) {
            return false;
        }

        // Parantez/tablo ağırlıklı (liste, okunuş vb.) satırlar soru olmaz.
        if (substr_count($sentence, '(') >= 2) {
            return false;
        }

        // Rakamla başlayan liste/etkinlik satırları (ör. "5 Beden dili ...").
        if (preg_match('/^\d+\s/u', $sentence)) {
            return false;
        }

        return ! $this->isNoisy($sentence);
    }

    /**
     * Bir sözcüğün hangi kategoriye girdiğini belirler.
     *
     * @return 'number'|'proper'|'noun'|null
     */
    private function classifyToken(string $token, int $index, bool $allowNouns): ?string
    {
        if (preg_match('/^\d{3,4}$/u', $token)) {
            return 'number';
        }

        // Roma rakamları (XVII, III) özel isim değildir.
        if ($index > 0 && preg_match('/^\p{Lu}[\p{L}]{2,}$/u', $token) && ! preg_match('/^[IVXLCDM]+$/u', $token)) {
            return 'proper';
        }

        if (! $allowNouns) {
            return null;
        }

        $lower = mb_strtolower($token, 'UTF-8');

        if (mb_strlen($lower) < 7 || $this->isStopword($lower) || $this->looksLikeVerb($lower)) {
            return null;
        }

        return 'noun';
    }

    /**
     * Doğru yanıtla aynı kategoriden çeldiriciler seçer.
     *
     * Özel isimlerde aynı hâl eklerini alan (aynı türden olması muhtemel)
     * isimler, sayılarda ise yakın değerler önceliklidir.
     *
     * @param  'number'|'proper'|'noun'  $category
     * @param  array{proper: array<int, string>, number: array<int, string>, noun: array<int, string>, proper_cases: array<string, array<string, true>>}  $pools
     * @param  array<string, true>  $usedAnswers
     * @return array<int, string>
     */
    private function pickPoolDistractors(string $category, string $answer, array $pools, array $usedAnswers): array
    {
        $answerLower = mb_strtolower($answer, 'UTF-8');
        $targetLength = mb_strlen($answer);
        $candidates = [];

        foreach ($pools[$category] as $candidate) {
            $candidateLower = mb_strtolower($candidate, 'UTF-8');

            if ($candidateLower === $answerLower || isset($usedAnswers[$candidateLower])) {
                continue;
            }

            if ($category === 'noun') {
                $length = mb_strlen($candidate);

                if ($length < $targetLength - 3 || $length > $targetLength + 4) {
                    continue;
                }
            }

            $candidates[$candidateLower] = $candidate;
        }

        $candidates = array_values($candidates);
        shuffle($candidates);

        return match ($category) {
            'proper' => $this->rankProperDistractors($answerLower, $candidates, $pools['proper_cases']),
            'number' => $this->pickNumberDistractors($answer, $candidates),
            default => array_slice($candidates, 0, 3),
        };
    }

    /**
     * Özel isim çeldiricilerini, doğru yanıtla ortak hâl eki sayısına ve
     * uzunluk benzerliğine göre sıralayıp en iyi üçünü döndürür.
     *
     * @param  array<int, string>  $candidates
     * @param  array<string, array<string, true>>  $properCases
     * @return array<int, string>
     */
    private function rankProperDistractors(string $answerLower, array $candidates, array $properCases): array
    {
        $answerCases = $properCases[$answerLower] ?? [];
        $answerLength = mb_strlen($answerLower);

        $scored = array_map(
            static function (string $candidate) use ($answerCases, $answerLength, $properCases): array {
                $candidateCases = $properCases[mb_strtolower($candidate, 'UTF-8')] ?? [];
                $sharedCases = count(array_intersect_key($answerCases, $candidateCases));
                $lengthGap = abs(mb_strlen($candidate) - $answerLength);

                return ['text' => $candidate, 'score' => $sharedCases * 10 - min($lengthGap, 9)];
            },
            $candidates,
        );

        usort($scored, static fn (array $first, array $second): int => $second['score'] <=> $first['score']);

        return array_column(array_slice($scored, 0, 3), 'text');
    }

    /**
     * Sayı/yıl çeldiricileri: metindeki yakın değerler, yoksa yanıta yakın
     * makul değerler üretilir (ör. 1774 için 1767, 1777, 1785).
     *
     * @param  array<int, string>  $candidates
     * @return array<int, string>
     */
    private function pickNumberDistractors(string $answer, array $candidates): array
    {
        $answerValue = (int) $answer;
        $digits = strlen($answer);

        $near = array_values(array_filter(
            $candidates,
            static fn (string $candidate): bool => strlen($candidate) === $digits
                && abs((int) $candidate - $answerValue) <= 60,
        ));

        $distractors = array_slice($near, 0, 3);
        $offsets = self::NUMBER_OFFSETS;
        shuffle($offsets);

        foreach ($offsets as $offset) {
            if (count($distractors) >= 3) {
                break;
            }

            $value = (string) ($answerValue + $offset);

            if (strlen($value) !== $digits || $value === $answer || in_array($value, $distractors, true)) {
                continue;
            }

            $distractors[] = $value;
        }

        return $distractors;
    }

    /**
     * Soruyu şıklarıyla birlikte kurar.
     *
     * @param  array<int, string>  $distractorTexts
     * @return array{text: string, correct_option: string, options: array<int, array{label: string, text: string, is_correct: bool}>, answer_key: string}
     */
    private function assembleQuestion(
        string $prompt,
        string $answerText,
        array $distractorTexts,
        string $answerKey,
    ): array {
        $options = [
            ['text' => $answerText, 'is_correct' => true],
        ];

        foreach ($distractorTexts as $distractor) {
            $options[] = ['text' => $distractor, 'is_correct' => false];
        }

        // Aynı metne sahip şıkları (doğru yanıtla çakışan çeldiriciler dâhil) ele.
        $unique = [];

        foreach ($options as $option) {
            $key = mb_strtolower($option['text'], 'UTF-8');

            if (isset($unique[$key])) {
                continue;
            }

            $unique[$key] = $option;
        }

        $options = array_values($unique);

        shuffle($options);

        $correctOption = self::SELECTION_LABELS[0];
        $prepared = [];

        foreach ($options as $index => $option) {
            $label = self::SELECTION_LABELS[$index];

            if ($option['is_correct']) {
                $correctOption = $label;
            }

            $prepared[] = [
                'label' => $label,
                'text' => $option['text'],
                'is_correct' => $option['is_correct'],
            ];
        }

        return [
            'text' => $prompt,
            'correct_option' => $correctOption,
            'options' => $prepared,
            'answer_key' => $answerKey,
        ];
    }

    /**
     * Tanım gövdesini görüntülemeye uygun cümleye çevirir.
     */
    private function presentSentence(string $text): string
    {
        $text = rtrim(trim($text), '.!?,;:');

        return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($text, 1, null, 'UTF-8')
            .'.';
    }

    private function presentToken(string $token, string $category): string
    {
        if ($category === 'number' || $category === 'proper') {
            return $token;
        }

        return mb_strtoupper(mb_substr($token, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($token, 1, null, 'UTF-8');
    }

    /**
     * @param  array<int, string>  $items
     */
    private function randomFrom(array $items): string
    {
        return $items[array_rand($items)];
    }

    private function isStopword(string $word): bool
    {
        return in_array($word, self::STOPWORDS, true);
    }

    private function looksLikeVerb(string $word): bool
    {
        foreach (self::VERB_LIKE_SUFFIXES as $suffix) {
            if (str_ends_with($word, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cümle; yönerge, künye veya etkinlik metni mi?
     */
    private function isNoisy(string $sentence): bool
    {
        $lower = mb_strtolower($sentence, 'UTF-8');

        foreach (self::NOISE_PATTERNS as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
