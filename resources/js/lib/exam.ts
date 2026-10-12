/**
 * Gerçek AÖL kitapçıklarında olumsuz soru kökleri kalın ve altı çizili
 * basılır ("… hangisi değildir?"). Kökü bu parçalara ayırır.
 */
const NEGATIVE_PATTERN =
    /(değildir|değiller|yoktur|yanlıştır|ulaşılamaz|söylenemez|olamaz|gösterilemez|beklenemez|sayılamaz|bulunmaz|görülmez|dışta kalır|yer almaz|yapılmamıştır|değinilmemiştir|çıkarılamaz|kullanılmamıştır)/giu;

export interface StemPart {
    text: string;
    emphasized: boolean;
}

export function splitNegativeStem(stem: string): StemPart[] {
    return stem
        .split(NEGATIVE_PATTERN)
        .filter((part) => part !== '')
        .map((part) => ({
            text: part,
            emphasized: new RegExp(`^${NEGATIVE_PATTERN.source}$`, 'iu').test(
                part,
            ),
        }));
}

/**
 * Şıkların hepsi kısaysa kitapçıktaki gibi 2×2 dizilir.
 */
export function hasShortOptions(options: { text: string }[]): boolean {
    return options.every((option) => option.text.length <= 28);
}

export function formatDuration(totalSeconds: number): string {
    const safe = Math.max(0, totalSeconds);
    const minutes = Math.floor(safe / 60)
        .toString()
        .padStart(2, '0');
    const seconds = (safe % 60).toString().padStart(2, '0');

    return `${minutes}:${seconds}`;
}

/**
 * Sonuç sayfasındaki veriyi, dışarıya (ör. bir yapay zekâya) aktarılmak üzere
 * düz JSON'a çevirir. Her soruda metin, giriş metni, şıklar, adayın işaretlediği
 * şık ve doğru şık bulunur.
 */
export interface ExamResultQuestion {
    no: number;
    giris_metni: string | null;
    metin: string;
    secenekler: { harf: string; metin: string; dogru: boolean }[];
    isaretlenen: string | null;
    dogru_cevap: string | null;
    sonuc: 'dogru' | 'yanlis' | 'bos';
    kaynak: string;
    kitap_sayfasi: number | null;
}

export interface ExamResultCourse {
    ders: string;
    toplam_soru: number;
    dogru: number;
    yanlis: number;
    bos: number;
    puan: number;
    basarili: boolean;
    sorular: ExamResultQuestion[];
}

export interface ExamResultJson {
    seviye: string;
    havuz: string;
    gecme_notu: number;
    sonuclar: ExamResultCourse[];
}

interface ReviewItemInput {
    number: number;
    passage: string | null;
    question: string;
    isCorrect: boolean;
    selectedLabel: string | null;
    correctLabel: string | null;
    options: { label: string; text: string; isCorrect: boolean }[];
    source: string;
    sourcePage: number | null;
}

interface CourseResultInput {
    course: string;
    total: number;
    correct: number;
    wrong: number;
    blank: number;
    score: number;
    passed: boolean;
    review: ReviewItemInput[];
}

export function buildCourseResultJson(
    result: CourseResultInput,
): ExamResultCourse {
    return {
        ders: result.course,
        toplam_soru: result.total,
        dogru: result.correct,
        yanlis: result.wrong,
        bos: result.blank,
        puan: result.score,
        basarili: result.passed,
        sorular: result.review.map((item) => ({
            no: item.number,
            giris_metni: item.passage,
            metin: item.question,
            secenekler: item.options.map((option) => ({
                harf: option.label,
                metin: option.text,
                dogru: option.isCorrect,
            })),
            isaretlenen: item.selectedLabel,
            dogru_cevap: item.correctLabel,
            sonuc:
                item.selectedLabel === null
                    ? 'bos'
                    : item.isCorrect
                      ? 'dogru'
                      : 'yanlis',
            kaynak: item.source,
            kitap_sayfasi: item.sourcePage,
        })),
    };
}

export function buildExamResultJson(
    exam: { levelLabel: string; pool: string; passScore: number },
    results: CourseResultInput[],
): ExamResultJson {
    return {
        seviye: exam.levelLabel,
        havuz: exam.pool,
        gecme_notu: exam.passScore,
        sonuclar: results.map(buildCourseResultJson),
    };
}

/**
 * Verilen veriyi girintili JSON olarak panoya kopyalar.
 */
export async function copyJson(data: unknown): Promise<boolean> {
    try {
        await navigator.clipboard.writeText(JSON.stringify(data, null, 2));

        return true;
    } catch {
        return false;
    }
}
