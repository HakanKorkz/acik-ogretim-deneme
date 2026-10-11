import AdminLayout from '@/layouts/admin-layout';
import { result } from '@/routes/exams';
import { Head, Link } from '@inertiajs/react';

interface Stats {
    courses: number;
    coursesWithQuestions: number;
    questions: number;
    sessions: number;
    completedSessions: number;
}

interface RecentSession {
    id: string;
    course: string;
    status: string;
    isCompleted: boolean;
    score: number | null;
    startedAt: string | null;
}

interface Props {
    stats: Stats;
    recentSessions: RecentSession[];
}

const dateFormatter = new Intl.DateTimeFormat('tr-TR', {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function StatCard({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <p className="text-xs font-medium tracking-wide text-slate-500 uppercase dark:text-slate-400">
                {label}
            </p>
            <p className="mt-2 text-3xl font-bold">{value}</p>
        </div>
    );
}

function Dashboard({ stats, recentSessions }: Props) {
    return (
        <>
            <Head title="Panel" />

            <h1 className="text-2xl font-bold tracking-tight">Panel</h1>

            <div className="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard
                    label="Ders (sorusu hazır)"
                    value={`${stats.coursesWithQuestions} / ${stats.courses}`}
                />
                <StatCard label="Soru" value={stats.questions} />
                <StatCard label="Sınav oturumu" value={stats.sessions} />
                <StatCard label="Tamamlanan" value={stats.completedSessions} />
            </div>

            <h2 className="mt-10 text-lg font-semibold">Son sınavlar</h2>

            <div className="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-slate-200 text-xs text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400">
                        <tr>
                            <th className="px-4 py-3 font-medium">Ders</th>
                            <th className="px-4 py-3 font-medium">Başlangıç</th>
                            <th className="px-4 py-3 font-medium">Durum</th>
                            <th className="px-4 py-3 text-right font-medium">
                                Puan
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                        {recentSessions.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-4 py-8 text-center text-slate-500"
                                >
                                    Henüz sınav çözülmedi.
                                </td>
                            </tr>
                        )}
                        {recentSessions.map((session) => (
                            <tr key={session.id}>
                                <td className="px-4 py-3 font-medium">
                                    {session.course}
                                </td>
                                <td className="px-4 py-3 text-slate-500 dark:text-slate-400">
                                    {session.startedAt
                                        ? dateFormatter.format(
                                              new Date(session.startedAt),
                                          )
                                        : '—'}
                                </td>
                                <td className="px-4 py-3">{session.status}</td>
                                <td className="px-4 py-3 text-right">
                                    {session.isCompleted ? (
                                        <Link
                                            href={result.url(session.id)}
                                            className="font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                        >
                                            {session.score}
                                        </Link>
                                    ) : (
                                        '—'
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default Dashboard;
