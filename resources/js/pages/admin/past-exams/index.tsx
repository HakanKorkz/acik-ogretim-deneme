import ImportStatusBadge from '@/components/import-status-badge';
import PastExamSections from '@/components/past-exam-sections';
import AdminLayout from '@/layouts/admin-layout';
import {
    index,
    importMethod as importPastExam,
    sync,
} from '@/routes/admin/past-exams';
import { Head, router, usePoll } from '@inertiajs/react';
import {
    functionalUpdate,
    rowPaginationFeature,
    rowSortingFeature,
    tableFeatures as defineTableFeatures,
    useTable,
    type ColumnDef,
    type PaginationState,
    type SortingState,
} from '@tanstack/react-table';
import { Fragment, useEffect, useState } from 'react';

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
}

interface Filters {
    search: string;
    status: string;
    sort: 'default' | 'title' | 'status' | 'importedQuestions';
    direction: 'asc' | 'desc';
}

interface Query extends Filters {
    page: number;
    per_page: number;
}

interface Props {
    pastExams: {
        data: PastExamRow[];
        current_page: number;
        per_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: Filters;
    statuses: { value: string; label: string }[];
}

const tableFeatures = defineTableFeatures({
    rowSortingFeature,
    rowPaginationFeature,
});
const columns: ColumnDef<typeof tableFeatures, PastExamRow>[] = [
    {
        accessorKey: 'title',
        header: 'Kitapçık',
        cell: ({ row }) => (
            <>
                <p className="font-medium">{row.original.title}</p>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {row.original.sectionsCount} ders bölümü
                    {row.original.pendingSections > 0 &&
                        ` · ${row.original.pendingSections} bölüm işleniyor`}
                </p>
                {row.original.error && (
                    <p className="mt-1 max-w-xl text-xs text-rose-600 dark:text-rose-400">
                        {row.original.error}
                    </p>
                )}
            </>
        ),
    },
    {
        accessorKey: 'status',
        header: 'Durum',
        cell: ({ row }) => (
            <ImportStatusBadge
                status={row.original.status}
                label={row.original.statusLabel}
            />
        ),
    },
    {
        accessorKey: 'importedQuestions',
        header: 'Aktarılan soru',
        cell: ({ row }) => (
            <span className="font-medium">
                {row.original.importedQuestions}
            </span>
        ),
    },
    {
        id: 'actions',
        header: () => <span className="sr-only">İşlemler</span>,
        enableSorting: false,
    },
];
const getRowId = (exam: PastExamRow) => String(exam.id);
const buttonClass =
    'rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold whitespace-nowrap hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:hover:bg-slate-800';

function PastExams({ pastExams, filters, statuses }: Props) {
    const [open, setOpen] = useState<number | null>(null);
    const [search, setSearch] = useState(filters.search);
    const [syncing, setSyncing] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const isWorking = pastExams.data.some(
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
    useEffect(() => {
        setSearch(filters.search);
    }, [filters.search]);
    useEffect(() => {
        setOpen(null);
    }, [
        pastExams.current_page,
        filters.search,
        filters.status,
        filters.sort,
        filters.direction,
    ]);

    const visit = (changes: Partial<Query>) => {
        const query = {
            ...filters,
            page: pastExams.current_page,
            per_page: pastExams.per_page,
            ...changes,
        };
        router.get(
            index.url({
                query: { ...query, search: query.search || undefined },
            }),
            {},
            {
                only: ['pastExams', 'filters'],
                preserveState: true,
                preserveScroll: true,
                onStart: () => {
                    setIsLoading(true);
                    setOpen(null);
                },
                onFinish: () => setIsLoading(false),
            },
        );
    };

    const pagination: PaginationState = {
        pageIndex: pastExams.current_page - 1,
        pageSize: pastExams.per_page,
    };
    const sorting: SortingState =
        filters.sort === 'default'
            ? []
            : [{ id: filters.sort, desc: filters.direction === 'desc' }];
    const table = useTable(
        {
            features: tableFeatures,
            columns,
            data: pastExams.data,
            getRowId,
            manualPagination: true,
            manualSorting: true,
            rowCount: pastExams.total,
            autoResetPageIndex: false,
            enableMultiSort: false,
            sortDescFirst: false,
            state: { pagination, sorting },
            onPaginationChange: (updater) => {
                const next = functionalUpdate(updater, pagination);
                visit({
                    page:
                        next.pageSize === pagination.pageSize
                            ? next.pageIndex + 1
                            : 1,
                    per_page: next.pageSize,
                });
            },
            onSortingChange: (updater) => {
                const next = functionalUpdate(updater, sorting)[0];
                visit({
                    sort:
                        next?.id === 'title' ||
                        next?.id === 'status' ||
                        next?.id === 'importedQuestions'
                            ? next.id
                            : 'default',
                    direction: next?.desc ? 'desc' : 'asc',
                    page: 1,
                });
            },
        },
        (state) => ({ pagination: state.pagination, sorting: state.sorting }),
    );

    return (
        <>
            <Head title="Çıkmış Sorular" />
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        Çıkmış Sorular
                    </h1>
                    <p className="mt-1 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400">
                        MEB kitapçıklarını işleyin; bölüm durumlarını ve
                        aktarılan soruları takip edin. Doğru şıklar kitapçığın
                        cevap anahtarından alınır. Eksiksiz metin soruları
                        doğrudan, diğer bölümler yapay zekâyla ayrıştırılır;
                        görsel gerektiren sorular atlanır.
                    </p>
                </div>
                <button
                    type="button"
                    disabled={syncing || isLoading}
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
                    className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-60"
                >
                    {syncing ? 'Eşitleniyor...' : 'MEB listesini eşitle'}
                </button>
            </div>
            <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                <label className="flex items-center gap-2 text-sm">
                    Durum
                    <select
                        value={filters.status}
                        disabled={isLoading}
                        onChange={(event) =>
                            visit({ status: event.target.value, page: 1 })
                        }
                        className="rounded-xl border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                    >
                        <option value="all">Tüm durumlar</option>
                        <option value="unprocessed">İşlenmedi</option>
                        {statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                </label>
                <form
                    role="search"
                    className="flex flex-wrap gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        visit({ search: search.trim(), page: 1 });
                    }}
                >
                    <label htmlFor="past-exam-search" className="sr-only">
                        Kitapçık başlığında ara
                    </label>
                    <input
                        id="past-exam-search"
                        type="search"
                        maxLength={100}
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Kitapçık veya dönem ara…"
                        className="w-64 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500 dark:border-slate-700 dark:bg-slate-900"
                    />
                    <button
                        type="submit"
                        disabled={isLoading}
                        className={buttonClass}
                    >
                        Ara
                    </button>
                    {filters.search && (
                        <button
                            type="button"
                            disabled={isLoading}
                            onClick={() => visit({ search: '', page: 1 })}
                            className={buttonClass}
                        >
                            Temizle
                        </button>
                    )}
                </form>
            </div>
            <section
                aria-label="Sınav kitapçıkları"
                aria-busy={isLoading}
                className="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <caption className="sr-only">
                            Sınav kitapçıkları ve aktarım durumları
                        </caption>
                        <thead className="border-b border-slate-200 text-xs text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400">
                            {table.getHeaderGroups().map((group) => (
                                <tr key={group.id}>
                                    {group.headers.map((header) => {
                                        const sorted = table.state.sorting.find(
                                            (item) =>
                                                item.id === header.column.id,
                                        );
                                        return (
                                            <th
                                                key={header.id}
                                                scope="col"
                                                aria-sort={
                                                    header.column.getCanSort()
                                                        ? sorted
                                                            ? sorted.desc
                                                                ? 'descending'
                                                                : 'ascending'
                                                            : 'none'
                                                        : undefined
                                                }
                                                className="px-4 py-3 font-medium"
                                            >
                                                {header.column.getCanSort() ? (
                                                    <button
                                                        type="button"
                                                        disabled={isLoading}
                                                        onClick={header.column.getToggleSortingHandler()}
                                                        className="inline-flex items-center gap-2 rounded text-left hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500 dark:hover:text-indigo-300"
                                                    >
                                                        <table.FlexRender
                                                            header={header}
                                                        />
                                                        <span aria-hidden="true">
                                                            {sorted
                                                                ? sorted.desc
                                                                    ? '↓'
                                                                    : '↑'
                                                                : '↕'}
                                                        </span>
                                                    </button>
                                                ) : (
                                                    <table.FlexRender
                                                        header={header}
                                                    />
                                                )}
                                            </th>
                                        );
                                    })}
                                </tr>
                            ))}
                        </thead>
                        <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                            {table.getRowModel().rows.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={columns.length}
                                        className="p-10 text-center text-slate-500"
                                    >
                                        {pastExams.total > 0
                                            ? 'Bu sayfada kitapçık yok. İlk sayfaya dönün.'
                                            : 'Bu seçime uygun kitapçık bulunamadı. Aramayı değiştirin veya MEB listesini eşitleyin.'}
                                    </td>
                                </tr>
                            )}
                            {table.getRowModel().rows.map((row) => {
                                const exam = row.original;
                                return (
                                    <Fragment key={row.id}>
                                        <tr>
                                            {row.getAllCells().map((cell) => (
                                                <td
                                                    key={cell.id}
                                                    className="px-4 py-4"
                                                >
                                                    {cell.column.id ===
                                                    'actions' ? (
                                                        <div className="flex items-center justify-end gap-2">
                                                            {exam.sectionsCount >
                                                                0 && (
                                                                <button
                                                                    type="button"
                                                                    aria-expanded={
                                                                        open ===
                                                                        exam.id
                                                                    }
                                                                    aria-controls={`sections-${exam.id}`}
                                                                    onClick={() =>
                                                                        setOpen(
                                                                            open ===
                                                                                exam.id
                                                                                ? null
                                                                                : exam.id,
                                                                        )
                                                                    }
                                                                    className={
                                                                        buttonClass
                                                                    }
                                                                >
                                                                    {open ===
                                                                    exam.id
                                                                        ? 'Bölümleri gizle'
                                                                        : 'Bölümler'}
                                                                </button>
                                                            )}
                                                            <button
                                                                type="button"
                                                                disabled={
                                                                    exam.isPending
                                                                }
                                                                onClick={() =>
                                                                    router.post(
                                                                        importPastExam.url(
                                                                            exam.id,
                                                                        ),
                                                                        {},
                                                                        {
                                                                            preserveScroll: true,
                                                                        },
                                                                    )
                                                                }
                                                                className="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold whitespace-nowrap text-indigo-700 hover:bg-indigo-100 disabled:opacity-50 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300"
                                                            >
                                                                {exam.sectionsCount >
                                                                0
                                                                    ? 'Yeniden işle'
                                                                    : 'İçe aktar'}
                                                            </button>
                                                            <a
                                                                href={
                                                                    exam.sourceUrl
                                                                }
                                                                target="_blank"
                                                                rel="noreferrer"
                                                                className="text-xs whitespace-nowrap text-indigo-600 hover:underline dark:text-indigo-400"
                                                            >
                                                                PDF ↗
                                                            </a>
                                                        </div>
                                                    ) : (
                                                        <table.FlexRender
                                                            cell={cell}
                                                        />
                                                    )}
                                                </td>
                                            ))}
                                        </tr>
                                        {open === exam.id && (
                                            <tr id={`sections-${exam.id}`}>
                                                <td colSpan={columns.length}>
                                                    <PastExamSections
                                                        key={exam.id}
                                                        exam={exam}
                                                    />
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-4 py-4 text-sm dark:border-slate-800">
                    <p
                        role="status"
                        className="text-slate-500 dark:text-slate-400"
                    >
                        {isLoading
                            ? 'Kitapçıklar yükleniyor…'
                            : `${pastExams.from ?? 0}–${pastExams.to ?? 0} / ${pastExams.total} kitapçık`}
                    </p>
                    <div className="flex flex-wrap items-center gap-4">
                        <label className="flex items-center gap-2">
                            Sayfa başına
                            <select
                                aria-label="Sayfa başına kitapçık sayısı"
                                disabled={isLoading}
                                value={table.state.pagination.pageSize}
                                onChange={(event) =>
                                    table.setPageSize(
                                        Number(event.target.value),
                                    )
                                }
                                className="rounded-lg border border-slate-300 bg-white px-2 py-1.5 dark:border-slate-700 dark:bg-slate-950"
                            >
                                {[10, 25, 50, 100].map((size) => (
                                    <option key={size} value={size}>
                                        {size}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <nav
                            aria-label="Kitapçık sayfaları"
                            className="flex items-center gap-2"
                        >
                            <button
                                type="button"
                                aria-label="İlk sayfa"
                                disabled={
                                    isLoading || !table.getCanPreviousPage()
                                }
                                onClick={() => table.firstPage()}
                                className={buttonClass}
                            >
                                «
                            </button>
                            <button
                                type="button"
                                disabled={
                                    isLoading || !table.getCanPreviousPage()
                                }
                                onClick={() => table.previousPage()}
                                className={buttonClass}
                            >
                                Önceki
                            </button>
                            <span className="text-xs">
                                {table.state.pagination.pageIndex + 1} /{' '}
                                {pastExams.last_page}
                            </span>
                            <button
                                type="button"
                                disabled={isLoading || !table.getCanNextPage()}
                                onClick={() => table.nextPage()}
                                className={buttonClass}
                            >
                                Sonraki
                            </button>
                            <button
                                type="button"
                                aria-label="Son sayfa"
                                disabled={isLoading || !table.getCanNextPage()}
                                onClick={() => table.lastPage()}
                                className={buttonClass}
                            >
                                »
                            </button>
                        </nav>
                    </div>
                </div>
            </section>
        </>
    );
}

PastExams.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default PastExams;
