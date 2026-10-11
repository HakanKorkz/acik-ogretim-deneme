import FlashMessage from '@/components/flash-message';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import { store as startExam } from '@/routes/exams';
import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

interface CourseItem {
    slug: string;
    name: string;
    subject: string | null;
    pastCount: number;
    generatedCount: number;
}

interface Choice {
    value: string;
    label: string;
}

interface Props {
    level: { value: string; label: string };
    courses: CourseItem[];
    pools: Choice[];
    resultModes: Choice[];
    maxCourses: number;
    questionsPerCourse: number;
    sectionMinutes: number;
}

function availableIn(course: CourseItem, pool: string): number {
    if (pool === 'past_exam') {
        return course.pastCount;
    }

    if (pool === 'generated') {
        return course.generatedCount;
    }

    return course.pastCount + course.generatedCount;
}

function CourseSelection({
    level,
    courses,
    pools,
    resultModes,
    maxCourses,
    questionsPerCourse,
    sectionMinutes,
}: Props) {
    const [selected, setSelected] = useState<string[]>([]);
    const [step, setStep] = useState<'courses' | 'options'>('courses');
    const [pool, setPool] = useState('mixed');
    const [resultMode, setResultMode] = useState('per_course');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    const groups = useMemo(() => {
        const bySubject = new Map<string, CourseItem[]>();

        for (const course of courses) {
            const subject = course.subject ?? 'Diğer';
            bySubject.set(subject, [...(bySubject.get(subject) ?? []), course]);
        }

        return [...bySubject.entries()];
    }, [courses]);

    const selectedCourses = courses.filter((course) =>
        selected.includes(course.slug),
    );
    const missingInPool = selectedCourses.filter(
        (course) => availableIn(course, pool) === 0,
    );

    const toggle = (slug: string) => {
        setSelected((current) =>
            current.includes(slug)
                ? current.filter((item) => item !== slug)
                : current.length >= maxCourses
                  ? current
                  : [...current, slug],
        );
    };

    const submit = () => {
        router.post(
            startExam.url(),
            {
                level: level.value,
                courses: selected,
                pool,
                result_mode: resultMode,
            },
            {
                onStart: () => setSubmitting(true),
                onFinish: () => setSubmitting(false),
                onError: (validationErrors) => setErrors(validationErrors),
            },
        );
    };

    return (
        <>
            <Head title={`${level.label} - Ders Seçimi`} />

            <Link
                href={home.url()}
                className="text-sm text-indigo-600 hover:underline dark:text-indigo-400"
            >
                ← Kademe seçimi
            </Link>
            <h1 className="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">
                {level.label}
            </h1>
            <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {step === 'courses'
                    ? `Sınava gireceğin dersleri seç (en az 1, en fazla ${maxCourses}). Dersler sırayla, her biri ${questionsPerCourse} soru ve ${sectionMinutes} dakika olarak çözülür.`
                    : 'Soru türünü ve sonuçların nasıl gösterileceğini seç.'}
            </p>

            <div className="mt-6">
                <FlashMessage />
            </div>

            {step === 'courses' ? (
                <>
                    {courses.length === 0 && (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500 dark:border-slate-700 dark:bg-slate-900">
                            Bu kademede henüz sınava hazır ders yok.
                        </div>
                    )}

                    <div className="grid gap-6">
                        {groups.map(([subject, items]) => (
                            <section key={subject}>
                                <h2 className="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase dark:text-slate-400">
                                    {subject}
                                </h2>
                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {items.map((course) => {
                                        const isSelected = selected.includes(
                                            course.slug,
                                        );
                                        const total =
                                            course.pastCount +
                                            course.generatedCount;
                                        const isDisabled =
                                            total === 0 ||
                                            (!isSelected &&
                                                selected.length >= maxCourses);

                                        return (
                                            <button
                                                key={course.slug}
                                                type="button"
                                                onClick={() =>
                                                    toggle(course.slug)
                                                }
                                                disabled={isDisabled}
                                                aria-pressed={isSelected}
                                                className={cn(
                                                    'flex items-center justify-between gap-3 rounded-xl border px-4 py-3 text-left text-sm transition disabled:cursor-not-allowed disabled:opacity-50',
                                                    isSelected
                                                        ? 'border-indigo-500 bg-indigo-50 ring-2 ring-indigo-500/20 dark:bg-indigo-950/40'
                                                        : 'border-slate-200 bg-white hover:border-indigo-300 dark:border-slate-800 dark:bg-slate-900',
                                                )}
                                            >
                                                <span>
                                                    <span className="block font-medium">
                                                        {course.name}
                                                    </span>
                                                    <span className="text-xs text-slate-500 dark:text-slate-400">
                                                        {total === 0
                                                            ? 'Sorular hazırlanıyor'
                                                            : course.pastCount >
                                                                0
                                                              ? `${course.pastCount} çıkmış soru · ${course.generatedCount} soru`
                                                              : `${course.generatedCount} soru`}
                                                    </span>
                                                </span>
                                                <span
                                                    className={cn(
                                                        'flex h-5 w-5 shrink-0 items-center justify-center rounded-md border text-xs font-bold',
                                                        isSelected
                                                            ? 'border-indigo-600 bg-indigo-600 text-white'
                                                            : 'border-slate-300 dark:border-slate-600',
                                                    )}
                                                >
                                                    {isSelected ? '✓' : ''}
                                                </span>
                                            </button>
                                        );
                                    })}
                                </div>
                            </section>
                        ))}
                    </div>

                    <div className="sticky bottom-4 mt-8 flex items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
                        <p className="text-sm">
                            <strong>{selected.length}</strong> / {maxCourses}{' '}
                            ders seçildi
                        </p>
                        <button
                            type="button"
                            onClick={() => setStep('options')}
                            disabled={selected.length === 0}
                            className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            Devam et →
                        </button>
                    </div>
                </>
            ) : (
                <div className="grid max-w-3xl gap-6">
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h2 className="font-semibold">
                            Seçilen dersler (çözülme sırası)
                        </h2>
                        <ol className="mt-3 grid gap-1 text-sm">
                            {selectedCourses.map((course, index) => (
                                <li key={course.slug}>
                                    {index + 1}. {course.name}
                                    {availableIn(course, pool) === 0 && (
                                        <span className="ml-2 text-xs text-amber-600 dark:text-amber-400">
                                            (bu soru türünde sorusu yok,
                                            atlanacak)
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ol>
                        <button
                            type="button"
                            onClick={() => setStep('courses')}
                            className="mt-3 text-sm text-indigo-600 hover:underline dark:text-indigo-400"
                        >
                            Dersleri değiştir
                        </button>
                        {errors.courses && (
                            <p className="mt-2 text-sm text-rose-600">
                                {errors.courses}
                            </p>
                        )}
                    </section>

                    <fieldset className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <legend className="px-1 font-semibold">
                            Soru türü
                        </legend>
                        <div className="mt-2 grid gap-2">
                            {pools.map((option) => (
                                <label
                                    key={option.value}
                                    className="flex items-center gap-3 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="pool"
                                        value={option.value}
                                        checked={pool === option.value}
                                        onChange={() => setPool(option.value)}
                                    />
                                    {option.label}
                                </label>
                            ))}
                        </div>
                        {missingInPool.length === selectedCourses.length && (
                            <p className="mt-3 text-sm text-amber-700 dark:text-amber-400">
                                Seçtiğin derslerde bu türden soru yok; başka bir
                                soru türü seç.
                            </p>
                        )}
                    </fieldset>

                    <fieldset className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <legend className="px-1 font-semibold">Sonuçlar</legend>
                        <div className="mt-2 grid gap-2">
                            {resultModes.map((option) => (
                                <label
                                    key={option.value}
                                    className="flex items-center gap-3 text-sm"
                                >
                                    <input
                                        type="radio"
                                        name="result_mode"
                                        value={option.value}
                                        checked={resultMode === option.value}
                                        onChange={() =>
                                            setResultMode(option.value)
                                        }
                                    />
                                    {option.label}
                                </label>
                            ))}
                        </div>
                    </fieldset>

                    <div className="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            onClick={() => setStep('courses')}
                            className="rounded-xl border border-slate-300 px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200"
                        >
                            ← Geri
                        </button>
                        <button
                            type="button"
                            onClick={submit}
                            disabled={
                                submitting ||
                                missingInPool.length === selectedCourses.length
                            }
                            className="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {submitting
                                ? 'Hazırlanıyor...'
                                : 'Sınav kitapçığını oluştur'}
                        </button>
                    </div>
                </div>
            )}
        </>
    );
}

CourseSelection.layout = (page: React.ReactNode) => (
    <AppLayout>{page}</AppLayout>
);

export default CourseSelection;
