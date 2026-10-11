import { store as startExam } from '@/actions/App/Http/Controllers/ExamSessionController';
import FlashMessage from '@/components/flash-message';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { index as coursesIndex } from '@/routes/courses';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface CourseItem {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    questions_count: number;
    exam_sessions_count: number;
}

interface Stats {
    courses: number;
    questions: number;
    sessions: number;
    completed: number;
}

interface Props {
    courses: CourseItem[];
    stats: Stats;
    questionsPerExam: number;
    level: string;
    levels: { value: string; label: string }[];
}

function StatCard({
    label,
    value,
    accent,
}: {
    label: string;
    value: number;
    accent: string;
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                {label}
            </p>
            <p className={`mt-2 text-3xl font-bold ${accent}`}>{value}</p>
        </div>
    );
}

function CoursesIndex({
    courses,
    stats,
    questionsPerExam,
    level,
    levels,
}: Props) {
    const [busy, setBusy] = useState<string | null>(null);

    const start = (course: CourseItem) => {
        setBusy(course.slug);
        router.post(
            startExam.url(course.slug),
            {},
            { onFinish: () => setBusy(null) },
        );
    };

    return (
        <>
            <Head title="Dersler" />

            <section className="overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-8 text-white shadow-xl shadow-indigo-500/20 sm:p-10">
                <p className="text-sm font-medium text-white/80">
                    Online Test ve Sınav Otomasyon Sistemi
                </p>
                <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">
                    Bugün hangi dersi test edelim?
                </h1>
                <p className="mt-3 max-w-2xl text-sm text-white/80 sm:text-base">
                    Ders kitaplarından otomatik üretilen çoktan seçmeli
                    sorularla kendini dene. Her sınav, soru bankasından rastgele
                    seçilen {questionsPerExam} sorudan oluşur.
                </p>
            </section>

            <div className="mt-8">
                <FlashMessage />
                <div className="mb-6 flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                    {levels.map((item) => (
                        <Link
                            key={item.value}
                            href={coursesIndex.url({
                                query: { level: item.value },
                            })}
                            preserveScroll
                            className={cn(
                                'flex-1 rounded-lg px-3 py-2 text-center text-sm font-medium transition',
                                level === item.value
                                    ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white'
                                    : 'text-slate-600 hover:text-slate-900 dark:text-slate-300',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard
                    label="Ders"
                    value={stats.courses}
                    accent="text-indigo-600 dark:text-indigo-400"
                />
                <StatCard
                    label="Soru"
                    value={stats.questions}
                    accent="text-violet-600 dark:text-violet-400"
                />
                <StatCard
                    label="Oturum"
                    value={stats.sessions}
                    accent="text-sky-600 dark:text-sky-400"
                />
                <StatCard
                    label="Tamamlanan"
                    value={stats.completed}
                    accent="text-emerald-600 dark:text-emerald-400"
                />
            </div>

            <div className="mt-10 flex items-center justify-between">
                <h2 className="text-lg font-semibold">Dersler</h2>
                <span className="text-sm text-slate-500 dark:text-slate-400">
                    {courses.length} ders
                </span>
            </div>

            {courses.length === 0 ? (
                <div className="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                    Henüz ders bulunmuyor. PDF dosyalarını{' '}
                    <code className="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800">
                        public/dersler
                    </code>{' '}
                    dizinine ekleyip{' '}
                    <code className="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-800">
                        php artisan courses:scan
                    </code>{' '}
                    komutunu çalıştırın.
                </div>
            ) : (
                <div className="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {courses.map((course) => {
                        const isBusy = busy === course.slug;
                        const hasQuestions = course.questions_count > 0;

                        return (
                            <article
                                key={course.id}
                                className="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <h3 className="text-base font-semibold tracking-tight">
                                        {course.name}
                                    </h3>
                                    <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-sm font-bold text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-300">
                                        {course.questions_count}
                                    </span>
                                </div>

                                <p className="mt-2 line-clamp-2 min-h-10 text-sm text-slate-500 dark:text-slate-400">
                                    {course.description ??
                                        'Bu ders için açıklama bulunmuyor.'}
                                </p>

                                <div className="mt-4 flex flex-wrap gap-2 text-xs">
                                    <span className="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {course.questions_count} soru
                                    </span>
                                    <span className="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                        {course.exam_sessions_count} oturum
                                    </span>
                                </div>

                                <div className="mt-6 flex flex-col gap-2">
                                    <button
                                        type="button"
                                        onClick={() => start(course)}
                                        disabled={!hasQuestions || isBusy}
                                        className="inline-flex w-full items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        {!hasQuestions
                                            ? 'Sorular hazırlanıyor'
                                            : isBusy
                                              ? 'Hazırlanıyor...'
                                              : 'Sınavı Başlat'}
                                    </button>
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}
        </>
    );
}

CoursesIndex.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;

export default CoursesIndex;
