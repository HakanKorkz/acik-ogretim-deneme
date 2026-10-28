import { copyJson, type ExamResultJson } from '@/lib/exam';
import { cn } from '@/lib/utils';
import { useState } from 'react';

/**
 * Sınav sonucunu JSON olarak panoya kopyalar. Sonucu bir yapay zekâya verip
 * değerlendirmek için kullanılır.
 */
export default function CopyJsonButton({
    build,
    className,
}: {
    build: () => ExamResultJson;
    className?: string;
}) {
    const [state, setState] = useState<'idle' | 'copied' | 'error'>('idle');

    const copy = async () => {
        const ok = await copyJson(build());
        setState(ok ? 'copied' : 'error');
        window.setTimeout(() => setState('idle'), 2000);
    };

    return (
        <button
            type="button"
            onClick={copy}
            className={cn(
                'rounded-sm border px-5 py-2.5 text-sm font-semibold transition',
                state === 'error'
                    ? 'border-rose-300 text-rose-700 dark:border-rose-800 dark:text-rose-300'
                    : 'border-study-rule bg-study-surface text-study-muted hover:bg-study-tint',
                className,
            )}
        >
            {state === 'copied'
                ? 'Kopyalandı ✓'
                : state === 'error'
                  ? 'Kopyalanamadı'
                  : 'JSON kopyala'}
        </button>
    );
}
