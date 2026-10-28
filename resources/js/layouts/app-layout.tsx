import StudyArrow from '@/components/study-arrow';
import { home } from '@/routes';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

function BookMark() {
    return (
        <svg
            viewBox="0 0 36 36"
            fill="none"
            aria-hidden="true"
            className="h-9 w-9"
        >
            <path
                d="M18 29V9M18 12C13 7 8 7 4 8v17c5-1 9 0 14 4 5-4 9-5 14-4V8c-4-1-9-1-14 4Z"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinejoin="round"
            />
            <path
                d="M8 13c2.5 0 4 .5 6 2M8 18c2.5 0 4 .5 6 2M22 15c2-1.5 3.5-2 6-2M22 20c2-1.5 3.5-2 6-2"
                stroke="currentColor"
                strokeWidth="1.3"
            />
        </svg>
    );
}

export default function AppLayout({ children }: { children: ReactNode }) {
    const { component } = usePage();
    const inExam = component.startsWith('exams/');

    return (
        <div className="student-app flex min-h-screen flex-col bg-study-paper text-study-ink">
            <a href="#ana-icerik" className="study-skip">
                İçeriğe geç
            </a>
            <header className="sticky top-0 z-30 h-16 border-b border-study-rule bg-study-paper">
                <div className="study-container flex h-full items-center justify-between gap-4">
                    <Link
                        href={home.url()}
                        aria-label="Açık Öğretim Deneme ana sayfa"
                        className="flex shrink-0 items-center gap-2.5"
                    >
                        <BookMark />
                        <span className="text-[13px] leading-tight font-semibold tracking-tight sm:text-sm">
                            Açık Öğretim
                            <span className="block font-normal text-study-muted">
                                Deneme Sınavları
                            </span>
                        </span>
                    </Link>
                    <nav
                        aria-label="Ana menü"
                        className="flex items-center gap-7 text-xs font-medium sm:text-sm"
                    >
                        {!inExam && (
                            <a
                                href={`${home.url()}#nasil-calisir`}
                                className="hidden py-3 text-study-muted transition hover:text-study-ink sm:block"
                            >
                                Nasıl çalışır?
                            </a>
                        )}
                        <Link
                            href={
                                inExam ? home.url() : `${home.url()}#sinavlar`
                            }
                            className="inline-flex min-h-11 items-center gap-3 border-b border-study-ink font-semibold"
                        >
                            {inExam ? 'Ana sayfa' : 'Sınavları keşfet'}
                            <StudyArrow />
                        </Link>
                    </nav>
                </div>
            </header>
            <main
                id="ana-icerik"
                tabIndex={-1}
                className="study-container w-full flex-1 py-8 outline-none sm:py-12"
            >
                {children}
            </main>
            <footer className="mt-12 border-t border-study-rule">
                <div className="study-container grid gap-6 py-8 text-xs text-study-muted sm:grid-cols-[1fr_auto] sm:items-end">
                    <div>
                        <p className="font-display text-2xl text-study-ink">
                            Her deneme, bir adım ileri.
                        </p>
                        <p className="mt-3 max-w-sm leading-relaxed">
                            Açık öğretim lise ve ortaokul öğrencileri için
                            <br className="hidden sm:block" /> kendi hızında
                            çalışabileceğin bir sınav alanı.
                        </p>
                    </div>
                    <p className="max-w-64 leading-relaxed sm:text-right">
                        Bağımsız bir çalışma platformudur.
                        <br />
                        Millî Eğitim Bakanlığının resmî sitesi değildir.
                    </p>
                </div>
            </footer>
        </div>
    );
}
