import { Head, router, useForm } from '@inertiajs/react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import { store, update } from '@/routes/branches';
import type { Branch } from '@/types/erp';

export default function Branches({ branches }: { branches: Branch[] }) {
    const form = useForm({ code: '', name: '', address: '' });
    return (
        <>
            <Head title="Cabang" />
            <ErpPage
                title="Daftar Cabang"
                description="Kelola lokasi operasional di dalam perusahaan Anda. Cabang aktif dapat dipilih pada bagian atas layar."
            >
                <section className={`${panel} overflow-x-auto`}>
                    <table className="w-full text-left text-sm">
                        <thead>
                            <tr className="border-b text-muted-foreground">
                                <th className="pb-3">Kode</th>
                                <th>Nama cabang</th>
                                <th>Status</th>
                                <th className="text-right">Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            {branches.map((branch) => (
                                <tr
                                    className="border-b last:border-0"
                                    key={branch.id}
                                >
                                    <td className="py-4 font-mono text-xs">
                                        {branch.code}
                                    </td>
                                    <td>
                                        <p className="font-medium">
                                            {branch.name}
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            {branch.address ||
                                                'Alamat belum diisi'}
                                        </p>
                                    </td>
                                    <td>
                                        {branch.is_active
                                            ? 'Aktif'
                                            : 'Nonaktif'}
                                    </td>
                                    <td className="text-right">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `${branch.is_active ? 'Nonaktifkan' : 'Aktifkan'} cabang ${branch.name}?`,
                                                    )
                                                )
                                                    router.patch(
                                                        update.url(branch.id),
                                                        {
                                                            code: branch.code,
                                                            name: branch.name,
                                                            address:
                                                                branch.address,
                                                            is_active:
                                                                !branch.is_active,
                                                        },
                                                    );
                                            }}
                                        >
                                            {branch.is_active
                                                ? 'Nonaktifkan'
                                                : 'Aktifkan'}
                                        </Button>
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
                    <h2 className="font-semibold">Tambah Cabang</h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        <label className="grid gap-2 text-sm">
                            Kode cabang
                            <input
                                className={field}
                                required
                                maxLength={30}
                                value={form.data.code}
                                onChange={(e) =>
                                    form.setData(
                                        'code',
                                        e.target.value.toUpperCase(),
                                    )
                                }
                                placeholder="CABANG-02"
                            />
                        </label>
                        <label className="grid gap-2 text-sm">
                            Nama cabang
                            <input
                                className={field}
                                required
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData('name', e.target.value)
                                }
                            />
                        </label>
                    </div>
                    <label className="grid gap-2 text-sm">
                        Alamat
                        <input
                            className={field}
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                        />
                    </label>
                    <Button
                        className="justify-self-start"
                        disabled={form.processing}
                    >
                        Simpan cabang
                    </Button>
                </form>
            </ErpPage>
        </>
    );
}
