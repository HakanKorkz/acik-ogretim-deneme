import type { InputHTMLAttributes } from 'react';

interface FormFieldProps extends InputHTMLAttributes<HTMLInputElement> {
    label: string;
    name: string;
    error?: string;
}

export default function FormField({
    label,
    name,
    error,
    ...inputProps
}: FormFieldProps) {
    return (
        <label className="flex flex-col gap-1.5 text-sm">
            <span className="font-medium text-slate-700 dark:text-slate-200">
                {label}
            </span>
            <input
                name={name}
                aria-invalid={error ? true : undefined}
                className="rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-slate-900 shadow-sm transition outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 aria-invalid:border-rose-400 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                {...inputProps}
            />
            {error && (
                <span className="text-xs text-rose-600 dark:text-rose-400">
                    {error}
                </span>
            )}
        </label>
    );
}
