import AdminLayout from '@/layouts/admin-layout';
import { index as coursesIndex } from '@/routes/admin/courses';
import { index as questionsIndex } from '@/routes/admin/courses/questions';
import { index } from '@/routes/admin/courses/text';
import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Props {
    course: {
        slug: string;
        name: string;
        level: string;
        sourceUrl: string | null;
    };
    filters: { scope: string; search: string };
    pages: {
        data: { pdfPage: number; printedPage: number; content: string }[];
        total: number;
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
    };
}

const buttonClass =
    'rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-100 disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800';

function CourseText({ course, filters, pages }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [loading, setLoading] = useState(false);
    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);
    const visit = (changes: {
        scope?: string;
        search?: string;
        page?: number;
    }) =>
        router.get(
            index.url(course.slug, { query: { ...filters, ...changes } }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['pages', 'filters'],
                onStart: () => setLoading(true),
                onFinish: () => setLoading(false),
            },
        );

    return (
        <>
            <Head title={`${course.name} · Kitap metni`} />
            <Link
                href={coursesIndex.url({ query: { level: course.level } })}
                className="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
            >
                ← Dersler
            </Link>
            <div className="mt-3 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold">
                        {course.name} · Kitap metni
                    </h1>
                    <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        Veritabanında saklanan metin. Sayfa numaralarını ve soru
                        üretiminde kullanılan içeriği kontrol edin.
                    </p>
                </div>
                <Link
                    href={questionsIndex.url(course.slug)}
                    className={buttonClass}
                >
                    Soruları görüntüle
                </Link>
            </div>
            <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                <label className="flex items-center gap-2 text-sm">
                    Kapsam
                    <select
                        value={filters.scope}
                        disabled={loading}
                        onChange={(event) =>
                            visit({ scope: event.target.value, page: 1 })
                        }
                        className="rounded-xl border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="responsible">
                            Dersin sorumlu olduğu sayfalar
                        </option>
                        <option value="all">Kitabın tüm sayfaları</option>
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
                    <label htmlFor="book-text-search" className="sr-only">
                        Kitap metninde ara
                    </label>
                    <input
                        id="book-text-search"
                        type="search"
                        maxLength={100}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Metinde ara…"
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
                    ? 'Metin yükleniyor…'
                    : `${pages.from ?? 0}–${pages.to ?? 0} / ${pages.total} kitap sayfası`}
            </p>
            <div aria-busy={loading} className="mt-4 space-y-5">
                {pages.data.length === 0 && (
                    <p className="rounded-2xl border border-dashed border-slate-300 p-8 text-center text-slate-500 dark:border-slate-700">
                        Bu kapsam veya arama için metin bulunamadı. Metin henüz
                        çıkarılmadıysa Dersler sayfasından metni hazırlayın.
                    </p>
                )}
                {pages.data.map((page) => (
                    <article
                        key={page.pdfPage}
                        className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <header className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <h2 className="font-semibold">
                                {page.printedPage > 0
                                    ? `Basılı sayfa ${page.printedPage}`
                                    : 'Ön sayfa'}{' '}
                                <span className="font-normal text-slate-500">
                                    · PDF sayfası {page.pdfPage}
                                </span>
                            </h2>
                            {course.sourceUrl && (
                                <a
                                    href={`${course.sourceUrl.split('#')[0]}#page=${page.pdfPage}`}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    PDF’te aç ↗
                                </a>
                            )}
                        </header>
                        <div className="text-sm leading-7 break-words whitespace-pre-wrap text-slate-700 dark:text-slate-300">
                            {page.content}
                        </div>
                    </article>
                ))}
            </div>
            <nav
                aria-label="Kitap metni sayfaları"
                className="mt-6 flex items-center justify-end gap-3"
            >
                <button
                    type="button"
                    disabled={loading || pages.current_page <= 1}
                    onClick={() => visit({ page: pages.current_page - 1 })}
                    className={buttonClass}
                >
                    Önceki
                </button>
                <span className="text-sm">
                    {pages.current_page} / {pages.last_page}
                </span>
                <button
                    type="button"
                    disabled={loading || pages.current_page >= pages.last_page}
                    onClick={() => visit({ page: pages.current_page + 1 })}
                    className={buttonClass}
                >
                    Sonraki
                </button>
            </nav>
        </>
    );
}

CourseText.layout = (page: React.ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);
export default CourseText;
