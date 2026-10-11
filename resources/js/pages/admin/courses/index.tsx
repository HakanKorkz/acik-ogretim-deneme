import AdminLayout from '@/layouts/admin-layout';
import { store as generateQuestions } from '@/routes/admin/courses/questions';
import { Head, router, usePoll } from '@inertiajs/react';
import { useEffect } from 'react';

interface CourseRow {
    id: number;
    name: string;
    slug: string;
    filePath: string | null;
    questionsCount: number;
    examSessionsCount: number;
    isGenerating: boolean;
    generationFailed: boolean;
}

interface Props {
    courses: CourseRow[];
    questionsPerCourse: number;
}

function StatusBadge({ course }: { course: CourseRow }) {
    if (course.isGenerating) {
        return (
            <span className="animate-pulse rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-950/60 dark:text-amber-300">
                Üretiliyor
            </span>
        );
    }

    if (course.generationFailed) {
        return (
            <span className="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-medium text-rose-700 dark:bg-rose-950/60 dark:text-rose-300">
                Başarısız
            </span>
        );
    }

    if (course.questionsCount > 0) {
        return (
            <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                Hazır
            </span>
        );
    }

    return (
        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">
            Soru yok
        </span>
    );
}

function CoursesIndex({ courses, questionsPerCourse }: Props) {
    const isAnyGenerating = courses.some((course) => course.isGenerating);

    const { start, stop } = usePoll(
        5000,
        { only: ['courses'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (isAnyGenerating) {
            start();
        } else {
            stop();
        }
    }, [isAnyGenerating, start, stop]);

    const generate = (course: CourseRow) => {
        if (
            course.questionsCount > 0 &&
            !window.confirm(
                `${course.name} için soru bankası yeniden üretilecek. Sınavlarda kullanılmış sorular arşivlenir. Devam edilsin mi?`,
            )
        ) {
            return;
        }

        router.post(
            generateQuestions.url(course.slug),
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Dersler" />

            <div className="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Dersler
                    </h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Her ders için {questionsPerCourse} soruluk banka
                        üretilir.
                    </p>
                </div>
            </div>

            <div className="mt-6 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-slate-200 text-xs text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400">
                        <tr>
                            <th className="px-4 py-3 font-medium">Ders</th>
                            <th className="px-4 py-3 font-medium">Durum</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Soru
                            </th>
                            <th className="px-4 py-3 text-right font-medium">
                                Oturum
                            </th>
                            <th className="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {courses.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-4 py-8 text-center text-slate-500"
                                >
                                    Henüz ders yok.
                                </td>
                            </tr>
                        )}
                        {courses.map((course) => (
                            <tr key={course.id}>
                                <td className="px-4 py-3">
                                    <p className="font-medium">{course.name}</p>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        {course.filePath ?? 'PDF kaynağı yok'}
                                    </p>
                                </td>
                                <td className="px-4 py-3">
                                    <StatusBadge course={course} />
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {course.questionsCount}
                                </td>
                                <td className="px-4 py-3 text-right tabular-nums">
                                    {course.examSessionsCount}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <button
                                        type="button"
                                        onClick={() => generate(course)}
                                        disabled={
                                            course.isGenerating ||
                                            !course.filePath
                                        }
                                        className="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold whitespace-nowrap text-indigo-700 transition hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300"
                                    >
                                        {course.questionsCount > 0
                                            ? 'Yeniden üret'
                                            : 'Soruları üret'}
                                    </button>
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
