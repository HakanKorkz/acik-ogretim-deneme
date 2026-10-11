import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function FlashMessage() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(true);

    useEffect(() => {
        setVisible(true);
    }, [flash?.success, flash?.error]);

    if (!flash || (!flash.success && !flash.error) || !visible) {
        return null;
    }

    const isError = Boolean(flash.error);
    const message = flash.error ?? flash.success;

    return (
        <div
            role="status"
            className={`mb-6 flex items-start justify-between gap-4 rounded-xl border p-4 text-sm ${
                isError
                    ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/40 dark:text-rose-200'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/60 dark:bg-emerald-950/40 dark:text-emerald-200'
            }`}
        >
            <span>{message}</span>
            <button
                type="button"
                onClick={() => setVisible(false)}
                className="shrink-0 rounded-md px-1 text-lg leading-none opacity-60 transition hover:opacity-100"
                aria-label="Kapat"
            >
                ×
            </button>
        </div>
    );
}
