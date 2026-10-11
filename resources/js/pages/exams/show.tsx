import { submit as submitExam } from '@/actions/App/Http/Controllers/ExamSessionController';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface QuestionOption {
    id: number;
    label: string;
    text: string;
}

interface Question {
    id: number;
    position: number;
    text: string;
    options: QuestionOption[];
}

interface Session {
    id: string;
    course: string;
    course_slug: string;
    total_questions: number;
    started_at: string | null;
}

interface Props {
    session: Session;
    questions: Question[];
}

function formatTime(totalSeconds: number): string {
    const minutes = Math.floor(totalSeconds / 60)
        .toString()
        .padStart(2, '0');
    const seconds = (totalSeconds % 60).toString().padStart(2, '0');

    return `${minutes}:${seconds}`;
}

function ExamShow({ session, questions }: Props) {
    const [answers, setAnswers] = useState<Record<number, number>>({});
    const [current, setCurrent] = useState(0);
    const [submitting, setSubmitting] = useState(false);
    const [elapsed, setElapsed] = useState(0);

    useEffect(() => {
        const timer = window.setInterval(() => {
            setElapsed((value) => value + 1);
        }, 1000);

        return () => window.clearInterval(timer);
    }, []);

    const question = questions[current];
    const selectedOption = answers[question.id] ?? null;
    const answeredCount = Object.keys(answers).length;
    const progress = Math.round((answeredCount / questions.length) * 100);

    const select = (questionId: number, optionId: number) => {
        setAnswers((previous) => ({ ...previous, [questionId]: optionId }));
    };

    const finish = () => {
        const unanswered = questions.length - answeredCount;

        if (
            unanswered > 0 &&
            !window.confirm(
                `${unanswered} soru boş bırakıldı. Sınavı yine de bitirmek istiyor musunuz?`,
            )
        ) {
            return;
        }

        router.post(
            submitExam.url(session.id),
            { answers },
            {
                onStart: () => setSubmitting(true),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <>
            <Head title={`${session.course} - Sınav`} />

            <div className="mb-6 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-900">
                <div>
                    <p className="text-xs font-medium tracking-wide text-indigo-600 uppercase dark:text-indigo-400">
                        {session.course}
                    </p>
                    <h1 className="mt-1 text-xl font-bold tracking-tight">
                        Soru {current + 1}{' '}
                        <span className="text-slate-400 dark:text-slate-500">
                            / {questions.length}
                        </span>
                    </h1>
                </div>

                <div className="flex items-center gap-6">
                    <div className="text-center">
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Süre
                        </p>
                        <p className="font-mono text-lg font-semibold tabular-nums">
                            {formatTime(elapsed)}
                        </p>
                    </div>
                    <div className="text-center">
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Cevaplanan
                        </p>
                        <p className="text-lg font-semibold">
                            {answeredCount}
                            <span className="text-slate-400 dark:text-slate-500">
                                /{questions.length}
                            </span>
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={finish}
                        disabled={submitting}
                        className="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {submitting ? 'Gönderiliyor...' : 'Sınavı Bitir'}
                    </button>
                </div>
            </div>

            <div className="mb-8 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div
                    className="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all duration-300"
                    style={{ width: `${progress}%` }}
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-slate-800 dark:bg-slate-900">
                    <p className="text-sm leading-relaxed font-medium whitespace-pre-line sm:text-base">
                        {question.text}
                    </p>

                    <div className="mt-6 flex flex-col gap-3">
                        {question.options.map((option) => {
                            const isSelected = selectedOption === option.id;

                            return (
                                <button
                                    key={option.id}
                                    type="button"
                                    onClick={() =>
                                        select(question.id, option.id)
                                    }
                                    className={`flex items-start gap-4 rounded-xl border p-4 text-left transition ${
                                        isSelected
                                            ? 'border-indigo-500 bg-indigo-50 ring-1 ring-indigo-500 dark:border-indigo-400 dark:bg-indigo-950/40'
                                            : 'border-slate-200 hover:border-indigo-300 hover:bg-slate-50 dark:border-slate-700 dark:hover:border-indigo-600 dark:hover:bg-slate-800/60'
                                    }`}
                                >
                                    <span
                                        className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-bold ${
                                            isSelected
                                                ? 'bg-indigo-600 text-white'
                                                : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300'
                                        }`}
                                    >
                                        {option.label}
                                    </span>
                                    <span className="pt-1 text-sm text-slate-700 dark:text-slate-200">
                                        {option.text}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <div className="mt-8 flex items-center justify-between">
                        <button
                            type="button"
                            onClick={() => setCurrent((index) => index - 1)}
                            disabled={current === 0}
                            className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            ← Önceki
                        </button>
                        {current === questions.length - 1 ? (
                            <button
                                type="button"
                                onClick={finish}
                                disabled={submitting}
                                className="rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:opacity-60"
                            >
                                Sınavı Bitir
                            </button>
                        ) : (
                            <button
                                type="button"
                                onClick={() => setCurrent((index) => index + 1)}
                                className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                            >
                                Sonraki →
                            </button>
                        )}
                    </div>
                </section>

                <aside className="h-fit rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h2 className="text-sm font-semibold">Soru Haritası</h2>
                    <div className="mt-4 grid grid-cols-5 gap-2">
                        {questions.map((item, index) => {
                            const isAnswered = answers[item.id] !== undefined;
                            const isCurrent = index === current;

                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => setCurrent(index)}
                                    className={`flex h-9 items-center justify-center rounded-lg text-sm font-semibold transition ${
                                        isCurrent
                                            ? 'ring-2 ring-indigo-500 ring-offset-2 dark:ring-offset-slate-900'
                                            : ''
                                    } ${
                                        isAnswered
                                            ? 'bg-emerald-500 text-white'
                                            : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                                    }`}
                                >
                                    {index + 1}
                                </button>
                            );
                        })}
                    </div>

                    <div className="mt-5 flex flex-col gap-2 text-xs text-slate-500 dark:text-slate-400">
                        <span className="flex items-center gap-2">
                            <span className="h-3 w-3 rounded bg-emerald-500" />
                            Cevaplandı
                        </span>
                        <span className="flex items-center gap-2">
                            <span className="h-3 w-3 rounded bg-slate-200 dark:bg-slate-700" />
                            Boş
                        </span>
                    </div>
                </aside>
            </div>
        </>
    );
}

ExamShow.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;

export default ExamShow;
