import FormField from '@/components/form-field';
import AdminLayout from '@/layouts/admin-layout';
import { update } from '@/routes/admin/settings/media';
import { Form, Head } from '@inertiajs/react';

interface Props {
    settings: {
        defaultTarget: string;
        cloudName: string | null;
        folder: string | null;
        maxMegabytes: number;
        credentialsSet: boolean;
    };
    uploadMaxMegabytes: number;
}

function MediaSettings({ settings, uploadMaxMegabytes }: Props) {
    return (
        <>
            <Head title="Depolama Ayarları" />

            <h1 className="text-2xl font-bold tracking-tight">
                Depolama Ayarları
            </h1>
            <p className="mt-1 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
                Panelden yüklenen ders kitapları (en fazla {uploadMaxMegabytes}{' '}
                MB) için. MEB kataloğundaki kitaplar barındırılmaz.
                Cloudinary'de PDF'lerin dışarıya sunulması için hesabın Security
                ayarlarında "Allow delivery of PDF and ZIP files" açık
                olmalıdır; ücretsiz planda dosya başına sınır yaklaşık 10
                MB'tır.
            </p>

            <Form
                {...update.form()}
                options={{ preserveScroll: true }}
                className="mt-6 grid max-w-2xl gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900"
            >
                {({ errors, processing }) => (
                    <>
                        <fieldset className="flex flex-wrap gap-4 text-sm">
                            <legend className="mb-1 font-medium">
                                Varsayılan hedef
                            </legend>
                            <label className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="default_target"
                                    value="local"
                                    defaultChecked={
                                        settings.defaultTarget !== 'cloudinary'
                                    }
                                />
                                Sunucu
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="radio"
                                    name="default_target"
                                    value="cloudinary"
                                    defaultChecked={
                                        settings.defaultTarget === 'cloudinary'
                                    }
                                />
                                Cloudinary
                            </label>
                        </fieldset>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Cloud name"
                                name="cloudinary_cloud_name"
                                defaultValue={settings.cloudName ?? ''}
                                error={errors.cloudinary_cloud_name}
                            />
                            <FormField
                                label="Klasör"
                                name="cloudinary_folder"
                                defaultValue={settings.folder ?? ''}
                                error={errors.cloudinary_folder}
                            />
                            <FormField
                                label="API key"
                                name="cloudinary_api_key"
                                type="password"
                                autoComplete="off"
                                placeholder={
                                    settings.credentialsSet
                                        ? '•••••••• (değiştirmek için yazın)'
                                        : ''
                                }
                                error={errors.cloudinary_api_key}
                            />
                            <FormField
                                label="API secret"
                                name="cloudinary_api_secret"
                                type="password"
                                autoComplete="off"
                                placeholder={
                                    settings.credentialsSet
                                        ? '•••••••• (değiştirmek için yazın)'
                                        : ''
                                }
                                error={errors.cloudinary_api_secret}
                            />
                            <FormField
                                label="Cloudinary dosya sınırı (MB)"
                                name="cloudinary_max_megabytes"
                                type="number"
                                min={1}
                                max={100}
                                defaultValue={settings.maxMegabytes}
                                error={errors.cloudinary_max_megabytes}
                            />
                        </div>

                        {settings.credentialsSet && (
                            <label className="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    name="clear_credentials"
                                    value="1"
                                />
                                Kayıtlı API anahtarını ve sırrını sil
                            </label>
                        )}

                        <div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
                            >
                                Kaydet
                            </button>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

MediaSettings.layout = (page: React.ReactNode) => (
    <AdminLayout>{page}</AdminLayout>
);

export default MediaSettings;
