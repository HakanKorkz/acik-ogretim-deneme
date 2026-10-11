import FormField from '@/components/form-field';
import AdminLayout from '@/layouts/admin-layout';
import { update as updatePassword } from '@/routes/user-password';
import { update as updateProfile } from '@/routes/user-profile-information';
import { Form, Head, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 className="text-base font-semibold">{title}</h2>
            <div className="mt-5">{children}</div>
        </section>
    );
}

function SubmitButton({
    processing,
    recentlySuccessful,
}: {
    processing: boolean;
    recentlySuccessful: boolean;
}) {
    return (
        <div className="flex items-center gap-3">
            <button
                type="submit"
                disabled={processing}
                className="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:opacity-60"
            >
                Kaydet
            </button>
            {recentlySuccessful && (
                <span className="text-sm text-emerald-600 dark:text-emerald-400">
                    Kaydedildi.
                </span>
            )}
        </div>
    );
}

function Profile() {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Profil" />

            <h1 className="text-2xl font-bold tracking-tight">Profil</h1>

            <div className="mt-6 grid max-w-2xl gap-6">
                <Section title="Hesap bilgileri">
                    <Form
                        {...updateProfile.form()}
                        options={{ preserveScroll: true }}
                        errorBag="updateProfileInformation"
                        className="flex flex-col gap-4"
                    >
                        {({ errors, processing, recentlySuccessful }) => (
                            <>
                                <FormField
                                    label="Ad"
                                    name="name"
                                    defaultValue={auth.user?.name}
                                    required
                                    error={errors.name}
                                />
                                <FormField
                                    label="E-posta"
                                    name="email"
                                    type="email"
                                    defaultValue={auth.user?.email}
                                    required
                                    error={errors.email}
                                />
                                <SubmitButton
                                    processing={processing}
                                    recentlySuccessful={recentlySuccessful}
                                />
                            </>
                        )}
                    </Form>
                </Section>

                <Section title="Şifre değiştir">
                    <Form
                        {...updatePassword.form()}
                        options={{ preserveScroll: true }}
                        errorBag="updatePassword"
                        resetOnSuccess
                        resetOnError={[
                            'password',
                            'password_confirmation',
                            'current_password',
                        ]}
                        className="flex flex-col gap-4"
                    >
                        {({ errors, processing, recentlySuccessful }) => (
                            <>
                                <FormField
                                    label="Mevcut şifre"
                                    name="current_password"
                                    type="password"
                                    autoComplete="current-password"
                                    error={errors.current_password}
                                />
                                <FormField
                                    label="Yeni şifre"
                                    name="password"
                                    type="password"
                                    autoComplete="new-password"
                                    error={errors.password}
                                />
                                <FormField
                                    label="Yeni şifre (tekrar)"
                                    name="password_confirmation"
                                    type="password"
                                    autoComplete="new-password"
                                    error={errors.password_confirmation}
                                />
                                <SubmitButton
                                    processing={processing}
                                    recentlySuccessful={recentlySuccessful}
                                />
                            </>
                        )}
                    </Form>
                </Section>
            </div>
        </>
    );
}

Profile.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;

export default Profile;
