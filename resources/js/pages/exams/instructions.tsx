import SectionProgress, {
    type ExamSummary,
} from '@/components/exam/section-progress';
import AppLayout from '@/layouts/app-layout';
import { start } from '@/routes/exams';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    exam: ExamSummary;
    section: {
        position: number;
        course: string;
        subject: string | null;
        totalQuestions: number;
    };
    isFirstSection: boolean;
}

function Instructions({ exam, section, isFirstSection }: Props) {
    const [starting, setStarting] = useState(false);

    const begin = () => {
        router.post(
            start.url(exam.id),
            {},
            {
                onStart: () => setStarting(true),
                onFinish: () => setStarting(false),
            },
        );
    };

    return (
        <>
            <Head title={`${section.course} - Talimatlar`} />

            <div className="mx-auto max-w-3xl">
                <SectionProgress
                    exam={exam}
                    currentPosition={section.position}
                />

                <section className="mt-6 overflow-hidden rounded-sm border border-study-rule bg-study-surface">
                    <header className="border-b border-study-rule bg-study-tint px-6 py-9 text-center">
                        <p className="text-xs font-semibold tracking-widest text-study-muted uppercase">
                            {exam.levelLabel} · Deneme Sınavı
                        </p>
                        <h1 className="mt-3 font-display text-4xl tracking-tight sm:text-5xl">
                            {section.course}
                        </h1>
                        <p className="mt-1 text-sm text-study-muted">
                            {exam.sections.length > 1 &&
                                `${section.position}. ders · `}
                            {section.totalQuestions} soru ·{' '}
                            {exam.sectionMinutes} dakika · {exam.pool}
                        </p>
                    </header>

                    <div className="px-6 py-6 text-sm leading-relaxed">
                        {isFirstSection && (
                            <>
                                <h2 className="font-display text-2xl">
                                    Başlamadan önce
                                </h2>
                                <ol className="mt-4 list-decimal space-y-2 pl-5 text-study-muted">
                                    <li>
                                        Her ders için {section.totalQuestions}{' '}
                                        soru bulunmaktadır. Her dersin cevaplama
                                        süresi {exam.sectionMinutes} dakikadır;
                                        süre dolduğunda ders kendiliğinden
                                        teslim edilir.
                                    </li>
                                    <li>
                                        Dersler sırayla çözülür; bir ders teslim
                                        edilmeden diğerine geçilmez.
                                    </li>
                                    <li>
                                        Her sorunun dört seçeneği vardır ve
                                        yalnızca biri doğrudur. Cevaplarınızı
                                        sağdaki cevap kâğıdında da
                                        işaretleyebilirsiniz.
                                    </li>
                                    <li>
                                        Cevaplarınız işaretlediğiniz anda
                                        kaydedilir; sayfa yenilense de
                                        kaybolmaz.
                                    </li>
                                    <li>
                                        Her doğru cevap eşit puandır; ders puanı
                                        100 üzerinden hesaplanır. Geçme notu{' '}
                                        {exam.passScore}'dir. Yanlış cevaplar
                                        doğruları götürmez.
                                    </li>
                                </ol>
                            </>
                        )}

                        <p className="mt-6 text-center font-semibold">
                            Hazır olduğunuzda başlayın; süre "Başlayınız"
                            düğmesine bastığınızda işlemeye başlar.
                        </p>

                        <div className="mt-6 text-center">
                            <button
                                type="button"
                                onClick={begin}
                                disabled={starting}
                                className="rounded-sm bg-study-fill px-8 py-3 text-base font-bold tracking-wide text-study-on-fill transition hover:opacity-90 disabled:opacity-60"
                            >
                                {starting ? 'Başlatılıyor...' : 'BAŞLAYINIZ'}
                            </button>
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

Instructions.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;

export default Instructions;
