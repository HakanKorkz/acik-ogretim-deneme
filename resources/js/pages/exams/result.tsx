import CourseResultCard, {
    type CourseResult,
} from '@/components/exam/course-result-card';
import QuestionReview, {
    type ReviewItem,
} from '@/components/exam/question-review';
import { type ExamSummary } from '@/components/exam/section-progress';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { index as coursesIndex } from '@/routes/courses';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    exam: ExamSummary;
    results: (CourseResult & { review: ReviewItem[] })[];
    totals: {
        courses: number;
        passed: number;
        correct: number;
        questions: number;
    };
}

function ExamResult({ exam, results, totals }: Props) {
    const [openCourse, setOpenCourse] = useState<number | null>(
        results.length === 1 ? results[0].position : null,
    );

    return (
        <>
            <Head title="Sınav Sonucu" />

            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
                Sınav Sonuç Belgesi
            </h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {exam.levelLabel} · {exam.pool} · {totals.courses} ders,{' '}
                {totals.passed} başarılı · {totals.correct}/{totals.questions}{' '}
                doğru
            </p>

            <div className="mt-6 overflow-x-auto rounded-2xl border border-slate-300 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-slate-300 bg-slate-50 text-xs uppercase dark:border-slate-700 dark:bg-slate-800/60">
                        <tr>
                            <th className="px-4 py-3">Ders</th>
                            <th className="px-3 py-3 text-center">Doğru</th>
                            <th className="px-3 py-3 text-center">Yanlış</th>
                            <th className="px-3 py-3 text-center">Boş</th>
                            <th className="px-3 py-3 text-center">Puan</th>
                            <th className="px-4 py-3 text-center">Sonuç</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                        {results.map((result) => (
                            <tr key={result.position}>
                                <td className="px-4 py-3 font-medium">
                                    {result.course}
                                </td>
                                <td className="px-3 py-3 text-center tabular-nums">
                                    {result.correct}
                                </td>
                                <td className="px-3 py-3 text-center tabular-nums">
                                    {result.wrong}
                                </td>
                                <td className="px-3 py-3 text-center tabular-nums">
                                    {result.blank}
                                </td>
                                <td className="px-3 py-3 text-center font-bold tabular-nums">
                                    {result.score}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    <span
                                        className={cn(
                                            'rounded-full px-2.5 py-0.5 text-xs font-bold',
                                            result.passed
                                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                : 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
                                        )}
                                    >
                                        {result.passed
                                            ? 'BAŞARILI'
                                            : 'BAŞARISIZ'}
                                    </span>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                Puan 100 üzerindendir; geçme notu {exam.passScore}.
            </p>

            <div className="mt-6 flex flex-wrap gap-3">
                <Link
                    href={coursesIndex.url(exam.level)}
                    className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                >
                    Yeni sınava gir
                </Link>
            </div>

            <h2 className="mt-10 text-lg font-semibold">
                Cevap anahtarı ve çözümler
            </h2>
            <div className="mt-4 grid gap-4">
                {results.map((result) => (
                    <div key={result.position}>
                        <button
                            type="button"
                            onClick={() =>
                                setOpenCourse((current) =>
                                    current === result.position
                                        ? null
                                        : result.position,
                                )
                            }
                            className="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white px-4 py-3 text-left text-sm font-semibold shadow-sm dark:border-slate-800 dark:bg-slate-900"
                            aria-expanded={openCourse === result.position}
                        >
                            {result.course}
                            <span className="text-slate-400">
                                {openCourse === result.position ? '−' : '+'}
                            </span>
                        </button>
                        {openCourse === result.position && (
                            <div className="mt-3 grid gap-4">
                                {results.length > 1 && (
                                    <CourseResultCard
                                        result={result}
                                        passScore={exam.passScore}
                                    />
                                )}
                                {result.review.map((item) => (
                                    <QuestionReview
                                        key={item.number}
                                        item={item}
                                    />
                                ))}
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </>
    );
}

ExamResult.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;

export default ExamResult;
