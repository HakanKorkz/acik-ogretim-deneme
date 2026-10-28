import ImportStatusBadge from '@/components/import-status-badge';
import { retry } from '@/routes/admin/past-exam-sections';
import { sections } from '@/routes/admin/past-exams';
import { router, useHttp } from '@inertiajs/react';
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

interface SectionPage {
    data: Section[];
    current_page: number;
    last_page: number;
    total: number;
}

export default function PastExamSections({
    exam,
}: {
    exam: { id: number; title: string; pendingSections: number };
}) {
    const [page, setPage] = useState(1);
    const [data, setData] = useState<SectionPage | null>(null);
    const [error, setError] = useState(false);
    const [version, setVersion] = useState(0);
    const { get, cancel, processing } = useHttp<
        Record<string, never>,
        SectionPage
    >({});

    useEffect(() => {
        let active = true;
        setError(false);
        void get(sections.url(exam.id, { query: { page } }))
            .then((response) => {
                if (active) {
                    setData(response);
                }
            })
            .catch(() => {
                if (active) {
                    setError(true);
                }
            });

        return () => {
            active = false;
            cancel();
        };
    }, [exam, page, version, get, cancel]);

    return (
        <section
            aria-label={`${exam.title} ders bölümleri`}
            aria-busy={processing}
            className="bg-slate-50 p-4 dark:bg-slate-950/50"
        >
            <h2 className="mb-3 text-sm font-semibold">
                {exam.title} · Ders bölümleri
            </h2>
            {error ? (
                <p
                    role="alert"
                    className="text-sm text-rose-600 dark:text-rose-400"
                >
                    Bölümler yüklenemedi.{' '}
                    <button
                        type="button"
                        onClick={() => setVersion((value) => value + 1)}
                        className="font-semibold underline"
                    >
                        Yeniden dene
                    </button>
                </p>
            ) : data === null || data.current_page !== page ? (
                <div
                    role="status"
                    className="animate-pulse rounded-xl bg-slate-200 px-4 py-8 text-sm dark:bg-slate-800"
                >
                    Bölümler yükleniyor…
                </div>
            ) : (
                <>
                    <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-white text-xs text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                                <tr>
                                    <th scope="col" className="px-4 py-3">
                                        Ders
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Aktarım
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        Durum
                                    </th>
                                    <th scope="col" className="px-4 py-3">
                                        <span className="sr-only">
                                            İşlemler
                                        </span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                                {data.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={4}
                                            className="p-4 text-center text-slate-500"
                                        >
                                            Bu sayfada bölüm bulunamadı.
                                        </td>
                                    </tr>
                                )}
                                {data.data.map((section) => (
                                    <tr key={section.id}>
                                        <td className="px-4 py-3">
                                            <p className="font-medium">
                                                {section.heading}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                                {section.course ??
                                                    'Katalogda eşleşmedi'}
                                            </p>
                                            {section.error && (
                                                <p className="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                                    {section.error}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-xs whitespace-nowrap">
                                            {section.importedCount} soru ·{' '}
                                            {section.skippedCount} atlandı
                                        </td>
                                        <td className="px-4 py-3">
                                            <ImportStatusBadge
                                                status={section.status}
                                                label={section.statusLabel}
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            {section.canRetry &&
                                                section.status !== 'done' && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                retry.url(
                                                                    section.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                    onSuccess:
                                                                        () =>
                                                                            setVersion(
                                                                                (
                                                                                    value,
                                                                                ) =>
                                                                                    value +
                                                                                    1,
                                                                            ),
                                                                },
                                                            )
                                                        }
                                                        className="text-xs font-semibold whitespace-nowrap text-indigo-600 hover:underline dark:text-indigo-400"
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
                    <nav
                        aria-label="Ders bölümü sayfaları"
                        className="mt-3 flex items-center justify-end gap-3 text-sm"
                    >
                        <span className="mr-auto text-xs text-slate-500">
                            Toplam {data.total} bölüm · Sayfa başına 25
                        </span>
                        <button
                            type="button"
                            disabled={data.current_page <= 1}
                            onClick={() => setPage(page - 1)}
                            className="rounded-lg border border-slate-300 px-3 py-1 disabled:opacity-40 dark:border-slate-700"
                        >
                            Önceki
                        </button>
                        <span>
                            {data.current_page} / {data.last_page}
                        </span>
                        <button
                            type="button"
                            disabled={data.current_page >= data.last_page}
                            onClick={() => setPage(page + 1)}
                            className="rounded-lg border border-slate-300 px-3 py-1 disabled:opacity-40 dark:border-slate-700"
                        >
                            Sonraki
                        </button>
                    </nav>
                </>
            )}
        </section>
    );
}
