import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { sync } from '@/routes/admin/catalog';
import { create, edit, index } from '@/routes/admin/courses';
import { store as generateQuestions } from '@/routes/admin/courses/questions';
import { store as extractText } from '@/routes/admin/courses/text';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface CourseRow {
    id: number;
    name: string;
    subject: string | null;
    slug: string;
    source: string;
    hasSource: boolean;
    range: string;
    isActive: boolean;
    textStatus: 'queued' | 'running' | 'ready' | 'failed' | null;
    textStatusLabel: string;
    isExtractingText: boolean;
    sourceChanged: boolean;
    questionsCount: number;
    examSessionsCount: number;
    isGenerating: boolean;
    generationFailed: boolean;
}

interface LevelTab {
    value: string;
    label: string;
    count: number;
}

interface Props {
    courses: CourseRow[];
    filters: { level: string; search: string };
    levels: LevelTab[];
    questionsPerCourse: number;
}

function Badge({
    tone,
    children,
}: {
    tone: 'slate' | 'amber' | 'emerald' | 'rose' | 'sky';
    children: React.ReactNode;
}) {
    const tones = {
        slate: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        amber: 'animate-pulse bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
        emerald:
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
        rose: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
        sky: 'bg-sky-100 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
    };

    return (
        <span
            className={cn(
                'rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                tones[tone],
            )}
        >
            {children}
        </span>
    );
}

function TextBadge({ course }: { course: CourseRow }) {
    if (course.isExtractingText) {
        return <Badge tone="amber">Metin çıkarılıyor</Badge>;
    }

    if (course.textStatus === 'failed') {
        return <Badge tone="rose">Metin: başarısız</Badge>;
    }

    if (course.textStatus === 'ready') {
        return course.sourceChanged ? (
            <Badge tone="sky">Kitap güncellendi</Badge>
        ) : (
            <Badge tone="emerald">Metin hazır</Badge>
        );
    }

    return <Badge tone="slate">Metin yok</Badge>;
}

function QuestionBadge({ course }: { course: CourseRow }) {
    if (course.isGenerating) {
        return <Badge tone="amber">Sorular üretiliyor</Badge>;
    }

    if (course.generationFailed) {
        return <Badge tone="rose">Üretim başarısız</Badge>;
    }

    return (
        <Badge tone={course.questionsCount > 0 ? 'emerald' : 'slate'}>
            {course.questionsCount} soru
        </Badge>
    );
}

const actionClass =
    'rounded-lg border px-2.5 py-1 text-xs font-semibold whitespace-nowrap transition disabled:cursor-not-allowed disabled:opacity-50';

function CoursesIndex({ courses, filters, levels, questionsPerCourse }: Props) {
    const [search, setSearch] = useState(filters.search);
    const [syncing, setSyncing] = useState(false);
    const isAnyWorking = courses.some(
        (course) => course.isGenerating || course.isExtractingText,
    );

    const { start, stop } = usePoll(
        5000,
        { only: ['courses'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (isAnyWorking) {
            start();
        } else {
            stop();
        }
    }, [isAnyWorking, start, stop]);

    const visit = (level: string, term: string) => {
        router.get(
            index.url({ query: { level, search: term || undefined } }),
            {},
            { preserveState: true, replace: true },
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

    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });

    const generate = (course: CourseRow) => {
        if (
            course.questionsCount > 0 &&
            !window.confirm(
                `${course.name} için soru bankası yeniden üretilecek. Sınavlarda kullanılmış sorular arşivlenir. Devam edilsin mi?`,
            )
        ) {
            return;
        }

        post(generateQuestions.url(course.slug));
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
                <div className="flex gap-2">
                    <Link
                        href={create.url()}
                        className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    >
                        PDF ile ders ekle
                    </Link>
                    <button
                        type="button"
                        onClick={runSync}
                        disabled={syncing}
                        className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                    >
                        {syncing ? 'Eşitleniyor...' : 'MEB kataloğunu eşitle'}
                    </button>
                </div>
            </div>

            <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                <div className="flex gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800">
                    {levels.map((level) => (
                        <button
                            key={level.value}
                            type="button"
                            onClick={() => visit(level.value, search)}
                            className={cn(
                                'rounded-lg px-3 py-1.5 text-sm font-medium transition',
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
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit(filters.level, search);
                    }}
                >
                    <input
                        type="search"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Ders ara..."
                        className="w-56 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-900"
                    />
                </form>
            </div>

            <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-slate-200 text-xs text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400">
                        <tr>
                            <th className="px-4 py-3 font-medium">Ders</th>
                            <th className="px-4 py-3 font-medium">Durum</th>
                            <th className="px-4 py-3 font-medium">Yayında</th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {courses.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-4 py-8 text-center text-slate-500"
                                >
                                    Ders bulunamadı. Kataloğu eşitlemeyi
                                    deneyin.
                                </td>
                            </tr>
                        )}
                        {courses.map((course) => (
                            <tr key={course.id}>
                                <td className="px-4 py-3">
                                    <p className="font-medium">{course.name}</p>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        {course.subject} · {course.range}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-wrap gap-1.5">
                                        <TextBadge course={course} />
                                        <QuestionBadge course={course} />
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    {course.isActive ? (
                                        <Badge tone="emerald">Aktif</Badge>
                                    ) : (
                                        <Badge tone="slate">Pasif</Badge>
                                    )}
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-1.5">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                post(
                                                    extractText.url(
                                                        course.slug,
                                                    ),
                                                )
                                            }
                                            disabled={
                                                course.isExtractingText ||
                                                !course.hasSource
                                            }
                                            className={cn(
                                                actionClass,
                                                'border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200',
                                            )}
                                        >
                                            {course.textStatus === 'ready'
                                                ? 'Metni yenile'
                                                : 'Metni hazırla'}
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => generate(course)}
                                            disabled={
                                                course.isGenerating ||
                                                course.isExtractingText ||
                                                !course.hasSource
                                            }
                                            className={cn(
                                                actionClass,
                                                'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300',
                                            )}
                                        >
                                            {course.questionsCount > 0
                                                ? 'Yeniden üret'
                                                : 'Soruları üret'}
                                        </button>
                                        <Link
                                            href={edit.url(course.slug)}
                                            className={cn(
                                                actionClass,
                                                'border-slate-300 text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200',
                                            )}
                                        >
                                            Düzenle
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

CoursesIndex.layout = (page: React.ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);

export default CoursesIndex;
