import FormField from '@/components/form-field';
import { store } from '@/routes/login';
import { Form, Head } from '@inertiajs/react';

interface Props {
    status?: string | null;
}

export default function Login({ status }: Props) {
    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 px-4 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <Head title="Yönetici Girişi" />

            <div className="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div className="mb-6 text-center">
                    <span className="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-lg font-bold text-white">
                        A
                    </span>
                    <h1 className="mt-4 text-xl font-bold tracking-tight">
                        Yönetici Girişi
                    </h1>
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Açık Lise Test yönetim paneli
                    </p>
                </div>

                {status && (
                    <p className="mb-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200">
                        {status}
                    </p>
                )}

                <Form
                    {...store.form()}
                    resetOnSuccess={['password']}
                    className="flex flex-col gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <FormField
                                label="E-posta"
                                name="email"
                                type="email"
                                autoComplete="username"
                                autoFocus
                                required
                                error={errors.email}
                            />
                            <FormField
                                label="Şifre"
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                required
                                error={errors.password}
                            />
                            <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    name="remember"
                                    className="rounded"
                                />
                                Beni hatırla
                            </label>
                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-2 rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                {processing
                                    ? 'Giriş yapılıyor...'
                                    : 'Giriş yap'}
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </div>
    );
}
