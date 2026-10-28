import QuestionStem from '@/components/exam/question-stem';
import SectionProgress, {
    type ExamSummary,
} from '@/components/exam/section-progress';
import AppLayout from '@/layouts/app-layout';
import { formatDuration, hasShortOptions } from '@/lib/exam';
import { cn } from '@/lib/utils';
import { answer as saveAnswerRoute, finish } from '@/routes/exams';
import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface QuestionOption {
    id: number;
    label: string;
    text: string;
}

interface Question {
    id: number;
    number: number;
    passage: string | null;
    text: string;
    options: QuestionOption[];
    selectedOptionId: number | null;
}

interface Props {
    exam: ExamSummary;
    section: {
        position: number;
        course: string;
        subject: string | null;
        totalQuestions: number;
        remainingSeconds: number;
    };
    questions: Question[];
}

type SaveState = 'idle' | 'saving' | 'saved' | 'error';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function ExamShow({ exam, section, questions }: Props) {
    const [answers, setAnswers] = useState<Record<number, number | null>>(() =>
        Object.fromEntries(
            questions.map((question) => [
                question.id,
                question.selectedOptionId,
            ]),
        ),
    );
    const [remaining, setRemaining] = useState(section.remainingSeconds);
    const [saveState, setSaveState] = useState<SaveState>('idle');
    const [submitting, setSubmitting] = useState(false);
    const submittedRef = useRef(false);

    const answeredCount = Object.values(answers).filter(
        (value) => value !== null,
    ).length;

    const submit = useCallback(() => {
        if (submittedRef.current) {
            return;
        }

        submittedRef.current = true;
        router.post(
            finish.url(exam.id),
            { answers },
            {
                onStart: () => setSubmitting(true),
                onFinish: () => {
                    setSubmitting(false);
                    submittedRef.current = false;
                },
            },
        );
    }, [answers, exam.id]);

    useEffect(() => {
        const timer = window.setInterval(
            () => setRemaining((value) => Math.max(0, value - 1)),
            1000,
        );

        return () => window.clearInterval(timer);
    }, []);

    useEffect(() => {
        if (remaining === 0) {
            submit();
        }
    }, [remaining, submit]);

    const choose = (questionId: number, optionId: number) => {
        const next = answers[questionId] === optionId ? null : optionId;

        setAnswers((current) => ({ ...current, [questionId]: next }));
        setSaveState('saving');

        fetch(saveAnswerRoute.url(exam.id), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: JSON.stringify({ question_id: questionId, option_id: next }),
        })
            .then((response) => setSaveState(response.ok ? 'saved' : 'error'))
            .catch(() => setSaveState('error'));
    };

    const finishSection = () => {
        const blank = questions.length - answeredCount;

        if (
            blank > 0 &&
            !window.confirm(
                `${blank} soru boş. ${section.course} dersini yine de teslim etmek istiyor musunuz?`,
            )
        ) {
            return;
        }

        submit();
    };

    const isLow = remaining <= 120;

    return (
        <>
            <Head title={`${section.course} - Sınav`} />

            <div className="sticky top-16 z-20 -mx-4 mb-6 border-b border-study-rule bg-study-paper px-4 py-3 sm:-mx-6 sm:px-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p className="text-xs font-medium tracking-wide text-study-accent uppercase">
                            {exam.sections.length > 1
                                ? `${section.position}. ders / ${exam.sections.length}`
                                : exam.levelLabel}
                        </p>
                        <h1 className="font-display text-2xl tracking-tight">
                            {section.course}
                        </h1>
                    </div>
                    <div className="flex items-center gap-5">
                        <div className="text-center">
                            <p className="text-[11px] text-study-muted">
                                Kalan süre
                            </p>
                            <p
                                className={cn(
                                    'font-mono text-xl font-bold tabular-nums',
                                    isLow && 'animate-pulse text-rose-600',
                                )}
                            >
                                {formatDuration(remaining)}
                            </p>
                        </div>
                        <div className="text-center">
                            <p className="text-[11px] text-study-muted">
                                Cevaplanan
                            </p>
                            <p className="text-xl font-bold">
                                {answeredCount}
                                <span className="text-study-muted">
                                    /{questions.length}
                                </span>
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={finishSection}
                            disabled={submitting}
                            className="rounded-sm bg-study-fill px-4 py-2.5 text-sm font-semibold text-study-on-fill transition hover:opacity-90 disabled:opacity-60"
                        >
                            {submitting
                                ? 'Teslim ediliyor...'
                                : 'Dersi teslim et'}
                        </button>
                    </div>
                </div>
            </div>

            <SectionProgress exam={exam} currentPosition={section.position} />

            <div className="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_260px]">
                <section className="rounded-sm border border-study-rule bg-study-surface p-5 sm:p-8">
                    <h2 className="mb-6 border-b-2 border-study-rule pb-2 text-sm font-bold tracking-wide uppercase">
                        {section.course}
                    </h2>

                    <div className="gap-10 2xl:columns-2">
                        {questions.map((question) => {
                            const isShort = hasShortOptions(question.options);

                            return (
                                <article
                                    key={question.id}
                                    id={`soru-${question.number}`}
                                    className="mb-10 scroll-mt-48 break-inside-avoid border-b border-study-rule pb-8 last:border-0"
                                >
                                    <QuestionStem
                                        number={question.number}
                                        passage={question.passage}
                                        text={question.text}
                                    />

                                    <div
                                        className={cn(
                                            'mt-3 grid gap-2 pl-5',
                                            isShort && 'grid-cols-2',
                                        )}
                                    >
                                        {question.options.map((option) => {
                                            const isSelected =
                                                answers[question.id] ===
                                                option.id;

                                            return (
                                                <button
                                                    key={option.id}
                                                    type="button"
                                                    onClick={() =>
                                                        choose(
                                                            question.id,
                                                            option.id,
                                                        )
                                                    }
                                                    aria-pressed={isSelected}
                                                    className={cn(
                                                        'flex min-h-11 items-start gap-2 rounded-sm border px-3 py-2 text-left text-[15px] transition',
                                                        isSelected
                                                            ? 'border-study-fill bg-study-tint font-medium'
                                                            : 'border-transparent hover:border-study-muted hover:bg-study-tint',
                                                    )}
                                                >
                                                    <span className="font-bold">
                                                        {option.label})
                                                    </span>
                                                    <span>{option.text}</span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </article>
                            );
                        })}
                    </div>
                </section>

                <aside className="hidden lg:block">
                    <div className="sticky top-40 rounded-sm border border-study-rule bg-study-surface p-4">
                        <p className="text-center text-xs font-bold tracking-wide uppercase">
                            Cevap kâğıdı
                        </p>
                        <p className="mb-3 truncate text-center text-[11px] text-study-muted">
                            {section.course}
                        </p>
                        <div className="grid gap-1.5">
                            {questions.map((question) => (
                                <div
                                    key={question.id}
                                    className="flex items-center gap-2"
                                >
                                    <a
                                        href={`#soru-${question.number}`}
                                        className="w-5 text-right text-xs font-semibold tabular-nums"
                                    >
                                        {question.number}
                                    </a>
                                    <div className="flex gap-1">
                                        {question.options.map((option) => {
                                            const isSelected =
                                                answers[question.id] ===
                                                option.id;

                                            return (
                                                <button
                                                    key={option.id}
                                                    type="button"
                                                    onClick={() =>
                                                        choose(
                                                            question.id,
                                                            option.id,
                                                        )
                                                    }
                                                    aria-label={`${question.number}. soru ${option.label} şıkkı`}
                                                    className={cn(
                                                        'flex h-11 w-11 items-center justify-center rounded-full border text-xs font-bold transition',
                                                        isSelected
                                                            ? 'border-study-fill bg-study-fill text-study-on-fill'
                                                            : 'border-study-muted text-study-muted hover:border-study-ink',
                                                    )}
                                                >
                                                    {option.label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                        <p className="mt-3 text-center text-[11px] text-study-muted">
                            {saveState === 'saving' && 'Kaydediliyor...'}
                            {saveState === 'saved' && 'Cevaplar kaydedildi'}
                            {saveState === 'error' && (
                                <span className="text-rose-600">
                                    Kaydedilemedi, teslimde gönderilecek
                                </span>
                            )}
                        </p>
                    </div>
                </aside>
            </div>
        </>
    );
}

ExamShow.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;

export default ExamShow;
