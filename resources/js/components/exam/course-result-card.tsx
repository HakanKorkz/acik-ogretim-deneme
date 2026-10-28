import { cn } from '@/lib/utils';

export interface CourseResult {
    position: number;
    course: string;
    subject: string | null;
    total: number;
    correct: number;
    wrong: number;
    blank: number;
    score: number;
    passed: boolean;
}

/**
 * Bir dersin puanı ve doğru/yanlış/boş dağılımı.
 */
export default function CourseResultCard({
    result,
    passScore,
}: {
    result: CourseResult;
    passScore: number;
}) {
    return (
        <section className="rounded-sm border border-study-rule bg-study-surface p-6">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p className="text-xs font-medium tracking-wide text-study-muted uppercase">
                        {result.subject}
                    </p>
                    <h2 className="mt-2 font-display text-3xl tracking-tight">
                        {result.course}
                    </h2>
                </div>
                <div className="text-right">
                    <p
                        className={cn(
                            'font-display text-6xl tracking-tight tabular-nums',
                            result.passed
                                ? 'text-emerald-600'
                                : 'text-rose-600',
                        )}
                    >
                        {result.score}
                    </p>
                    <p
                        className={cn(
                            'mt-1 inline-block rounded-full px-3 py-0.5 text-xs font-bold',
                            result.passed
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300'
                                : 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
                        )}
                    >
                        {result.passed ? 'BAŞARILI' : 'BAŞARISIZ'} (geçme notu{' '}
                        {passScore})
                    </p>
                </div>
            </div>
            <dl className="mt-7 grid grid-cols-4 divide-x divide-study-rule border-t border-study-rule pt-5 text-center">
                {[
                    ['Soru', result.total, ''],
                    ['Doğru', result.correct, 'text-emerald-600'],
                    ['Yanlış', result.wrong, 'text-rose-600'],
                    ['Boş', result.blank, 'text-study-muted'],
                ].map(([label, value, tone]) => (
                    <div key={label} className="py-3">
                        <dt className="text-xs text-study-muted">{label}</dt>
                        <dd
                            className={cn(
                                'text-2xl font-bold tabular-nums',
                                tone,
                            )}
                        >
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
        </section>
    );
}
