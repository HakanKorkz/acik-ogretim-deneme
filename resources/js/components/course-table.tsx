import { cn } from '@/lib/utils';
import { edit, publish } from '@/routes/admin/courses';
import {
    index as questionsIndex,
    store as generateQuestions,
} from '@/routes/admin/courses/questions';
import {
    index as textIndex,
    store as extractText,
} from '@/routes/admin/courses/text';
import { Link, router } from '@inertiajs/react';
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

export interface CourseRow {
    id: number;
    name: string;
    subject: string | null;
    slug: string;
    hasSource: boolean;
    range: string;
    isActive: boolean;
    textStatus: 'queued' | 'running' | 'ready' | 'failed' | null;
    isExtractingText: boolean;
    sourceChanged: boolean;
    questionsCount: number;
    isGenerating: boolean;
    generationFailed: boolean;
}

export interface CoursesPage {
    data: CourseRow[];
    current_page: number;
    per_page: number;
    last_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

export interface CourseQuery {
    level: string;
    search: string;
    sort: 'default' | 'name' | 'questionsCount' | 'isActive';
    direction: 'asc' | 'desc';
    page: number;
    per_page: number;
}

export type CourseFilters = Pick<
    CourseQuery,
    'level' | 'search' | 'sort' | 'direction'
>;

const tableFeatures = defineTableFeatures({
    rowSortingFeature,
    rowPaginationFeature,
});

const actionClass =
    'rounded-lg border px-2.5 py-1 text-xs font-semibold whitespace-nowrap transition disabled:cursor-not-allowed disabled:opacity-50';

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

function CourseActions({ course }: { course: CourseRow }) {
    const post = (url: string) =>
        router.post(url, {}, { preserveScroll: true });
    const generate = () => {
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
        <div className="flex flex-col items-end gap-2">
            <div className="flex gap-1.5">
                <Link
                    href={textIndex.url(course.slug)}
                    className={cn(
                        actionClass,
                        'border-sky-300 text-sky-700 hover:bg-sky-50 dark:border-sky-800 dark:text-sky-300',
                    )}
                >
                    Kitap metni
                </Link>
                <Link
                    href={questionsIndex.url(course.slug)}
                    className={cn(
                        actionClass,
                        'border-indigo-300 text-indigo-700 hover:bg-indigo-50 dark:border-indigo-800 dark:text-indigo-300',
                    )}
                >
                    Sorular ({course.questionsCount})
                </Link>
            </div>
            <div className="flex gap-1.5">
                <button
                    type="button"
                    onClick={() => post(publish.url(course.slug))}
                    disabled={course.questionsCount === 0}
                    title={
                        course.questionsCount === 0
                            ? 'Önce soru üretin'
                            : undefined
                    }
                    className={cn(
                        actionClass,
                        course.isActive
                            ? 'border-amber-300 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300'
                            : 'border-emerald-300 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-300',
                    )}
                >
                    {course.isActive ? 'Yayından kaldır' : 'Yayınla'}
                </button>
                <button
                    type="button"
                    onClick={() => post(extractText.url(course.slug))}
                    disabled={course.isExtractingText || !course.hasSource}
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
                    onClick={generate}
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
                    Dersi düzenle
                </Link>
            </div>
        </div>
    );
}

const columns: ColumnDef<typeof tableFeatures, CourseRow>[] = [
    {
        accessorKey: 'name',
        header: 'Ders',
        cell: ({ row }) => (
            <>
                <p className="font-medium">{row.original.name}</p>
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {row.original.subject} · {row.original.range}
                </p>
            </>
        ),
    },
    {
        id: 'status',
        header: 'Metin',
        enableSorting: false,
        cell: ({ row }) => <TextBadge course={row.original} />,
    },
    {
        accessorKey: 'questionsCount',
        header: 'Soru',
        cell: ({ row }) => <QuestionBadge course={row.original} />,
    },
    {
        accessorKey: 'isActive',
        header: 'Yayın',
        cell: ({ row }) => (
            <Badge tone={row.original.isActive ? 'emerald' : 'slate'}>
                {row.original.isActive ? 'Aktif' : 'Pasif'}
            </Badge>
        ),
    },
    {
        id: 'actions',
        header: () => <span className="sr-only">İşlemler</span>,
        enableSorting: false,
        cell: ({ row }) => <CourseActions course={row.original} />,
    },
];

const getRowId = (course: CourseRow) => String(course.id);

export default function CourseTable({
    courses,
    filters,
    isLoading,
    onChange,
}: {
    courses: CoursesPage;
    filters: CourseFilters;
    isLoading: boolean;
    onChange: (changes: Partial<CourseQuery>) => void;
}) {
    const pagination: PaginationState = {
        pageIndex: courses.current_page - 1,
        pageSize: courses.per_page,
    };
    const sorting: SortingState =
        filters.sort === 'default'
            ? []
            : [{ id: filters.sort, desc: filters.direction === 'desc' }];
    const table = useTable(
        {
            features: tableFeatures,
            columns,
            data: courses.data,
            getRowId,
            manualPagination: true,
            manualSorting: true,
            rowCount: courses.total,
            autoResetPageIndex: false,
            enableMultiSort: false,
            sortDescFirst: false,
            state: { pagination, sorting },
            onPaginationChange: (updater) => {
                const next = functionalUpdate(updater, pagination);
                onChange({
                    page:
                        next.pageSize === pagination.pageSize
                            ? next.pageIndex + 1
                            : 1,
                    per_page: next.pageSize,
                });
            },
            onSortingChange: (updater) => {
                const next = functionalUpdate(updater, sorting)[0];
                onChange({
                    sort:
                        next?.id === 'name' ||
                        next?.id === 'questionsCount' ||
                        next?.id === 'isActive'
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
        <section
            aria-label="Ders listesi"
            aria-busy={isLoading}
            className="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                    <caption className="sr-only">
                        Dersler: metin ve soru üretim durumu, yayın durumu ve
                        yönetim işlemleri.
                    </caption>
                    <thead className="border-b border-slate-200 text-xs text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400">
                        {table.getHeaderGroups().map((group) => (
                            <tr key={group.id}>
                                {group.headers.map((header) => {
                                    const sorted = table.state.sorting.find(
                                        (item) => item.id === header.column.id,
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
                                                    className="inline-flex items-center gap-2 rounded text-left hover:text-indigo-600 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-500 disabled:opacity-60 dark:hover:text-indigo-300"
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
                                    className="px-4 py-10 text-center text-slate-500"
                                >
                                    {courses.total > 0
                                        ? 'Bu sayfada ders yok. İlk sayfaya dönün veya filtreleri değiştirin.'
                                        : 'Bu seçime uygun ders bulunamadı.'}
                                </td>
                            </tr>
                        )}
                        {table.getRowModel().rows.map((row) => (
                            <tr key={row.id}>
                                {row.getAllCells().map((cell) => (
                                    <td key={cell.id} className="px-4 py-3">
                                        <table.FlexRender cell={cell} />
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <div className="flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 px-4 py-4 text-sm dark:border-slate-800">
                <p role="status" className="text-slate-500 dark:text-slate-400">
                    {isLoading
                        ? 'Dersler yükleniyor…'
                        : `${courses.from ?? 0}–${courses.to ?? 0} / ${courses.total} ders`}
                </p>
                <div className="flex flex-wrap items-center gap-4">
                    <label className="flex items-center gap-2">
                        Sayfa başına
                        <select
                            aria-label="Sayfa başına ders sayısı"
                            value={table.state.pagination.pageSize}
                            disabled={isLoading}
                            onChange={(event) =>
                                table.setPageSize(Number(event.target.value))
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
                        aria-label="Ders listesi sayfaları"
                        className="flex items-center gap-2"
                    >
                        <button
                            type="button"
                            aria-label="İlk sayfa"
                            disabled={isLoading || !table.getCanPreviousPage()}
                            onClick={() => table.firstPage()}
                            className={cn(
                                actionClass,
                                'border-slate-300 dark:border-slate-700',
                            )}
                        >
                            «
                        </button>
                        <button
                            type="button"
                            disabled={isLoading || !table.getCanPreviousPage()}
                            onClick={() => table.previousPage()}
                            className={cn(
                                actionClass,
                                'border-slate-300 dark:border-slate-700',
                            )}
                        >
                            Önceki
                        </button>
                        <span className="px-1 text-xs whitespace-nowrap">
                            {table.state.pagination.pageIndex + 1} /{' '}
                            {courses.last_page}
                        </span>
                        <button
                            type="button"
                            disabled={isLoading || !table.getCanNextPage()}
                            onClick={() => table.nextPage()}
                            className={cn(
                                actionClass,
                                'border-slate-300 dark:border-slate-700',
                            )}
                        >
                            Sonraki
                        </button>
                        <button
                            type="button"
                            aria-label="Son sayfa"
                            disabled={isLoading || !table.getCanNextPage()}
                            onClick={() => table.lastPage()}
                            className={cn(
                                actionClass,
                                'border-slate-300 dark:border-slate-700',
                            )}
                        >
                            »
                        </button>
                    </nav>
                </div>
            </div>
        </section>
    );
}
