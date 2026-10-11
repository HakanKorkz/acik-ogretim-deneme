import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { retry } from '@/routes/admin/past-exam-sections';
import {
    importMethod as importPastExam,
    sync,
} from '@/routes/admin/past-exams';
import { Head, router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';

interface Section {
    id: number;
    heading: string;
    course: string | null;
    status: string | null;
    statusLabel: string;
    importedCount: number;
    skippedCount: number;
    error: string | null;
    canRetry: boolean;
}

interface PastExamRow {
    id: number;
    title: string;
    sourceUrl: string;
    status: string | null;
    statusLabel: string;
    error: string | null;
    isPending: boolean;
    sectionsCount: number;
    importedQuestions: number;
    pendingSections: number;
    sections: Section[];
}

const statusTone: Record<string, string> = {
    done: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    failed: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
    queued: 'animate-pulse bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    running:
        'animate-pulse bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    skipped:
        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};

function StatusBadge({
    status,
    label,
}: {
    status: string | null;
    label: string;
}) {
    return (
        <span
            className={cn(
                'rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                status
                    ? statusTone[status]
                    : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            )}
        >
            {label}
        </span>
    );
}

function PastExams({ pastExams }: { pastExams: PastExamRow[] }) {
    const [open, setOpen] = useState<number | null>(null);
    const [syncing, setSyncing] = useState(false);
    const isWorking = pastExams.some(
        (exam) => exam.isPending || exam.pendingSections > 0,
    );

    const { start, stop } = usePoll(
        5000,
        { only: ['pastExams'] },
        { autoStart: false },
    );

    useEffect(() => {
        if (isWorking) {
            start();
        } else {
            stop();
        }
    }, [isWorking, start, stop]);

    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });

    return (
        <>
            <Head title="Çıkmış Sorular" />

            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Çıkmış Sorular
                    </h1>
                    <p className="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                        MEB'in yayımladığı kitapçıklar. İçe aktarılan kitapçık
                        ders bölümlerine ayrılır; yayındaki derslerin soruları
                        yapay zekâyla yapılandırılır, doğru şıklar kitapçığın
                        cevap anahtarından alınır. Görsel gerektiren sorular
                        atlanır.
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() =>
                        router.post(
                            sync.url(),
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => setSyncing(true),
                                onFinish: () => setSyncing(false),
                            },
                        )
                    }
                    disabled={syncing}
                    className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                >
                    {syncing ? 'Eşitleniyor...' : 'MEB listesini eşitle'}
                </button>
            </div>

            <div className="mt-6 grid gap-3">
                {pastExams.length === 0 && (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500 dark:border-slate-700 dark:bg-slate-900">
                        Henüz kitapçık yok. MEB listesini eşitleyin.
                    </div>
                )}

                {pastExams.map((exam) => (
                    <section
                        key={exam.id}
                        className="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                            <div>
                                <p className="font-medium">{exam.title}</p>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    {exam.sectionsCount > 0
                                        ? `${exam.sectionsCount} ders · ${exam.importedQuestions} soru aktarıldı`
                                        : 'İşlenmedi'}
                                    {exam.error && (
                                        <span className="text-rose-600">
                                            {' '}
                                            · {exam.error}
                                        </span>
                                    )}
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <StatusBadge
                                    status={exam.status}
                                    label={exam.statusLabel}
                                />
                                {exam.sectionsCount > 0 && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setOpen((current) =>
                                                current === exam.id
                                                    ? null
                                                    : exam.id,
                                            )
                                        }
                                        className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200"
                                    >
                                        {open === exam.id
                                            ? 'Bölümleri gizle'
                                            : 'Bölümler'}
                                    </button>
                                )}
                                <button
                                    type="button"
                                    onClick={() =>
                                        post(importPastExam.url(exam.id))
                                    }
                                    disabled={exam.isPending}
                                    className="rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:opacity-50 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300"
                                >
                                    {exam.sectionsCount > 0
                                        ? 'Yeniden işle'
                                        : 'İçe aktar'}
                                </button>
                                <a
                                    href={exam.sourceUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    PDF ↗
                                </a>
                            </div>
                        </div>

                        {open === exam.id && (
                            <div className="overflow-x-auto border-t border-slate-200 dark:border-slate-800">
                                <table className="w-full text-left text-sm">
                                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                                        {exam.sections.map((section) => (
                                            <tr key={section.id}>
                                                <td className="px-4 py-2">
                                                    <p className="font-medium">
                                                        {section.heading}
                                                    </p>
                                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                                        {section.course ??
                                                            'Katalogda eşleşmedi'}
                                                        {section.error && (
                                                            <span className="text-rose-600">
                                                                {' '}
                                                                ·{' '}
                                                                {section.error}
                                                            </span>
                                                        )}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-2 text-xs whitespace-nowrap text-slate-500">
                                                    {section.importedCount >
                                                        0 &&
                                                        `${section.importedCount} soru`}
                                                    {section.skippedCount > 0 &&
                                                        ` · ${section.skippedCount} atlandı`}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <StatusBadge
                                                        status={section.status}
                                                        label={
                                                            section.statusLabel
                                                        }
                                                    />
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    {section.canRetry &&
                                                        section.status !==
                                                            'done' && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    post(
                                                                        retry.url(
                                                                            section.id,
                                                                        ),
                                                                    )
                                                                }
                                                                className="text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                                            >
                                                                {section.status ===
                                                                'skipped'
                                                                    ? 'Yine de aktar'
                                                                    : 'Yeniden dene'}
                                                            </button>
                                                        )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                ))}
            </div>
        </>
    );
}

PastExams.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default PastExams;
