import { store as startExam } from '@/actions/App/Http/Controllers/ExamSessionController';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface ReviewOption {
    label: string;
    text: string;
    is_correct: boolean;
}

interface ReviewItem {
    position: number;
    question: string;
    is_correct: boolean;
    selected_label: string | null;
    selected_text: string | null;
    correct_label: string | null;
    correct_text: string | null;
    options: ReviewOption[];
}

interface ResultSession {
    id: string;
    course: string;
    course_slug: string;
    total_questions: number;
    correct_count: number;
    wrong_count: number;
    blank_count: number;
    score: number;
    completed_at: string | null;
}

interface Props {
    session: ResultSession;
    review: ReviewItem[];
}

/**
 * Sonucu, daha sonra bir yapay zekâya verilebilecek yapılandırılmış bir
 * JSON nesnesine dönüştürür.
 */
function buildResultJson(session: ResultSession, review: ReviewItem[]) {
    return {
        ders: session.course,
        ozet: {
            toplam_soru: session.total_questions,
            dogru: session.correct_count,
            yanlis: session.wrong_count,
            bos: session.blank_count,
            puan: session.score,
            tamamlandi: session.completed_at,
        },
        sorular: review.map((item) => ({
            sira: item.position,
            soru: item.question,
            durum: item.is_correct
                ? 'dogru'
                : item.selected_label === null
                  ? 'bos'
                  : 'yanlis',
            senin_cevabin:
                item.selected_label === null
                    ? null
                    : { harf: item.selected_label, metin: item.selected_text },
            dogru_cevap:
                item.correct_label === null
                    ? null
                    : { harf: item.correct_label, metin: item.correct_text },
            secenekler: item.options.map((option) => ({
                harf: option.label,
                metin: option.text,
                dogru: option.is_correct,
            })),
        })),
    };
}

function scoreTheme(score: number): {
    ring: string;
    text: string;
    badge: string;
    label: string;
} {
    if (score >= 85) {
        return {
            ring: 'text-emerald-500',
            text: 'text-emerald-600 dark:text-emerald-400',
            badge: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
            label: 'Harika!',
        };
    }

    if (score >= 50) {
        return {
            ring: 'text-amber-500',
            text: 'text-amber-600 dark:text-amber-400',
            badge: 'bg-amber-50 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
            label: 'İyi, biraz daha çalışabilirsin.',
        };
    }

    return {
        ring: 'text-rose-500',
        text: 'text-rose-600 dark:text-rose-400',
        badge: 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300',
        label: 'Konuları tekrar etmekte fayda var.',
    };
}

function ScoreRing({ score, ringClass }: { score: number; ringClass: string }) {
    const radius = 64;
    const circumference = 2 * Math.PI * radius;
    const offset = circumference * (1 - score / 100);

    return (
        <div className="relative h-40 w-40 shrink-0">
            <svg viewBox="0 0 160 160" className="h-full w-full -rotate-90">
                <circle
                    cx="80"
                    cy="80"
                    r={radius}
                    fill="none"
                    strokeWidth="12"
                    className="stroke-slate-200 dark:stroke-slate-800"
                />
                <circle
                    cx="80"
                    cy="80"
                    r={radius}
                    fill="none"
                    strokeWidth="12"
                    strokeLinecap="round"
                    strokeDasharray={circumference}
                    strokeDashoffset={offset}
                    className={`${ringClass} stroke-current transition-[stroke-dashoffset] duration-700`}
                />
            </svg>
            <div className="absolute inset-0 flex flex-col items-center justify-center">
                <span className="text-4xl font-bold tabular-nums">{score}</span>
                <span className="text-xs font-medium text-slate-500 dark:text-slate-400">
                    puan
                </span>
            </div>
        </div>
    );
}

function ResultStat({
    label,
    value,
    className,
}: {
    label: string;
    value: number;
    className: string;
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 text-center dark:border-slate-800 dark:bg-slate-900">
            <p className={`text-2xl font-bold ${className}`}>{value}</p>
            <p className="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                {label}
            </p>
        </div>
    );
}

