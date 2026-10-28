import CourseTable, {
    type CourseFilters,
    type CourseQuery,
    type CoursesPage,
} from '@/components/course-table';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { sync } from '@/routes/admin/catalog';
import { create, index } from '@/routes/admin/courses';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Props {
    courses: CoursesPage;
    filters: CourseFilters;
    levels: { value: string; label: string; count: number }[];
    questionsPerCourse: number;
}

function CoursesIndex({ courses, filters, levels, questionsPerCourse }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [syncing, setSyncing] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const isAnyWorking = courses.data.some(
        (course) => course.isGenerating || course.isExtractingText,
    );
    const { start, stop } = usePoll(
        5000,
        { only: ['courses'] },
        { autoStart: false },
    );

    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);

    useEffect(() => {
        if (isAnyWorking) {
            start();
        } else {
            stop();
        }
    }, [isAnyWorking, start, stop]);

    const visit = (changes: Partial<CourseQuery>) => {
        const query = {
            ...filters,
            page: courses.current_page,
            per_page: courses.per_page,
            ...changes,
        };

        router.get(
            index.url({
                query: { ...query, search: query.search || undefined },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                only: ['courses', 'filters', 'levels'],
                onStart: () => setIsLoading(true),
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const runSync = () => {
        router.post(
            sync.url(),
            { level: filters.level },
            {
                preserveScroll: true,
                onStart: () => setSyncing(true),
                onFinish: () => setSyncing(false),
            },
        );
    };

    return (
        <>
            <Head title="Dersler" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Dersler
                    </h1>
                    <p className="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                        Kitaplar MEB kataloğundan alınır; PDF barındırılmaz,
                        metni çıkarılıp saklanır. Her ders için{' '}
                        {questionsPerCourse} soruluk banka üretilir.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Link
                        href={create.url()}
                        className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        PDF ile ders ekle
                    </Link>
                    <button
                        type="button"
                        onClick={runSync}
                        disabled={syncing || isLoading}
                        className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                    >
                        {syncing ? 'Eşitleniyor...' : 'MEB kataloğunu eşitle'}
                    </button>
                </div>
            </div>

            <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                <div
                    role="group"
                    aria-label="Kademe seçimi"
                    className="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800"
                >
                    {levels.map((level) => (
                        <button
                            key={level.value}
                            type="button"
                            disabled={isLoading}
                            aria-pressed={filters.level === level.value}
                            onClick={() =>
                                visit({ level: level.value, page: 1 })
                            }
                            className={cn(
                                'rounded-lg px-3 py-1.5 text-sm font-medium transition disabled:opacity-60',
                                filters.level === level.value
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-300',
                            )}
                        >
                            {level.label}{' '}
                            <span className="text-slate-400">
                                ({level.count})
                            </span>
                        </button>
                    ))}
                </div>
                <form
                    className="flex flex-wrap gap-2"
                    role="search"
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({ search: search.trim(), page: 1 });
                    }}
                >
                    <label htmlFor="course-search" className="sr-only">
                        Ders adı veya ders grubunda ara
                    </label>
                    <input
                        id="course-search"
                        type="search"
                        maxLength={100}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Ders ara..."
                        className="w-56 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <button
                        type="submit"
                        disabled={isLoading}
                        className="rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold hover:bg-slate-100 disabled:opacity-60 dark:border-slate-700 dark:hover:bg-slate-800"
                    >
                        Ara
                    </button>
                    {filters.search && (
                        <button
                            type="button"
                            disabled={isLoading}
                            onClick={() => visit({ search: '', page: 1 })}
                            className="rounded-xl px-3 py-2 text-sm text-slate-500 hover:text-indigo-600 disabled:opacity-60 dark:text-slate-400"
                        >
                            Temizle
                        </button>
                    )}
                </form>
            </div>
            <CourseTable
                courses={courses}
                filters={filters}
                isLoading={isLoading}
                onChange={visit}
            />
        </>
    );
}

CoursesIndex.layout = (page: React.ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);

export default CoursesIndex;
