import StudyArrow from '@/components/study-arrow';
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
            <Head title="Açık Öğretim Deneme Sınavı">
                <meta
                    name="description"
                    content="Açık öğretim lise ve ortaokul sınavlarına kendi hızında hazırlan. Derslerini seç, süreli denemeni çöz, cevaplarını incele."
                />
            </Head>
            <section className="study-hero grid items-center gap-12 pt-3 pb-12 lg:grid-cols-[1.05fr_1fr] lg:gap-10 lg:pt-6 lg:pb-16">
                <div className="study-enter">
                    <p className="study-eyebrow">
                        <span className="mr-3 inline-block h-1.5 w-1.5 rounded-full bg-study-accent" />
                        Açık öğretim · Lise & Ortaokul
                    </p>
                    <h1 className="mt-7 font-display text-[clamp(3.5rem,7vw,6.6rem)] leading-[1.02] tracking-[-0.055em]">
                        Geleceğine
                        <br />
                        bir deneme
                        <br />
                        <em className="font-normal text-study-accent">
                            daha yakın.
                        </em>
                    </h1>
                    <p className="mt-7 max-w-sm text-[15px] leading-7 text-study-muted">
                        Nerede kaldığın değil, nereden başlayacağın önemli.
                        Derslerini seç, sınava hazırlan ve her denemede kendini
                        biraz daha tanı.
                    </p>
                    <div className="mt-8 flex flex-wrap items-center gap-5">
                        <a href="#sinavlar" className="study-button">
                            İlk adımı at
                            <StudyArrow />
                        </a>
                        <span className="text-xs leading-5 text-study-muted">
                            Kendi derslerin.
                            <br />
                            Kendi çalışma ritmin.
                        </span>
                    </div>
                </div>
                <div
                    role="img"
                    className="study-book-scene study-enter"
                    aria-label={`Deneme düzeni: ders başına ${questionsPerCourse} soru, ${sectionMinutes} dakika. Geçme notu ${passScore}.`}
                >
                    <div className="study-scene-caption" aria-hidden="true">
                        <span>ÇALIŞMA MASAN</span>
                        <span>01 / HAZIRLIK</span>
                    </div>
                    <div className="study-book-back" aria-hidden="true">
                        <span>AÇIK ÖĞRETİM</span>
                        <div className="study-cover-arches">
                            <i />
                            <i />
                            <i />
                            <i />
                        </div>
                        <span>
                            Bir sonraki
                            <br />
                            sayfa senin.
                        </span>
                    </div>
                    <div className="study-book-front" aria-hidden="true">
                        <div className="flex items-center justify-between border-b border-[#bfc4b6] pb-3 text-[9px] tracking-[0.14em]">
                            <span>DENEME DEFTERİ</span>
                            <span>AÖ / 01</span>
                        </div>
                        <p className="mt-5 font-display text-[clamp(2.5rem,4.1vw,3.3rem)] leading-none tracking-[-0.04em]">
                            Bilgini
                            <br />
                            gör.
                            <br />
                            <em>
                                Kendine
                                <br />
                                güven.
                            </em>
                        </p>
                        <div className="mt-auto border-t border-[#bfc4b6] pt-4">
                            <div className="flex justify-between text-[10px]">
                                <span>{questionsPerCourse} soru / ders</span>
                                <span>{sectionMinutes} dakika</span>
                            </div>
                            <div className="mt-3 flex gap-2">
                                {['A', 'B', 'C', 'D'].map((letter) => (
                                    <span
                                        key={letter}
                                        className={`flex h-6 w-6 items-center justify-center rounded-full border text-[9px] ${letter === 'B' ? 'border-[#203d32] bg-[#203d32] text-[#f5f2e9]' : 'border-[#a9b2a3]'}`}
                                    >
                                        {letter}
                                    </span>
                                ))}
                            </div>
                        </div>
                    </div>
                    <div className="study-book-note" aria-hidden="true">
                        Hazır hissetmenin
                        <br />
                        <span className="font-display text-2xl italic">
                            ilk adımı.
                        </span>
                    </div>
                </div>
            </section>

            <dl className="grid grid-cols-3 divide-x divide-study-rule border-y border-study-rule py-6 sm:py-7">
                {[
                    [
                        questionsPerCourse,
                        'soru / ders',
                        'Odaklanmak için yeterli.',
                    ],
                    [sectionMinutes, 'dakika / ders', 'Zamanını tanı.'],
                    [passScore, 'geçme notu', 'Hedefin belli.'],
                ].map(([value, label, note]) => (
                    <div key={label} className="px-3 first:pl-0 sm:px-8">
                        <dt className="flex flex-col gap-1 sm:flex-row sm:items-baseline sm:gap-3">
                            <span className="font-display text-4xl tracking-tight sm:text-5xl">
                                {value}
                            </span>
                            <span className="text-xs text-study-muted sm:text-sm">
                                {label}
                            </span>
                        </dt>
                        <dd className="mt-2 hidden text-xs text-study-muted sm:block">
                            {note}
                        </dd>
                    </div>
                ))}
            </dl>

            <section id="sinavlar" className="scroll-mt-24 pt-14 sm:pt-20">
                <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="study-eyebrow">01 — Başlangıç noktası</p>
                        <h2 className="mt-3 font-display text-4xl tracking-[-0.035em] sm:text-5xl">
                            Senin yolun hangisi?
                        </h2>
                    </div>
                    <p className="max-w-60 text-sm leading-6 text-study-muted">
                        Kademeni seç. Sana uygun derslerle kendi denemeni
                        oluştur.
                    </p>
                </div>
                <div className="grid gap-5 md:grid-cols-2">
                    {levels.map((level, index) => (
                        <Link
                            key={level.value}
                            href={coursesIndex.url(level.value)}
                            className={`study-level group ${index === 0 ? 'study-level-primary' : ''}`}
                        >
                            <div className="flex items-start justify-between gap-4">
                                <span className="text-[11px] tracking-[0.14em] opacity-70">
                                    {index === 0
                                        ? 'LİSE DÜZEYİ'
                                        : 'ORTAOKUL DÜZEYİ'}
                                </span>
                                <span className="font-display text-4xl opacity-45">
                                    0{index + 1}
                                </span>
                            </div>
                            <h3 className="mt-8 max-w-64 font-display text-4xl leading-[1.1] tracking-[-0.03em] sm:text-5xl">
                                {level.label}
                            </h3>
                            <p className="mt-4 text-sm opacity-75">
                                {level.courses > 0
                                    ? `${level.courses} dersle çalışmaya başlayabilirsin.`
                                    : 'Ders içerikleri hazırlanıyor.'}
                            </p>
                            <div className="mt-10 flex items-center justify-between border-t border-current/20 pt-5 text-sm font-medium">
                                <span>Derslerini seç</span>
                                <span
                                    aria-hidden="true"
                                    className="flex h-10 w-10 items-center justify-center rounded-full border border-current/30 text-xl transition-transform group-hover:rotate-45"
                                >
                                    <StudyArrow />
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            </section>

            <section
                id="nasil-calisir"
                className="scroll-mt-24 pt-16 pb-5 sm:pt-24"
            >
                <div className="grid gap-8 border-t border-study-rule pt-8 lg:grid-cols-[1fr_2fr]">
                    <div>
                        <p className="study-eyebrow">02 — Çalışma ritmin</p>
                        <h2 className="mt-4 font-display text-4xl leading-tight tracking-[-0.03em]">
                            Küçük adımlar.
                            <br />
                            <em>Net bir ilerleme.</em>
                        </h2>
                    </div>
                    <ol className="grid gap-7 sm:grid-cols-3">
                        {[
                            [
                                'Dersini seç.',
                                'Çalışmak istediğin dersleri ve soru türünü belirle. Denemeni ihtiyacına göre oluştur.',
                            ],
                            [
                                'Kendini dene.',
                                `Her ders için ${sectionMinutes} dakikan var. Sorularını çöz, cevaplarını işaretle, kendi temponu bul.`,
                            ],
                            [
                                'Geriye dön, öğren.',
                                'Doğru ve yanlışlarını incele. Cevap anahtarını kaynaklarla karşılaştır, eksiklerini fark et.',
                            ],
                        ].map(([title, text], index) => (
                            <li key={title}>
                                <span className="text-xs text-study-accent">
                                    0{index + 1}
                                </span>
                                <h3 className="mt-4 text-base font-semibold">
                                    {title}
                                </h3>
                                <p className="mt-3 text-sm leading-6 text-study-muted">
                                    {text}
                                </p>
                            </li>
                        ))}
                    </ol>
                </div>
                <p className="mt-12 border-t border-study-rule pt-5 text-xs leading-6 text-study-muted">
                    Soru bankası MEB kitapçıklarından aktarılan çıkmış soruları
                    ve ders metinlerinden üretilen çalışma sorularını içerir.
                    Üretilen içerikte hata olabilir; tereddüt ettiğin cevapları
                    kaynak kitaptan kontrol et.
                </p>
            </section>
        </>
    );
}

Home.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
export default Home;
