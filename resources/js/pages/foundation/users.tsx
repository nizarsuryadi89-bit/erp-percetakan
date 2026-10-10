import { Head, router, useForm, usePage } from '@inertiajs/react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import { store, update } from '@/routes/tenant-users';
import type { TenantUser } from '@/types/erp';

export default function Users({
    users,
    roles,
}: {
    users: TenantUser[];
    roles: string[];
}) {
    const { auth } = usePage().props;
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'Kasir',
    });
    return (
        <>
            <Head title="Pengguna & Hak Akses" />
            <ErpPage
                title="Pengguna & Hak Akses"
                description="Atur akun kasir, operator produksi, dan keuangan. Hak akses berlaku untuk perusahaan yang sedang aktif."
            >
                <section className={`${panel} overflow-x-auto`}>
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b text-muted-foreground">
                                <th className="pb-3">Pengguna</th>
                                <th>Hak akses</th>
                                <th>Status</th>
                                <th className="text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="py-4">
                                        <p className="font-medium">
                                            {user.name}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {user.email}
                                        </p>
                                    </td>
                                    <td>
                                        {user.role === 'Owner' ||
                                        user.id === auth.user.id ? (
                                            user.role
                                        ) : (
                                            <select
                                                className={`${field} max-w-44`}
                                                value={user.role}
                                                onChange={(e) =>
                                                    router.patch(
                                                        update.url(user.id),
                                                        {
                                                            role: e.target
                                                                .value,
                                                            is_active:
                                                                user.is_active,
                                                        },
                                                    )
                                                }
                                            >
                                                {roles.map((role) => (
                                                    <option key={role}>
                                                        {role}
                                                    </option>
                                                ))}
                                            </select>
                                        )}
                                    </td>
                                    <td>
                                        {user.is_active ? 'Aktif' : 'Nonaktif'}
                                    </td>
                                    <td className="text-right">
                                        {user.role !== 'Owner' &&
                                            user.id !== auth.user.id && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                `Ubah status akun ${user.name}?`,
                                                            )
                                                        )
                                                            router.patch(
                                                                update.url(
                                                                    user.id,
                                                                ),
                                                                {
                                                                    role: user.role,
                                                                    is_active:
                                                                        !user.is_active,
                                                                },
                                                            );
                                                    }}
                                                >
                                                    {user.is_active
                                                        ? 'Nonaktifkan'
                                                        : 'Aktifkan'}
                                                </Button>
                                            )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
                <form
                    className={`${panel} grid gap-4`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(store.url(), {
                            onSuccess: () => form.reset(),
                        });
                    }}
                >
                    <h2 className="font-semibold">Tambah Pengguna</h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="grid gap-2 text-sm">
                            Nama
                            <input
                                className={field}
                                required
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </label>
                        <label className="grid gap-2 text-sm">
                            Email
                            <input
                                className={field}
                                required
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                        </label>
                        <label className="grid gap-2 text-sm">
                            Password awal
                            <input
                                className={field}
                                required
                                minLength={12}
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password}
                                onChange={(e) =>
                                    form.setData('password', e.target.value)
                                }
                            />
                        </label>
                        <label className="grid gap-2 text-sm">
                            Konfirmasi password
                            <input
                                className={field}
                                required
                                type="password"
                                autoComplete="new-password"
                                value={form.data.password_confirmation}
                                onChange={(e) =>
                                    form.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                            />
                        </label>
                        <label className="grid gap-2 text-sm">
                            Hak akses
                            <select
                                className={field}
                                value={form.data.role}
                                onChange={(e) =>
                                    form.setData('role', e.target.value)
                                }
                            >
                                {roles.map((role) => (
                                    <option key={role}>{role}</option>
                                ))}
                            </select>
                        </label>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Gunakan password awal minimal 12 karakter. Akun baru
                        menerima email verifikasi.
                    </p>
                    <Button
                        className="justify-self-start"
                        disabled={form.processing}
                    >
                        Simpan pengguna
                    </Button>
                </form>
            </ErpPage>
        </>
    );
}
