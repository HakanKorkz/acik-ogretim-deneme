import StudyArrow from '@/components/study-arrow';
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
                className="inline-flex min-h-11 items-center gap-2 text-xs text-study-muted hover:text-study-ink"
            >
                ← Kademe seçimi
            </Link>
            <div className="mt-5 flex flex-wrap items-end justify-between gap-6 border-b border-study-rule pb-8">
                <div>
                    <p className="study-eyebrow">
                        {level.label} · Kendi denemeni oluştur
                    </p>
                    <h1 className="mt-4 font-display text-5xl leading-tight tracking-[-0.04em] sm:text-6xl">
                        {step === 'courses'
                            ? 'Bugün ne çalışalım?'
                            : 'Son bir hazırlık.'}
                    </h1>
                </div>
                <ol
                    aria-label="Sınav hazırlık adımları"
                    className="flex gap-6 text-xs"
                >
                    <li
                        aria-current={step === 'courses' ? 'step' : undefined}
                        className={
                            step === 'courses'
                                ? 'font-semibold text-study-ink'
                                : 'text-study-muted'
                        }
                    >
                        <span className="mr-2 font-display text-xl">01</span>
                        Dersler
                    </li>
                    <li
                        aria-current={step === 'options' ? 'step' : undefined}
                        className={
                            step === 'options'
                                ? 'font-semibold text-study-ink'
                                : 'text-study-muted'
                        }
                    >
                        <span className="mr-2 font-display text-xl">02</span>
                        Tercihler
                    </li>
                </ol>
            </div>
            <div className="mt-6">
                <FlashMessage />
            </div>
            {Object.keys(errors).length > 0 && (
                <div
                    role="alert"
                    className="mb-6 border-l-2 border-study-accent bg-study-surface p-4 text-sm text-study-accent"
                >
                    {Object.values(errors).map((error) => (
                        <p key={error}>{error}</p>
                    ))}
                </div>
            )}
            <div className="grid items-start gap-10 lg:grid-cols-[minmax(0,1fr)_300px] lg:gap-14">
                <div>
                    <p className="mb-7 max-w-xl text-sm leading-7 text-study-muted">
                        {step === 'courses'
                            ? `Bir ya da birkaç dersle başlayabilirsin. En fazla ${maxCourses} ders seç; her birini sırayla, ${questionsPerCourse} soru ve ${sectionMinutes} dakika üzerinden çöz.`
                            : 'Denemenin soru kaynağını ve sonuçları nasıl görmek istediğini belirle. Ardından sınav kitapçığın hazır.'}
                    </p>
                    {step === 'courses' ? (
                        <div className="space-y-9">
                            {courses.length === 0 && (
                                <div className="study-panel border-dashed text-sm text-study-muted">
                                    Bu kademede henüz sınava hazır ders yok.
                                    İçerikler hazır olduğunda burada görünecek.
                                </div>
                            )}
                            {groups.map(([subject, items], groupIndex) => (
                                <section key={subject}>
                                    <h2 className="mb-3 flex items-center gap-3 text-xs font-semibold tracking-wide uppercase">
                                        <span className="font-display text-lg font-normal text-study-accent">
                                            {String(groupIndex + 1).padStart(
                                                2,
                                                '0',
                                            )}
                                        </span>
                                        {subject}
                                    </h2>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {items.map((course) => {
                                            const isSelected =
                                                selected.includes(course.slug);
                                            const total =
                                                course.pastCount +
                                                course.generatedCount;
                                            const isDisabled =
                                                total === 0 ||
                                                (!isSelected &&
                                                    selected.length >=
                                                        maxCourses);
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
                                                        'flex min-h-24 items-center justify-between gap-4 border p-5 text-left transition disabled:cursor-not-allowed disabled:opacity-45',
                                                        isSelected
                                                            ? 'border-study-ink bg-study-tint'
                                                            : 'border-study-rule bg-study-surface hover:border-study-ink',
                                                    )}
                                                >
                                                    <span>
                                                        <span className="block text-sm font-semibold">
                                                            {course.name}
                                                        </span>
                                                        <span className="mt-1 block text-[11px] leading-5 text-study-muted">
                                                            {total === 0
                                                                ? 'Sorular hazırlanıyor'
                                                                : course.pastCount >
                                                                    0
                                                                  ? `${course.pastCount} çıkmış · ${course.generatedCount} çalışma sorusu`
                                                                  : `${course.generatedCount} çalışma sorusu`}
                                                        </span>
                                                    </span>
                                                    <span
                                                        aria-hidden="true"
                                                        className={cn(
                                                            'flex h-6 w-6 shrink-0 items-center justify-center border text-xs',
                                                            isSelected
                                                                ? 'border-study-fill bg-study-fill text-study-on-fill'
                                                                : 'border-study-muted',
                                                        )}
                                                    >
                                                        {isSelected ? '✓' : '+'}
                                                    </span>
                                                </button>
                                            );
                                        })}
                                    </div>
                                </section>
                            ))}
                        </div>
                    ) : (
                        <div className="space-y-7">
                            <fieldset className="study-panel">
                                <legend className="px-2 font-display text-2xl">
                                    Soru kaynağın
                                </legend>
                                <div className="grid gap-3">
                                    {pools.map((option) => (
                                        <label
                                            key={option.value}
                                            className={cn(
                                                'flex min-h-14 cursor-pointer items-center gap-3 border p-4 text-sm',
                                                pool === option.value
                                                    ? 'border-study-ink bg-study-tint'
                                                    : 'border-study-rule',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="pool"
                                                value={option.value}
                                                checked={pool === option.value}
                                                onChange={() =>
                                                    setPool(option.value)
                                                }
                                                className="h-4 w-4"
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </div>
                                {missingInPool.length ===
                                    selectedCourses.length && (
                                    <p
                                        role="status"
                                        className="mt-4 text-sm text-study-accent"
                                    >
                                        Seçtiğin derslerde bu türden soru yok;
                                        başka bir soru türü seç.
                                    </p>
                                )}
                            </fieldset>
                            <fieldset className="study-panel">
                                <legend className="px-2 font-display text-2xl">
                                    Sonuçları ne zaman görelim?
                                </legend>
                                <div className="grid gap-3">
                                    {resultModes.map((option) => (
                                        <label
                                            key={option.value}
                                            className={cn(
                                                'flex min-h-14 cursor-pointer items-center gap-3 border p-4 text-sm',
                                                resultMode === option.value
                                                    ? 'border-study-ink bg-study-tint'
                                                    : 'border-study-rule',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="result_mode"
                                                value={option.value}
                                                checked={
                                                    resultMode === option.value
                                                }
                                                onChange={() =>
                                                    setResultMode(option.value)
                                                }
                                                className="h-4 w-4"
                                            />
                                            {option.label}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                            <button
                                type="button"
                                onClick={() => setStep('courses')}
                                className="study-button study-button-secondary"
                            >
                                ← Derslere dön
                            </button>
                        </div>
                    )}
                </div>
                <aside
                    aria-label="Deneme özeti"
                    className="border-t-2 border-study-ink bg-study-tint p-6 lg:sticky lg:top-24"
                >
                    <p className="study-eyebrow text-study-muted">
                        Senin çalışma planın
                    </p>
                    <h2 className="mt-3 font-display text-3xl tracking-tight">
                        Deneme defterin.
                    </h2>
                    <p role="status" className="mt-3 text-xs text-study-muted">
                        {selected.length} / {maxCourses} ders seçildi
                    </p>
                    {selectedCourses.length > 0 ? (
                        <ol className="mt-6 divide-y divide-study-rule border-y border-study-rule">
                            {selectedCourses.map((course, index) => (
                                <li
                                    key={course.slug}
                                    className="flex gap-3 py-3 text-sm"
                                >
                                    <span className="text-xs text-study-muted">
                                        {String(index + 1).padStart(2, '0')}
                                    </span>
                                    <span>
                                        {course.name}
                                        {step === 'options' &&
                                            availableIn(course, pool) === 0 && (
                                                <span className="mt-1 block text-xs text-study-accent">
                                                    Bu kaynakta soru yok;
                                                    atlanacak.
                                                </span>
                                            )}
                                    </span>
                                </li>
                            ))}
                        </ol>
                    ) : (
                        <p className="mt-6 border-y border-study-rule py-6 text-sm leading-6 text-study-muted">
                            Derslerden birini seçerek başlayabilirsin.
                        </p>
                    )}
                    <dl className="mt-5 flex justify-between text-xs text-study-muted">
                        <div>
                            <dt>Her ders</dt>
                            <dd className="mt-1 text-sm font-semibold text-study-ink">
                                {questionsPerCourse} soru
                            </dd>
                        </div>
                        <div className="text-right">
                            <dt>Süre</dt>
                            <dd className="mt-1 text-sm font-semibold text-study-ink">
                                {sectionMinutes} dakika / ders
                            </dd>
                        </div>
                    </dl>
                    {step === 'courses' ? (
                        <button
                            type="button"
                            onClick={() => setStep('options')}
                            disabled={selected.length === 0}
                            className="study-button mt-7 w-full justify-between"
                        >
                            Tercihlere geç<span aria-hidden="true">→</span>
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={submit}
                            disabled={
                                submitting ||
                                missingInPool.length === selectedCourses.length
                            }
                            className="study-button mt-7 w-full justify-between"
                        >
                            {submitting ? 'Hazırlanıyor…' : 'Kitapçığı oluştur'}
                            <StudyArrow />
                        </button>
                    )}
                    <p className="mt-4 text-[11px] leading-5 text-study-muted">
                        Süren, kitapçık hazırlandıktan sonra Başlayınız
                        düğmesine bastığında başlar.
                    </p>
                </aside>
            </div>
        </>
    );
}

CourseSelection.layout = (page: React.ReactNode) => (
    <AppLayout>{page}</AppLayout>
);

export default CourseSelection;
