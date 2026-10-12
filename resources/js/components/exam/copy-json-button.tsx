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
                'rounded-xl border px-5 py-2.5 text-sm font-semibold shadow-sm transition',
                state === 'error'
                    ? 'border-rose-300 text-rose-700 dark:border-rose-800 dark:text-rose-300'
                    : 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200',
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
