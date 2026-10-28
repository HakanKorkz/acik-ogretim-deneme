import AdminLayout from '@/layouts/admin-layout';
import { download } from '@/routes/admin/questions/export';
import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';

interface Choice {
    value: string;
    label: string;
}

interface Props {
    courses: { slug: string; name: string; level: string }[];
    levels: Choice[];
    sources: Choice[];
    stats: { total: number; eligible: number; excluded: number };
}

const fieldClass =
    'mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-slate-700 dark:bg-slate-950';

function Export({ courses, levels, sources, stats }: Props) {
    const { errors } = usePage().props;
    const [level, setLevel] = useState('');
    const [course, setCourse] = useState('');

    return (
        <>
            <Head title="Soruları Dışa Aktar" />
            <h1 className="text-2xl font-bold tracking-tight">
                Soruları Dışa Aktar
            </h1>
            <p className="mt-3 max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-400">
                Soru bankasını model eğitimi için Excel (.xlsx) olarak indirin.
                Her soru tek satırda yer alır; giriş metni ve öncüller soru
                hücresine dahil edilir.
            </p>

            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                {[
                    ['Bankadaki soru', stats.total],
                    ['Dışa aktarmaya uygun', stats.eligible],
                    ['Biçim kontrolünden geçmeyen', stats.excluded],
                ].map(([label, value]) => (
                    <div
                        key={label}
                        className="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            {label}
                        </p>
                        <p className="mt-2 text-3xl font-bold">{value}</p>
                    </div>
                ))}
            </div>
            <p className="mt-3 text-xs text-slate-500 dark:text-slate-400">
                Bu sayılar tüm bankayı gösterir. İndirilen dosyaya aşağıdaki
                filtreler uygulanır.
            </p>

            <form
                action={download.url()}
                method="get"
                className="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7 dark:border-slate-800 dark:bg-slate-900"
            >
                <h2 className="text-lg font-semibold">Aktarılacak sorular</h2>
                {Object.keys(errors).length > 0 && (
                    <div
                        role="alert"
                        className="mt-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-700 dark:bg-rose-950/40 dark:text-rose-300"
                    >
                        {Object.entries(errors).map(([key, message]) => (
                            <p key={key}>{message}</p>
                        ))}
                    </div>
                )}
                <div className="mt-5 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label
                            htmlFor="export-level"
                            className="text-sm font-medium"
                        >
                            Kademe
                        </label>
                        <select
                            id="export-level"
                            name="level"
                            value={level}
                            onChange={(event) => {
                                setLevel(event.target.value);
                                setCourse('');
                            }}
                            className={fieldClass}
                        >
                            <option value="">Tüm kademeler</option>
                            {levels.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label
                            htmlFor="export-course"
                            className="text-sm font-medium"
                        >
                            Ders
                        </label>
                        <select
                            id="export-course"
                            name="course"
                            value={course}
                            onChange={(event) => setCourse(event.target.value)}
                            className={fieldClass}
                        >
                            <option value="">Tüm dersler</option>
                            {courses
                                .filter(
                                    (item) => !level || item.level === level,
                                )
                                .map((item) => (
                                    <option key={item.slug} value={item.slug}>
                                        {item.name}
                                    </option>
                                ))}
                        </select>
                    </div>
                    <div>
                        <label
                            htmlFor="export-source"
                            className="text-sm font-medium"
                        >
                            Soru kaynağı
                        </label>
                        <select
                            id="export-source"
                            name="source"
                            className={fieldClass}
                        >
                            <option value="">Tüm kaynaklar</option>
                            {sources.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label
                            htmlFor="export-publication"
                            className="text-sm font-medium"
                        >
                            Dersin yayın durumu
                        </label>
                        <select
                            id="export-publication"
                            name="publication"
                            className={fieldClass}
                        >
                            <option value="all">
                                Yayındaki ve pasif dersler
                            </option>
                            <option value="active">
                                Yalnızca yayındaki dersler
                            </option>
                            <option value="inactive">
                                Yalnızca pasif dersler
                            </option>
                        </select>
                    </div>
                </div>

                <label className="mt-6 flex items-start gap-3 text-sm">
                    <input
                        type="checkbox"
                        name="metadata"
                        value="1"
                        className="mt-1 accent-indigo-600"
                    />
                    <span>
                        <span className="font-medium">
                            Kaynak bilgilerini de ekle
                        </span>
                        <span className="mt-1 block leading-6 text-slate-500 dark:text-slate-400">
                            Soru kimliği, kademe, kaynak türü, kaynak bağlantısı
                            ve kitap sayfası ek sütunlar olarak gelir. Veri
                            takibi ve aynı soruyu eğitim/test kümelerine tekrar
                            koymamak için kullanabilirsiniz.
                        </span>
                    </span>
                </label>
                <div className="mt-6 rounded-xl bg-amber-50 p-4 text-sm leading-6 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    Konu ve zorluk bilgisi mevcut sorularda kayıtlı değil; bu
                    sütunlar boş bırakılır. Tahminî etiket veya cevap üretilmez.
                    Biçim kontrolü içerik doğruluğunu garanti etmez; eğitimden
                    önce soruları gözden geçirin.
                </div>
                <button
                    type="submit"
                    disabled={stats.eligible === 0}
                    className="mt-6 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Excel dosyasını indir
                </button>
            </form>

            <section className="mt-6 rounded-2xl border border-slate-200 bg-white p-5 sm:p-7 dark:border-slate-800 dark:bg-slate-900">
                <h2 className="text-lg font-semibold">Dosya düzeni</h2>
                <div className="mt-4 flex flex-wrap gap-2">
                    {[
                        'ders',
                        'konu',
                        'zorluk',
                        'soru',
                        'A',
                        'B',
                        'C',
                        'D',
                        'cevap',
                    ].map((column) => (
                        <code
                            key={column}
                            className="rounded-md bg-slate-100 px-2.5 py-1 text-xs dark:bg-slate-800"
                        >
                            {column}
                        </code>
                    ))}
                </div>
                <ul className="mt-5 list-disc space-y-2 pl-5 text-sm leading-6 text-slate-600 dark:text-slate-400">
                    <li>
                        İlk satır sütun başlıklarıdır. Birleştirilmiş hücre, ara
                        başlık veya özet satırı bulunmaz.
                    </li>
                    <li>
                        Şıklar A–D sırasındadır. Cevap sütunu doğru şıkkın
                        harfini içerir; sınav ekranındaki rastgele şık sırası
                        kullanılmaz.
                    </li>
                    <li>
                        Arşivlenmiş, eksik şıklı, boş metinli veya doğru cevap
                        işaretlemesi tutarsız sorular aktarılmaz.
                    </li>
                    <li>
                        Türkçe karakterler ve satır sonları korunur. Hücreler
                        metin olarak yazılır; soru içeriği Excel formülü olarak
                        çalıştırılmaz.
                    </li>
                    <li>
                        Excel’in hücre sınırını aşan içerik kesilmez; veri
                        kaybını önlemek için indirme durdurulup hata gösterilir.
                    </li>
                </ul>
            </section>
        </>
    );
}

Export.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default Export;
