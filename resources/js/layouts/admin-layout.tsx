import FlashMessage from '@/components/flash-message';
import { cn } from '@/lib/utils';
import { logout } from '@/routes';
import { dashboard } from '@/routes/admin';
import { index as coursesIndex } from '@/routes/admin/courses';
import { edit as profileEdit } from '@/routes/admin/profile';
import { index as publicCoursesIndex } from '@/routes/courses';
import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

const navigation = [
    { label: 'Panel', href: dashboard.url() },
    { label: 'Dersler', href: coursesIndex.url() },
    { label: 'Profil', href: profileEdit.url() },
];

export default function AdminLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;
    const { url } = usePage();

    const isActive = (href: string) =>
        href === dashboard.url() ? url === href : url.startsWith(href);

    return (
        <div className="flex min-h-screen flex-col bg-slate-50 text-slate-900 lg:flex-row dark:bg-slate-950 dark:text-slate-100">
            <aside className="border-b border-slate-200 bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-64 lg:shrink-0 lg:flex-col lg:border-r lg:border-b-0 dark:border-slate-800 dark:bg-slate-900">
                <div className="flex items-center justify-between gap-3 px-4 py-4 lg:px-6 lg:py-6">
                    <Link
                        href={dashboard.url()}
                        className="flex items-center gap-3"
                    >
                        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-base font-bold text-white">
                            A
                        </span>
                        <span className="flex flex-col leading-tight">
                            <span className="text-sm font-semibold">
                                Açık Lise Test
                            </span>
                            <span className="text-xs text-slate-500 dark:text-slate-400">
                                Yönetim Paneli
                            </span>
                        </span>
                    </Link>
                </div>

                <nav className="flex gap-1 overflow-x-auto px-3 pb-3 text-sm font-medium lg:flex-1 lg:flex-col lg:px-4">
                    {navigation.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={cn(
                                'rounded-lg px-3 py-2 whitespace-nowrap transition',
                                isActive(item.href)
                                    ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white',
                            )}
                        >
                            {item.label}
                        </Link>
                    ))}
                    <Link
                        href={publicCoursesIndex.url()}
                        className="rounded-lg px-3 py-2 whitespace-nowrap text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white"
                    >
                        Siteyi görüntüle ↗
                    </Link>
                </nav>

                <div className="hidden border-t border-slate-200 px-6 py-4 text-sm lg:block dark:border-slate-800">
                    <p className="truncate font-medium">{auth.user?.name}</p>
                    <p className="truncate text-xs text-slate-500 dark:text-slate-400">
                        {auth.user?.email}
                    </p>
                    <Link
                        href={logout.url()}
                        method="post"
                        as="button"
                        className="mt-3 text-xs font-semibold text-rose-600 hover:text-rose-700 dark:text-rose-400"
                    >
                        Çıkış yap
                    </Link>
                </div>
            </aside>

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-8">
                <FlashMessage />
                {children}
            </main>
        </div>
    );
}
