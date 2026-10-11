import BookUploadFields, {
    type UploadOptions,
} from '@/components/book-upload-fields';
import FormField from '@/components/form-field';
import AdminLayout from '@/layouts/admin-layout';
import { index, store } from '@/routes/admin/courses';
import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    levels: { value: string; label: string; subjects: string[] }[];
    upload: UploadOptions;
}

function CourseCreate({ levels, upload }: Props) {
    const [level, setLevel] = useState(levels[0]?.value ?? 'lise');
    const subjects =
        levels.find((item) => item.value === level)?.subjects ?? [];

    return (
        <>
            <Head title="Ders ekle" />

            <Link
                href={index.url()}
                className="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
            >
                ← Dersler
            </Link>
            <h1 className="mt-2 text-2xl font-bold tracking-tight">
                PDF ile ders ekle
            </h1>
            <p className="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                MEB kataloğunda olmayan bir ders kitabı için. Ders pasif
                eklenir; metni hazırlanınca soruları üretip yayınlayabilirsiniz.
            </p>

            <Form
                {...store.form()}
                className="mt-6 grid max-w-2xl gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                {({ errors, processing, progress }) => (
                    <>
                        <label className="flex flex-col gap-1.5 text-sm">
                            <span className="font-medium">Kademe</span>
                            <select
                                name="level"
                                value={level}
                                onChange={(event) =>
                                    setLevel(event.target.value)
                                }
                                className="rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                            >
                                {levels.map((item) => (
                                    <option key={item.value} value={item.value}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Ders grubu"
                                name="subject"
                                list="subjects"
                                placeholder="ör. TARİH"
                                required
                                error={errors.subject}
                            />
                            <datalist id="subjects">
                                {subjects.map((subject) => (
                                    <option key={subject} value={subject} />
                                ))}
                            </datalist>
                            <FormField
                                label="Ders adı"
                                name="name"
                                placeholder="ör. Tarih-3"
                                required
                                error={errors.name}
                            />
                        </div>

                        <label className="flex flex-col gap-1.5 text-sm">
                            <span className="font-medium">
                                Açıklama (isteğe bağlı)
                            </span>
                            <textarea
                                name="description"
                                rows={2}
                                className="rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 dark:border-slate-700 dark:bg-slate-900"
                            />
                        </label>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Başlangıç sayfası (basılı)"
                                name="page_from"
                                type="number"
                                min={1}
                                error={errors.page_from}
                            />
                            <FormField
                                label="Bitiş sayfası (basılı)"
                                name="page_to"
                                type="number"
                                min={1}
                                error={errors.page_to}
                            />
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
                                className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                {processing ? 'Yükleniyor...' : 'Dersi ekle'}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

CourseCreate.layout = (page: React.ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);

export default CourseCreate;
