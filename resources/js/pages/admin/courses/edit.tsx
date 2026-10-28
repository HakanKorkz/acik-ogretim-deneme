import BookUploadFields, {
    type UploadOptions,
} from '@/components/book-upload-fields';
import FormField from '@/components/form-field';
import AdminLayout from '@/layouts/admin-layout';
import { index, update } from '@/routes/admin/courses';
import { update as updateBook } from '@/routes/admin/courses/book';
import { index as questionsIndex } from '@/routes/admin/courses/questions';
import { index as textIndex } from '@/routes/admin/courses/text';
import { Form, Head, Link } from '@inertiajs/react';

interface PagePreview {
    pdfPage: number;
    printedPage: number;
    excerpt: string;
}

interface Props {
    course: {
        name: string;
        subject: string | null;
        slug: string;
        source: string;
        range: string;
        isActive: boolean;
        textStatusLabel: string;
        questionsCount: number;
        description: string | null;
        sourceUrl: string | null;
        pageFrom: number | null;
        pageTo: number | null;
        pageOffset: number | null;
        pageCount: number | null;
    };
    upload: (UploadOptions & { storedOn: string | null }) | null;
    scopePreview: {
        first: PagePreview | null;
        last: PagePreview | null;
        pages: number;
    } | null;
}

function Preview({ title, page }: { title: string; page: PagePreview | null }) {
    if (page === null) {
        return null;
    }

    return (
        <div className="rounded-xl bg-slate-50 p-3 text-xs dark:bg-slate-800/60">
            <p className="font-semibold">
                {title}: basılı s. {page.printedPage} (PDF s. {page.pdfPage})
            </p>
            <p className="mt-1 text-slate-600 dark:text-slate-300">
                {page.excerpt}
            </p>
        </div>
    );
}

function CourseEdit({ course, scopePreview, upload }: Props) {
    return (
        <>
            <Head title={course.name} />

            <Link
                href={index.url()}
                className="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
            >
                ← Dersler
            </Link>
            <h1 className="mt-2 text-2xl font-bold tracking-tight">
                {course.name}
            </h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {course.subject} · {course.source} · Metin:{' '}
                {course.textStatusLabel}
                {course.pageCount ? ` (${course.pageCount} sayfa)` : ''} ·{' '}
                {course.questionsCount} soru
            </p>

            <nav
                aria-label="Ders içeriği"
                className="mt-4 flex gap-4 text-sm font-semibold text-indigo-600 dark:text-indigo-400"
            >
                <Link
                    href={textIndex.url(course.slug)}
                    className="hover:underline"
                >
                    Kitap metnini görüntüle
                </Link>
                <Link
                    href={questionsIndex.url(course.slug)}
                    className="hover:underline"
                >
                    Soruları görüntüle ve düzenle ({course.questionsCount})
                </Link>
            </nav>
            <div className="mt-6 grid max-w-3xl gap-6">
                {course.description && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 className="font-semibold">MEB açıklaması</h2>
                        <p className="mt-2 text-slate-600 dark:text-slate-300">
                            {course.description}
                        </p>
                        {course.sourceUrl && (
                            <a
                                href={course.sourceUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="mt-2 inline-block text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                Kitabı aç ↗
                            </a>
                        )}
                    </section>
                )}

                <Form
                    {...update.form(course.slug)}
                    options={{ preserveScroll: true }}
                    className="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                >
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <h2 className="text-base font-semibold">
                                    Sorumluluk aralığı
                                </h2>
                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Kitabın basılı sayfa numaraları. Boş
                                    bırakılan sınır kitabın başı/sonu demektir.
                                    Kaydırma, PDF sırası ile basılı numara
                                    arasındaki farktır (kapak ve ön sayfalar);
                                    boşsa metin çıkarılırken otomatik bulunur.
                                </p>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <FormField
                                    label="Başlangıç sayfası"
                                    name="page_from"
                                    type="number"
                                    min={1}
                                    defaultValue={course.pageFrom ?? ''}
                                    error={errors.page_from}
                                />
                                <FormField
                                    label="Bitiş sayfası"
                                    name="page_to"
                                    type="number"
                                    min={1}
                                    defaultValue={course.pageTo ?? ''}
                                    error={errors.page_to}
                                />
                                <FormField
                                    label="Sayfa kaydırması"
                                    name="page_offset"
                                    type="number"
                                    defaultValue={course.pageOffset ?? ''}
                                    error={errors.page_offset}
                                />
                            </div>

                            {scopePreview && (
                                <div className="grid gap-2">
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        Aralıkta {scopePreview.pages} sayfa var.
                                        İlk ve son sayfanın kitaptaki numarayla
                                        eşleştiğini kontrol edin:
                                    </p>
                                    <Preview
                                        title="İlk sayfa"
                                        page={scopePreview.first}
                                    />
                                    <Preview
                                        title="Son sayfa"
                                        page={scopePreview.last}
                                    />
                                </div>
                            )}

                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={course.isActive}
                                />
                                Ön yüzde yayınla (soruları hazır olduğunda
                                öğrenciler görür)
                            </label>

                            <div>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                                >
                                    Kaydet
                                </button>
                            </div>
                        </>
                    )}
                </Form>

                {upload && (
                    <Form
                        {...updateBook.form(course.slug)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        {({ errors, processing, progress }) => (
                            <>
                                <div>
                                    <h2 className="text-base font-semibold">
                                        Kitabı değiştir
                                    </h2>
                                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {upload.storedOn
                                            ? `Şu an: ${upload.storedOn}. `
                                            : ''}
                                        Yeni PDF yüklenince eski dosya silinir
                                        ve metin yeniden çıkarılır; mevcut
                                        sorular korunur.
                                    </p>
                                </div>
                                <BookUploadFields
                                    upload={upload}
                                    error={errors.book}
                                    progress={progress}
                                />
                                <div>
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="rounded-xl border border-indigo-200 bg-indigo-50 px-5 py-2.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 disabled:opacity-60 dark:border-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-300"
                                    >
                                        {processing
                                            ? 'Yükleniyor...'
                                            : 'Kitabı yükle'}
                                    </button>
                                </div>
                            </>
                        )}
                    </Form>
                )}
            </div>
        </>
    );
}

CourseEdit.layout = (page: React.ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);

export default CourseEdit;
