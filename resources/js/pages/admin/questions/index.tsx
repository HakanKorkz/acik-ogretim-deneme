import QuestionStem from '@/components/exam/question-stem';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { index as coursesIndex } from '@/routes/admin/courses';
import { edit, index } from '@/routes/admin/courses/questions';
import { index as textIndex } from '@/routes/admin/courses/text';
import { download } from '@/routes/admin/questions/export';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export interface QuestionRow {
    id: number;
    passage: string | null;
    text: string;
    correctOption: string;
    source: string;
    sourcePage: number | null;
    sourcePdfPage: number | null;
    sourceUrl: string | null;
    pastExamTitle: string | null;
    pastExamNumber: number | null;
    officialAnswer: string | null;
    options: { label: string; text: string; isCorrect: boolean }[];
}

interface Props {
    course: { slug: string; name: string; level: string };
    filters: { search: string; source: string | null };
    sources: { value: string; label: string }[];
    questions: {
        data: QuestionRow[];
        total: number;
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
    };
}

const buttonClass =
    'rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-100 disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800';

function Questions({ course, filters, sources, questions }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    const visit = (changes: {
        search?: string;
        source?: string;
        page?: number;
    }) => {
        router.get(
            index.url(course.slug, { query: { ...filters, ...changes } }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['questions', 'filters'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );
    };

    return (
        <>
            <Head title={`${course.name} · Sorular`} />
            <Link
                href={coursesIndex.url({ query: { level: course.level } })}
                className="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
            >
                ← Dersler
            </Link>
            <div className="mt-3 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">
                        {course.name} · Sorular
                    </h1>
                    <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Soru kökünü, öncülleri, şıkları ve doğru cevabı
                        inceleyin. Değişiklik yapmak için sorunun Düzenle
                        bağlantısını açın.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Link
                        href={textIndex.url(course.slug)}
                        className={buttonClass}
                    >
                        Kitap metni
                    </Link>
                    <a
                        href={download.url({ query: { course: course.slug } })}
                        className={buttonClass}
                    >
                        Excel indir
                    </a>
                </div>
            </div>
            <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                <label className="flex items-center gap-2 text-sm">
                    Kaynak
                    <select
                        value={filters.source ?? ''}
                        disabled={loading}
                        onChange={(event) =>
                            visit({ source: event.target.value, page: 1 })
                        }
                        className="rounded-xl border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="">Tüm kaynaklar</option>
                        {sources.map((source) => (
                            <option key={source.value} value={source.value}>
                                {source.label}
                            </option>
                        ))}
                    </select>
                </label>
                <form
                    role="search"
                    className="flex gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({ search: search.trim(), page: 1 });
                    }}
                >
                    <label htmlFor="question-search" className="sr-only">
                        Soru veya giriş metninde ara
                    </label>
                    <input
                        id="question-search"
                        type="search"
                        maxLength={100}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Soru metninde ara…"
                        className="w-56 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-900"
                    />
                    <button
                        type="submit"
                        disabled={loading}
                        className={buttonClass}
                    >
                        Ara
                    </button>
                </form>
            </div>
            <p
                role="status"
                className="mt-4 text-sm text-slate-500 dark:text-slate-400"
            >
                {loading
                    ? 'Sorular yükleniyor…'
                    : `${questions.from ?? 0}–${questions.to ?? 0} / ${questions.total} soru`}
            </p>
            <div aria-busy={loading} className="mt-4 space-y-5">
                {questions.data.length === 0 && (
                    <p className="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-slate-500 dark:border-slate-700">
                        Bu seçime uygun soru bulunamadı.
                    </p>
                )}
                {questions.data.map((question, position) => (
                    <article
                        key={question.id}
                        className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <header className="mb-5 flex flex-wrap items-center justify-between gap-3">
                            <div className="flex flex-wrap gap-2 text-xs">
                                <span className="rounded-full bg-slate-100 px-2.5 py-1 dark:bg-slate-800">
                                    #{question.id}
                                </span>
                                <span className="rounded-full bg-indigo-50 px-2.5 py-1 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                                    {question.source}
                                </span>
                                {question.sourcePage !== null && (
                                    <span className="px-2 py-1">
                                        Kaynak: s. {question.sourcePage}
                                    </span>
                                )}
                            </div>
                            <Link
                                href={edit.url([course.slug, question.id])}
                                className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700"
                            >
                                Düzenle
                            </Link>
                        </header>
                        <QuestionStem
                            number={(questions.from ?? 1) + position}
                            passage={question.passage}
                            text={question.text}
                        />
                        <ul className="mt-5 grid gap-2 sm:grid-cols-2">
                            {question.options.map((option) => (
                                <li
                                    key={option.label}
                                    className={cn(
                                        'rounded-xl border px-4 py-3 text-sm whitespace-pre-wrap',
                                        option.label === question.correctOption
                                            ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100'
                                            : 'border-slate-200 dark:border-slate-700',
                                    )}
                                >
                                    <strong>{option.label})</strong>{' '}
                                    {option.text}
                                    {option.label ===
                                        question.correctOption && (
                                        <span className="ml-2 text-xs font-semibold">
                                            ✓ Doğru cevap
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                        <footer className="mt-4 flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400">
                            <span>
                                Doğru cevap:{' '}
                                <strong>{question.correctOption}</strong>
                            </span>
                            {question.pastExamTitle && (
                                <span>
                                    {question.pastExamTitle} · Kitapçık sorusu{' '}
                                    {question.pastExamNumber}
                                </span>
                            )}
                            {question.sourceUrl && (
                                <a
                                    href={
                                        question.sourcePdfPage !== null
                                            ? `${question.sourceUrl.split('#')[0]}#page=${question.sourcePdfPage}`
                                            : question.sourceUrl
                                    }
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    Kaynağı aç ↗
                                </a>
                            )}
                        </footer>
                    </article>
                ))}
            </div>
            <nav
                aria-label="Soru sayfaları"
                className="mt-6 flex items-center justify-end gap-3"
            >
                <button
                    type="button"
                    disabled={loading || questions.current_page <= 1}
                    onClick={() => visit({ page: questions.current_page - 1 })}
                    className={buttonClass}
                >
                    Önceki
                </button>
                <span className="text-sm">
                    {questions.current_page} / {questions.last_page}
                </span>
                <button
                    type="button"
                    disabled={
                        loading || questions.current_page >= questions.last_page
                    }
                    onClick={() => visit({ page: questions.current_page + 1 })}
                    className={buttonClass}
                >
                    Sonraki
                </button>
            </nav>
        </>
    );
}

Questions.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
export default Questions;
