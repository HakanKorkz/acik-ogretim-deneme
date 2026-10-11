import AppLayout from '@/layouts/app-layout';
import { index as coursesIndex } from '@/routes/courses';
import { Head, Link } from '@inertiajs/react';

interface Props {
    levels: { value: string; label: string; courses: number }[];
    questionsPerCourse: number;
    sectionMinutes: number;
    passScore: number;
}

function Home({
    levels,
    questionsPerCourse,
    sectionMinutes,
    passScore,
}: Props) {
    return (
        <>
            <Head title="Açık Öğretim Deneme Sınavı" />

            <section className="rounded-3xl bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-8 text-white shadow-xl shadow-indigo-500/20 sm:p-12">
                <p className="text-sm font-medium text-white/80">
                    Açık Öğretim Lisesi ve Ortaokulu
                </p>
                <h1 className="mt-2 max-w-2xl text-3xl font-bold tracking-tight sm:text-4xl">
                    Gerçek sınav düzeninde deneme sınavına gir
                </h1>
                <p className="mt-4 max-w-2xl text-sm text-white/85 sm:text-base">
                    Derslerini seç, her ders için {questionsPerCourse} soruluk
                    bölümü {sectionMinutes} dakikada çöz. Sorular MEB ders
                    kitaplarından ve çıkmış sınavlardan gelir; her sınavda
                    farklı sorularla karşılaşırsın. Geçme notu {passScore}.
                </p>
            </section>

            <h2 className="mt-10 text-lg font-semibold">
                Hangi sınava hazırlanıyorsun?
            </h2>

            <div className="mt-4 grid gap-6 sm:grid-cols-2">
                {levels.map((level) => (
                    <Link
                        key={level.value}
                        href={coursesIndex.url(level.value)}
                        className="group rounded-2xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-700"
                    >
                        <p className="text-xl font-bold tracking-tight">
                            {level.label}
                        </p>
                        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">
                            {level.courses > 0
                                ? `${level.courses} ders sınava hazır`
                                : 'Dersler hazırlanıyor'}
                        </p>
                        <span className="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 group-hover:gap-2 dark:text-indigo-400">
                            Derslerini seç <span aria-hidden>→</span>
                        </span>
                    </Link>
                ))}
            </div>
        </>
    );
}

Home.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;

export default Home;
