import { home } from '@/routes';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

export default function AppLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-screen flex-col bg-slate-50 text-slate-900 dark:bg-slate-950 dark:text-slate-100">
            <header className="sticky top-0 z-30 border-b border-slate-200/70 bg-white/80 backdrop-blur-md dark:border-slate-800 dark:bg-slate-950/80">
                <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4 sm:px-6">
                    <Link
                        href={home.url()}
                        className="flex items-center gap-3 rounded-lg transition hover:opacity-90"
                    >
                        <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-lg font-bold text-white shadow-lg shadow-indigo-500/30">
                            A
                        </span>
                        <span className="flex flex-col leading-tight">
                            <span className="text-sm font-semibold tracking-tight sm:text-base">
                                Açık Öğretim Deneme
                            </span>
                            <span className="text-[11px] text-slate-500 sm:text-xs dark:text-slate-400">
                                Lise ve Ortaokul Deneme Sınavları
                            </span>
                        </span>
                    </Link>

                    <nav className="flex items-center gap-1 text-sm font-medium">
                        <Link
                            href={home.url()}
                            className="rounded-lg px-3 py-2 text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                        >
                            Sınavlar
                        </Link>
                    </nav>
                </div>
            </header>

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 sm:py-10">
                {children}
            </main>

            <footer className="border-t border-slate-200/70 py-6 text-center text-xs text-slate-400 dark:border-slate-800 dark:text-slate-500">
                Laravel · Inertia.js · React · Tailwind CSS ile geliştirildi
            </footer>
        </div>
    );
}
