import QuestionStem from '@/components/exam/question-stem';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/admin/courses/questions';
import { Head, Link, useForm } from '@inertiajs/react';
import type { QuestionRow } from './index';

interface Props {
    course: { slug: string; name: string; level: string };
    question: QuestionRow & { version: string };
}

const labels = ['A', 'B', 'C', 'D'] as const;
const inputClass =
    'mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-950';

function ErrorMessage({ message }: { message?: string }) {
    return message ? (
        <p
            role="alert"
            className="mt-1 text-sm text-rose-600 dark:text-rose-400"
        >
            {message}
        </p>
    ) : null;
}

function Editor({ course, question }: Props) {
    const form = useForm({
        version: question.version,
        passage: question.passage ?? '',
        text: question.text,
        options: Object.fromEntries(
            labels.map((label) => [
                label,
                question.options.find((option) => option.label === label)
                    ?.text ?? '',
            ]),
        ) as Record<(typeof labels)[number], string>,
        correct_option: question.correctOption,
        source_page: question.sourcePage?.toString() ?? '',
    });

    return (
        <div className="mt-6 grid items-start gap-6 xl:grid-cols-2">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(update.url([course.slug, question.id]), {
                        preserveScroll: true,
                    });
                }}
                className="space-y-5 rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 dark:border-slate-800 dark:bg-slate-900"
            >
                <h2 className="text-lg font-semibold">Soruyu düzenle</h2>
                <ErrorMessage message={form.errors.version} />
                <label className="block text-sm font-medium" htmlFor="passage">
                    Giriş metni / öncüller (isteğe bağlı)
                    <textarea
                        id="passage"
                        rows={6}
                        maxLength={10000}
                        value={form.data.passage}
                        onChange={(event) =>
                            form.setData('passage', event.target.value)
                        }
                        aria-invalid={!!form.errors.passage}
                        className={inputClass}
                    />
                    <ErrorMessage message={form.errors.passage} />
                </label>
                <label
                    className="block text-sm font-medium"
                    htmlFor="question-text"
                >
                    Soru kökü
                    <textarea
                        id="question-text"
                        required
                        rows={5}
                        maxLength={10000}
                        value={form.data.text}
                        onChange={(event) =>
                            form.setData('text', event.target.value)
                        }
                        aria-invalid={!!form.errors.text}
                        className={inputClass}
                    />
                    <ErrorMessage message={form.errors.text} />
                </label>
                <fieldset className="space-y-4">
                    <legend className="mb-2 text-sm font-semibold">
                        Şıklar ve doğru cevap
                    </legend>
                    <p className="text-xs text-slate-500 dark:text-slate-400">
                        Doğru şıkkı daireden seçin. Dört şık birbirinden farklı
                        olmalıdır.
                    </p>
                    <ErrorMessage message={form.errors.options} />
                    {labels.map((label) => (
                        <div key={label} className="flex items-start gap-3">
                            <label className="mt-4 flex items-center gap-2 text-sm font-semibold">
                                <input
                                    type="radio"
                                    name="correct_option"
                                    value={label}
                                    checked={form.data.correct_option === label}
                                    onChange={() =>
                                        form.setData('correct_option', label)
                                    }
                                    aria-label={`${label} doğru cevap`}
                                    className="accent-indigo-600"
                                />
                                {label}
                            </label>
                            <div className="min-w-0 flex-1">
                                <label
                                    htmlFor={`option-${label}`}
                                    className="sr-only"
                                >
                                    {label} şıkkının metni
                                </label>
                                <textarea
                                    id={`option-${label}`}
                                    required
                                    rows={2}
                                    maxLength={10000}
                                    value={form.data.options[label]}
                                    onChange={(event) =>
                                        form.setData('options', {
                                            ...form.data.options,
                                            [label]: event.target.value,
                                        })
                                    }
                                    aria-invalid={
                                        !!form.errors[`options.${label}`]
                                    }
                                    className={inputClass}
                                />
                                <ErrorMessage
                                    message={form.errors[`options.${label}`]}
                                />
                            </div>
                        </div>
                    ))}
                    <ErrorMessage message={form.errors.correct_option} />
                </fieldset>
                <label
                    className="block text-sm font-medium"
                    htmlFor="source-page"
                >
                    Kaynak kitabın basılı sayfası (isteğe bağlı)
                    <input
                        id="source-page"
                        type="number"
                        min={1}
                        max={2000}
                        value={form.data.source_page}
                        onChange={(event) =>
                            form.setData('source_page', event.target.value)
                        }
                        className={inputClass}
                    />
                    <ErrorMessage message={form.errors.source_page} />
                </label>
                <p className="text-xs text-slate-500 dark:text-slate-400">
                    Düzeltme yeni sınavlarda kullanılır. Daha önce başlatılmış
                    sınavların soruları ve cevapları korunur.
                </p>
                <div className="flex flex-wrap items-center gap-4">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                    >
                        {form.processing
                            ? 'Kaydediliyor…'
                            : 'Değişiklikleri kaydet'}
                    </button>
                    <Link
                        href={index.url(course.slug)}
                        className="text-sm text-slate-500 hover:underline dark:text-slate-400"
                    >
                        Soru listesine dön
                    </Link>
                    {form.recentlySuccessful && (
                        <span
                            role="status"
                            className="text-sm text-emerald-600"
                        >
                            Kaydedildi.
                        </span>
                    )}
                </div>
            </form>
            <section
                aria-label="Soru önizlemesi"
                className="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 dark:border-slate-800 dark:bg-slate-900"
            >
                <h2 className="mb-5 text-lg font-semibold">Canlı önizleme</h2>
                <QuestionStem
                    number={1}
                    passage={form.data.passage || null}
                    text={form.data.text}
                />
                <ul className="mt-5 space-y-2">
                    {labels.map((label) => (
                        <li
                            key={label}
                            className={cn(
                                'rounded-xl border p-3 text-sm whitespace-pre-wrap',
                                form.data.correct_option === label
                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-100'
                                    : 'border-slate-200 dark:border-slate-700',
                            )}
                        >
                            <strong>{label})</strong> {form.data.options[label]}
                            {form.data.correct_option === label && (
                                <span className="ml-2 text-xs font-semibold">
                                    ✓ Doğru cevap
                                </span>
                            )}
                        </li>
                    ))}
                </ul>
            </section>
        </div>
    );
}

function QuestionEdit({ course, question }: Props) {
    return (
        <>
            <Head title={`${course.name} · Soru düzenle`} />
            <Link
                href={index.url(course.slug)}
                className="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
            >
                ← {course.name} · Sorular
            </Link>
            <h1 className="mt-3 text-2xl font-bold">Soru #{question.id}</h1>
            <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                {question.source}
                {question.pastExamTitle &&
                    ` · ${question.pastExamTitle} · Kitapçık sorusu ${question.pastExamNumber}`}
                {question.officialAnswer &&
                    ` · Kitapçık cevap anahtarı: ${question.officialAnswer}`}
            </p>
            {question.sourceUrl && (
                <a
                    href={
                        question.sourcePdfPage !== null
                            ? `${question.sourceUrl.split('#')[0]}#page=${question.sourcePdfPage}`
                            : question.sourceUrl
                    }
                    target="_blank"
                    rel="noreferrer"
                    className="mt-2 inline-block text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                >
                    Kaynağı aç ↗
                </a>
            )}
            <Editor key={question.id} course={course} question={question} />
        </>
    );
}

QuestionEdit.layout = (page: React.ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);
export default QuestionEdit;
