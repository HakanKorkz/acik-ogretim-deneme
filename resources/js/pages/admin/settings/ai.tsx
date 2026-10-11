import AdminLayout from '@/layouts/admin-layout';
import { test, update } from '@/routes/admin/settings/ai';
import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';

type KeySource = 'panel' | 'env' | 'none';

interface ProviderOption {
    value: string;
    label: string;
    keySource: KeySource;
    suggestedModels: string[];
}

interface Props {
    settings: {
        provider: string;
        models: string[];
        questionsPerRequest: number;
    };
    providers: ProviderOption[];
    generator: string;
}

const keySourceText: Record<KeySource, string> = {
    panel: 'Panelde kayıtlı',
    env: '.env dosyasından geliyor',
    none: 'Tanımlı değil',
};

const inputClass =
    'rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm text-slate-900 shadow-sm outline-none focus:border-indigo-500 focus:ring-4 focus:ring-indigo-500/15 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';

function AiSettings({ settings, providers, generator }: Props) {
    const [provider, setProvider] = useState(settings.provider);
    const [testing, setTesting] = useState(false);
    const selected = providers.find((option) => option.value === provider);

    const runTest = () => {
        router.post(
            test.url(),
            {},
            {
                preserveScroll: true,
                onStart: () => setTesting(true),
                onFinish: () => setTesting(false),
            },
        );
    };

    return (
        <>
            <Head title="Yapay Zekâ Ayarları" />

            <h1 className="text-2xl font-bold tracking-tight">
                Yapay Zekâ Ayarları
            </h1>
            <p className="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                Soru bankası bu sağlayıcıyla üretilir. Modeller yazıldığı
                sırayla denenir; hiçbiri yanıt vermezse yerel üreticiye düşülür.
            </p>

            {generator === 'local' && (
                <p className="mt-4 max-w-2xl rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                    <code>EXAM_GENERATOR=local</code> ayarlı olduğu için bu
                    ayarlar şu an kullanılmıyor.
                </p>
            )}

            <Form
                {...update.form()}
                options={{ preserveScroll: true }}
                className="mt-6 grid max-w-2xl gap-6"
            >
                {({ errors, processing }) => (
                    <>
                        <section className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="font-medium">Sağlayıcı</span>
                                <select
                                    name="provider"
                                    value={provider}
                                    onChange={(event) =>
                                        setProvider(event.target.value)
                                    }
                                    className={inputClass}
                                >
                                    {providers.map((option) => (
                                        <option
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.provider && (
                                    <span className="text-xs text-rose-600">
                                        {errors.provider}
                                    </span>
                                )}
                            </label>

                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="font-medium">
                                    Modeller (her satıra bir model, sırayla
                                    denenir)
                                </span>
                                <textarea
                                    name="models"
                                    rows={4}
                                    defaultValue={settings.models.join('\n')}
                                    placeholder={selected?.suggestedModels.join(
                                        '\n',
                                    )}
                                    className={`${inputClass} font-mono`}
                                />
                                {(errors.models ?? errors['models.0']) && (
                                    <span className="text-xs text-rose-600">
                                        {errors.models ?? errors['models.0']}
                                    </span>
                                )}
                            </label>

                            <label className="flex flex-col gap-1.5 text-sm">
                                <span className="font-medium">
                                    İstek başına soru sayısı
                                </span>
                                <input
                                    name="questions_per_request"
                                    type="number"
                                    min={1}
                                    max={20}
                                    defaultValue={settings.questionsPerRequest}
                                    className={`${inputClass} w-32`}
                                />
                                {errors.questions_per_request && (
                                    <span className="text-xs text-rose-600">
                                        {errors.questions_per_request}
                                    </span>
                                )}
                            </label>
                        </section>

                        <section className="grid gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <div>
                                <h2 className="text-base font-semibold">
                                    API anahtarları
                                </h2>
                                <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Anahtarlar şifreli saklanır ve bir daha
                                    gösterilmez. Değiştirmek istemiyorsanız
                                    alanı boş bırakın.
                                </p>
                            </div>

                            {providers.map((option) => (
                                <div
                                    key={option.value}
                                    className="flex flex-col gap-1.5 text-sm"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <span className="font-medium">
                                            {option.label}
                                        </span>
                                        <span className="text-xs text-slate-500 dark:text-slate-400">
                                            {keySourceText[option.keySource]}
                                        </span>
                                    </div>
                                    <input
                                        name={`${option.value}_key`}
                                        type="password"
                                        autoComplete="off"
                                        placeholder={
                                            option.keySource === 'none'
                                                ? 'Anahtarı yapıştırın'
                                                : '•••••••• (değiştirmek için yazın)'
                                        }
                                        className={inputClass}
                                    />
                                    {option.keySource === 'panel' && (
                                        <label className="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                                            <input
                                                type="checkbox"
                                                name="clear_keys[]"
                                                value={option.value}
                                            />
                                            Paneldeki anahtarı sil
                                        </label>
                                    )}
                                </div>
                            ))}
                        </section>

                        <div className="flex flex-wrap items-center gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                Kaydet
                            </button>
                            <button
                                type="button"
                                onClick={runTest}
                                disabled={testing}
                                className="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                            >
                                {testing
                                    ? 'Deneniyor...'
                                    : 'Kayıtlı ayarlarla bağlantıyı dene'}
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

AiSettings.layout = (page: React.ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);

export default AiSettings;
