import {
    closestCenter,
    DndContext,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import {
    models as modelsRoute,
    test,
    update,
} from '@/routes/admin/settings/ai';
import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

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
        systemPrompt: string | null;
    };
    defaultSystemPrompt: string;
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

const secondaryButton =
    'rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

function GripIcon() {
    return (
        <svg viewBox="0 0 20 20" fill="currentColor" className="size-4">
            <circle cx="7" cy="5" r="1.4" />
            <circle cx="13" cy="5" r="1.4" />
            <circle cx="7" cy="10" r="1.4" />
            <circle cx="13" cy="10" r="1.4" />
            <circle cx="7" cy="15" r="1.4" />
            <circle cx="13" cy="15" r="1.4" />
        </svg>
    );
}

function SortableModel({ id, onRemove }: { id: string; onRemove: () => void }) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({ id });

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className={cn(
                'flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900',
                isDragging && 'opacity-60',
            )}
        >
            <button
                type="button"
                className="cursor-grab text-slate-400 hover:text-slate-600"
                aria-label={`${id} modelini sürükleyerek sırala`}
                {...attributes}
                {...listeners}
            >
                <GripIcon />
            </button>
            <code className="flex-1 truncate font-mono text-xs">{id}</code>
            <button
                type="button"
                onClick={onRemove}
                aria-label={`${id} modelini kaldır`}
                className="rounded-md px-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40"
            >
                Kaldır
            </button>
        </li>
    );
}