function ExamResult({ session, review }: Props) {
    const [retrying, setRetrying] = useState(false);
    const [copied, setCopied] = useState(false);
    const theme = scoreTheme(session.score);

    const retry = () => {
        setRetrying(true);
        router.post(
            startExam.url(session.course_slug),
            {},
            { onFinish: () => setRetrying(false) },
        );
    };

    const copyJson = async () => {
        const json = JSON.stringify(buildResultJson(session, review), null, 2);

        const markCopied = () => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        };

        try {
            if (navigator.clipboard?.writeText) {
                await navigator.clipboard.writeText(json);
            } else {
                throw new Error('Clipboard API yok');
            }

            markCopied();
        } catch {
            // Eski tarayıcı / güvensiz bağlam için yedek yöntem.
            const textarea = document.createElement('textarea');
            textarea.value = json;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                document.execCommand('copy');
                markCopied();
            } catch {
                // Kopyalama başarısız; sessizce yut.
            }

            document.body.removeChild(textarea);
        }
    };

    return (
        <>
            <Head title={`${session.course} - Sonuç`} />

            <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                <div className="flex flex-col items-center gap-8 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-col items-center gap-6 sm:flex-row">
                        <ScoreRing
                            score={session.score}
                            ringClass={theme.ring}
                        />
                        <div className="text-center sm:text-left">
                            <p className="text-xs font-medium tracking-wide text-indigo-600 uppercase dark:text-indigo-400">
                                {session.course}
                            </p>
                            <h1 className="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">
                                Sınav Tamamlandı
                            </h1>
                            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                {session.total_questions} sorudan{' '}
                                {session.correct_count} doğru cevap.
                            </p>
                            <span
                                className={`mt-3 inline-block rounded-full px-3 py-1 text-xs font-semibold ${theme.badge}`}
                            >
                                {theme.label}
                            </span>
                        </div>
                    </div>

                    <div className="flex flex-col gap-2 sm:w-48">
                        <button
                            type="button"
                            onClick={retry}
                            disabled={retrying}
                            className="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                        >
                            {retrying ? 'Başlatılıyor...' : 'Tekrar Dene'}
                        </button>
                        <Link
                            href="/"
                            className="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Derslere Dön
                        </Link>
                        <button
                            type="button"
                            onClick={copyJson}
                            className={`inline-flex items-center justify-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold transition ${
                                copied
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'
                            }`}
                        >
                            {copied ? (
                                <>
                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        aria-hidden="true"
                                    >
                                        <path d="M20 6 9 17l-5-5" />
                                    </svg>
                                    Kopyalandı!
                                </>
                            ) : (
                                <>
                                    <svg
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        aria-hidden="true"
                                    >
                                        <rect
                                            x="9"
                                            y="9"
                                            width="13"
                                            height="13"
                                            rx="2"
                                        />
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" />
                                    </svg>
                                    JSON Kopyala
                                </>
                            )}
                        </button>
                    </div>
                </div>

                <div className="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <ResultStat
                        label="Doğru"
                        value={session.correct_count}
                        className="text-emerald-600 dark:text-emerald-400"
                    />
                    <ResultStat
                        label="Yanlış"
                        value={session.wrong_count}
                        className="text-rose-600 dark:text-rose-400"
                    />
                    <ResultStat
                        label="Boş"
                        value={session.blank_count}
                        className="text-slate-500 dark:text-slate-400"
                    />
                    <ResultStat
                        label="Toplam"
                        value={session.total_questions}
                        className="text-indigo-600 dark:text-indigo-400"
                    />
                </div>
            </section>

            <h2 className="mt-10 mb-4 text-lg font-semibold">
                Soru Bazlı Değerlendirme
            </h2>

            <div className="flex flex-col gap-4">
                {review.map((item) => (
                    <article
                        key={item.position}
                        className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div className="flex items-start justify-between gap-4">
                            <p className="text-sm leading-relaxed font-medium whitespace-pre-line sm:text-base">
                                <span className="mr-2 text-slate-400 dark:text-slate-500">
                                    {item.position}.
                                </span>
                                {item.question}
                            </p>
                            <span
                                className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ${
                                    item.is_correct
                                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300'
                                        : item.selected_label === null
                                          ? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                          : 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300'
                                }`}
                            >
                                {item.is_correct
                                    ? 'Doğru'
                                    : item.selected_label === null
                                      ? 'Boş'
                                      : 'Yanlış'}
                            </span>
                        </div>

                        <div className="mt-4 grid gap-2">
                            {item.options.map((option) => {
                                const isCorrect = option.is_correct;
                                const isSelected =
                                    option.label === item.selected_label;
                                const isWrongSelection =
                                    isSelected && !isCorrect;

                                return (
                                    <div
                                        key={option.label}
                                        className={`flex items-start gap-3 rounded-xl border p-3 text-sm ${
                                            isCorrect
                                                ? 'border-emerald-300 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30'
                                                : isWrongSelection
                                                  ? 'border-rose-300 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/30'
                                                  : 'border-slate-200 dark:border-slate-800'
                                        }`}
                                    >
                                        <span
                                            className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-xs font-bold ${
                                                isCorrect
                                                    ? 'bg-emerald-500 text-white'
                                                    : isWrongSelection
                                                      ? 'bg-rose-500 text-white'
                                                      : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                            }`}
                                        >
                                            {option.label}
                                        </span>
                                        <span className="pt-0.5 text-slate-700 dark:text-slate-200">
                                            {option.text}
                                            {isCorrect && (
                                                <span className="ml-2 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                                    ✓ doğru cevap
                                                </span>
                                            )}
                                            {isWrongSelection && (
                                                <span className="ml-2 text-xs font-semibold text-rose-600 dark:text-rose-400">
                                                    ✗ sizin cevabınız
                                                </span>
                                            )}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    </article>
                ))}
            </div>
        </>
    );
}

ExamResult.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;

export default ExamResult;
