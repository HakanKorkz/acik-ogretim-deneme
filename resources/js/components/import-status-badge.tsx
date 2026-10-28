import { cn } from '@/lib/utils';

const tones: Record<string, string> = {
    done: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    failed: 'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300',
    queued: 'animate-pulse bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    running:
        'animate-pulse bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300',
    skipped:
        'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};

export default function ImportStatusBadge({
    status,
    label,
}: {
    status: string | null;
    label: string;
}) {
    return (
        <span
            className={cn(
                'rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                (status && tones[status]) ||
                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            )}
        >
            {label}
        </span>
    );
}