function AiSettings({
    settings,
    defaultSystemPrompt,
    providers,
    generator,
}: Props) {
    const form = useForm({
        provider: settings.provider,
        openrouter_key: '',
        openai_key: '',
        anthropic_key: '',
        clear_keys: [] as string[],
        models: settings.models,
        questions_per_request: settings.questionsPerRequest,
        system_prompt: settings.systemPrompt ?? defaultSystemPrompt,
    });

    const [testing, setTesting] = useState(false);
    const [candidate, setCandidate] = useState('');
    const [modelOptions, setModelOptions] = useState<string[]>(
        providers.find((option) => option.value === settings.provider)
            ?.suggestedModels ?? [],
    );
    const [modelsSource, setModelsSource] = useState<'live' | 'fallback'>(
        'fallback',
    );
    const [modelsError, setModelsError] = useState<string | null>(null);
    const [loadingModels, setLoadingModels] = useState(false);

    const selected = providers.find(
        (option) => option.value === form.data.provider,
    );
    const errors = form.errors as Record<string, string | undefined>;

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const currentKey = (): string => {
        if (form.data.provider === 'openrouter') {
            return form.data.openrouter_key;
        }

        if (form.data.provider === 'openai') {
            return form.data.openai_key;
        }

        if (form.data.provider === 'anthropic') {
            return form.data.anthropic_key;
        }

        return '';
    };

    const fetchModels = async () => {
        setLoadingModels(true);
        setModelsError(null);

        try {
            const response = await fetch(modelsRoute.url(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    provider: form.data.provider,
                    key: currentKey() || undefined,
                }),
            });

            const data = await response.json();
            setModelOptions(Array.isArray(data.models) ? data.models : []);
            setModelsSource(data.source === 'live' ? 'live' : 'fallback');
            setModelsError(typeof data.error === 'string' ? data.error : null);
        } catch {
            setModelsError('Model listesi alınamadı.');
        } finally {
            setLoadingModels(false);
        }
    };

    // Sağlayıcı değiştiğinde ya da sayfa açıldığında listeyi tazele.
    useEffect(() => {
        void fetchModels();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.provider]);

    const onDragEnd = ({ active, over }: DragEndEvent) => {
        if (!over || active.id === over.id) {
            return;
        }

        const from = form.data.models.indexOf(String(active.id));
        const to = form.data.models.indexOf(String(over.id));
        form.setData('models', arrayMove(form.data.models, from, to));
    };

    const addModel = () => {
        const id = candidate.trim();

        if (id === '' || form.data.models.includes(id)) {
            return;
        }

        form.setData('models', [...form.data.models, id]);
        setCandidate('');
    };

    const availableOptions = (
        modelOptions.length > 0
            ? modelOptions
            : (selected?.suggestedModels ?? [])
    ).filter((id) => !form.data.models.includes(id));

    const toggleClearKey = (value: string) => {
        form.setData(
            'clear_keys',
            form.data.clear_keys.includes(value)
                ? form.data.clear_keys.filter((item) => item !== value)
                : [...form.data.clear_keys, value],
        );
    };

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

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.put(update.url(), {
            preserveScroll: true,
            onSuccess: () =>
                form.setData({
                    ...form.data,
                    openrouter_key: '',
                    openai_key: '',
                    anthropic_key: '',
                }),
        });
    };

    return (
        <>
            <Head title="Yapay Zekâ Ayarları" />

            <h1 className="text-2xl font-bold tracking-tight">
                Yapay Zekâ Ayarları
            </h1>
            <p className="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                Soru bankası bu sağlayıcıyla üretilir. Modeller yukarıdan aşağı
                denenir; hiçbiri yanıt vermezse yerel üreticiye düşülür.
            </p>

            {generator === 'local' && (
                <p className="mt-4 max-w-2xl rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-200">
                    <code>EXAM_GENERATOR=local</code> ayarlı olduğu için bu
                    ayarlar şu an kullanılmıyor.
                </p>
            )}

            <form onSubmit={submit} className="mt-6 grid max-w-3xl gap-6">
                <section className="grid gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <label className="flex flex-col gap-1.5 text-sm">
                        <span className="font-medium">Sağlayıcı</span>
                        <select
                            value={form.data.provider}
                            onChange={(event) =>
                                form.setData('provider', event.target.value)
                            }
                            className={inputClass}
                        >
                            {providers.map((option) => (
                                <option key={option.value} value={option.value}>
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

                    <div className="grid gap-2">
                        <span className="text-sm font-medium">Model ekle</span>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            {modelsSource === 'live'
                                ? 'Sağlayıcıdan canlı liste.'
                                : 'Sağlayıcının önerdiği modeller.'}{' '}
                            İstediğin modeli ekle, sonra yukarıdan aşağı sırala.
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <select
                                value={candidate}
                                onChange={(event) =>
                                    setCandidate(event.target.value)
                                }
                                className={cn(inputClass, 'min-w-64 flex-1')}
                            >
                                <option value="">Model seçin…</option>
                                {availableOptions.map((id) => (
                                    <option key={id} value={id}>
                                        {id}
                                    </option>
                                ))}
                            </select>
                            <button
                                type="button"
                                onClick={fetchModels}
                                disabled={loadingModels}
                                className={secondaryButton}
                            >
                                {loadingModels
                                    ? 'Getiriliyor…'
                                    : 'Modelleri getir'}
                            </button>
                            <button
                                type="button"
                                onClick={addModel}
                                disabled={candidate === ''}
                                className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-50"
                            >
                                Ekle
                            </button>
                        </div>
                        {modelsError && (
                            <p
                                className={cn(
                                    'text-xs',
                                    modelsSource === 'fallback'
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : 'text-rose-600',
                                )}
                            >
                                {modelsError}
                            </p>
                        )}
                    </div>

                    <div className="grid gap-2">
                        <span className="text-sm font-medium">
                            Model sırası
                        </span>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Yukarıdan aşağı denenir; bir model yanıt vermezse
                            sıradaki denenir. Sürükleyerek sırala.
                        </p>
                        <DndContext
                            sensors={sensors}
                            collisionDetection={closestCenter}
                            onDragEnd={onDragEnd}
                        >
                            <SortableContext
                                items={form.data.models}
                                strategy={verticalListSortingStrategy}
                            >
                                <ol className="grid gap-2">
                                    {form.data.models.map((id) => (
                                        <SortableModel
                                            key={id}
                                            id={id}
                                            onRemove={() =>
                                                form.setData(
                                                    'models',
                                                    form.data.models.filter(
                                                        (model) => model !== id,
                                                    ),
                                                )
                                            }
                                        />
                                    ))}
                                </ol>
                            </SortableContext>
                        </DndContext>
                        {form.data.models.length === 0 && (
                            <p className="text-sm text-slate-500 dark:text-slate-400">
                                Henüz model eklenmedi. En az bir model gerekir.
                            </p>
                        )}
                        {(errors.models ?? errors['models.0']) && (
                            <span className="text-xs text-rose-600">
                                {errors.models ?? errors['models.0']}
                            </span>
                        )}
                    </div>

                    <label className="flex flex-col gap-1.5 text-sm">
                        <span className="font-medium">
                            İstek başına soru sayısı
                        </span>
                        <input
                            type="number"
                            min={1}
                            max={20}
                            value={form.data.questions_per_request}
                            onChange={(event) =>
                                form.setData(
                                    'questions_per_request',
                                    Number(event.target.value),
                                )
                            }
                            className={cn(inputClass, 'w-32')}
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
                            Anahtarlar şifreli saklanır ve bir daha gösterilmez.
                            Değiştirmek istemiyorsanız alanı boş bırakın.
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
                                type="password"
                                autoComplete="off"
                                value={
                                    form.data[
                                        `${option.value}_key` as
                                            | 'openrouter_key'
                                            | 'openai_key'
                                            | 'anthropic_key'
                                    ]
                                }
                                onChange={(event) =>
                                    form.setData(
                                        `${option.value}_key` as
                                            | 'openrouter_key'
                                            | 'openai_key'
                                            | 'anthropic_key',
                                        event.target.value,
                                    )
                                }
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
                                        checked={form.data.clear_keys.includes(
                                            option.value,
                                        )}
                                        onChange={() =>
                                            toggleClearKey(option.value)
                                        }
                                    />
                                    Paneldeki anahtarı sil
                                </label>
                            )}
                        </div>
                    ))}
                </section>

                <section className="grid gap-3 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-base font-semibold">
                                Sistem promptu
                            </h2>
                            <p className="mt-1 max-w-xl text-xs text-slate-500 dark:text-slate-400">
                                Soru üreten yapay zekâya verilen yönerge. Kod
                                içindeki varsayılan istemi değiştirip
                                kaydedebilir, istediğin zaman varsayılana
                                dönebilirsin.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={() =>
                                form.setData(
                                    'system_prompt',
                                    defaultSystemPrompt,
                                )
                            }
                            className={secondaryButton}
                        >
                            Varsayılana dön
                        </button>
                    </div>
                    <textarea
                        value={form.data.system_prompt}
                        onChange={(event) =>
                            form.setData('system_prompt', event.target.value)
                        }
                        rows={16}
                        spellCheck={false}
                        className={cn(
                            inputClass,
                            'font-mono text-xs leading-relaxed',
                        )}
                    />
                    {form.data.system_prompt.trim() ===
                        defaultSystemPrompt.trim() && (
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Şu an varsayılan istem görünüyor; kaydedersen
                            varsayılan kullanılmaya devam eder.
                        </p>
                    )}
                    {errors.system_prompt && (
                        <span className="text-xs text-rose-600">
                            {errors.system_prompt}
                        </span>
                    )}
                </section>

                <div className="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                    >
                        Kaydet
                    </button>
                    <button
                        type="button"
                        onClick={runTest}
                        disabled={testing}
                        className={secondaryButton}
                    >
                        {testing
                            ? 'Deneniyor…'
                            : 'Kayıtlı ayarlarla bağlantıyı dene'}
                    </button>
                </div>
            </form>
        </>
    );
}

AiSettings.layout = (page: React.ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);

export default AiSettings;
