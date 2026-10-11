export interface UploadOptions {
    defaultTarget: string;
    cloudinaryConfigured: boolean;
    cloudinaryMaxMegabytes: number;
    maxMegabytes: number;
}

/**
 * PDF seçimi ve depolama hedefi. Cloudinary sınırını aşan ya da Cloudinary
 * yapılandırılmamışken seçilen dosya sunucuya kaydedilir; bu önceden söylenir.
 */
export default function BookUploadFields({
    upload,
    error,
    progress,
}: {
    upload: UploadOptions;
    error?: string;
    progress?: { percentage?: number } | null;
}) {
    return (
        <div className="grid gap-3 text-sm">
            <label className="flex flex-col gap-1.5">
                <span className="font-medium">
                    Ders kitabı (PDF, en fazla {upload.maxMegabytes} MB)
                </span>
                <input
                    type="file"
                    name="book"
                    accept="application/pdf"
                    required
                    className="rounded-xl border border-slate-300 bg-white px-3 py-2 dark:border-slate-700 dark:bg-slate-900"
                />
                {error && (
                    <span className="text-xs text-rose-600">{error}</span>
                )}
            </label>

            <fieldset className="flex flex-wrap gap-4">
                <legend className="mb-1 font-medium">
                    Dosya nerede saklansın?
                </legend>
                <label className="flex items-center gap-2">
                    <input
                        type="radio"
                        name="target"
                        value="local"
                        defaultChecked={upload.defaultTarget !== 'cloudinary'}
                    />
                    Sunucu
                </label>
                <label className="flex items-center gap-2">
                    <input
                        type="radio"
                        name="target"
                        value="cloudinary"
                        defaultChecked={upload.defaultTarget === 'cloudinary'}
                        disabled={!upload.cloudinaryConfigured}
                    />
                    Cloudinary (en fazla {upload.cloudinaryMaxMegabytes} MB)
                </label>
            </fieldset>
            <p className="text-xs text-slate-500 dark:text-slate-400">
                {upload.cloudinaryConfigured
                    ? `${upload.cloudinaryMaxMegabytes} MB'tan büyük dosyalar sunucuya kaydedilir.`
                    : 'Cloudinary yapılandırılmadı; Depolama ayarlarından ekleyebilirsiniz.'}{' '}
                Soru üretimi PDF'in metnini kullanır; dosya yalnızca yeniden
                işleme için saklanır.
            </p>

            {progress && (
                <progress
                    value={progress.percentage ?? 0}
                    max={100}
                    className="h-2 w-full"
                >
                    {progress.percentage}%
                </progress>
            )}
        </div>
    );
}
