import { Head, router, useForm } from '@inertiajs/react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import { update as assignMembers } from '@/routes/branch-memberships';
import { store, update } from '@/routes/branches';
import type { Branch } from '@/types/erp';

type Member = { id: number; name: string };
type Assignment = { branch_id: number; user_id: number };
export default function Branches({ branches, members, assignments, canAssign }: { branches: Branch[]; members: Member[]; assignments: Assignment[]; canAssign: boolean }) {
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
                {canAssign && <section className="grid gap-4"><h2 className="font-semibold">Akses staf per cabang</h2><p className="text-sm text-muted-foreground">Staf hanya dapat mengakses SPK dan pelanggan pada cabang yang dicentang. Owner dan Administrator memiliki akses seluruh cabang aktif melalui hak kelola cabang.</p>{branches.map((branch) => <BranchMembers key={branch.id} branch={branch} members={members} selected={assignments.filter((row) => row.branch_id === branch.id).map((row) => row.user_id)} />)}</section>}
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

function BranchMembers({ branch, members, selected }: { branch: Branch; members: Member[]; selected: number[] }) {
    const form = useForm({ user_ids: selected });
    return <form className={`${panel} grid gap-3`} onSubmit={(event) => { event.preventDefault(); form.patch(assignMembers.url(branch.id), { preserveScroll: true }); }}>
        <h3 className="font-medium">{branch.name}</h3><div className="grid gap-2 sm:grid-cols-2">{members.map((member) => <label key={member.id} className="flex min-h-11 items-center gap-3 rounded-lg border p-3 text-sm"><input type="checkbox" className="size-5" checked={form.data.user_ids.includes(member.id)} onChange={(event) => form.setData('user_ids', event.target.checked ? [...form.data.user_ids, member.id] : form.data.user_ids.filter((id) => id !== member.id))} />{member.name}</label>)}</div>
        <Button className="min-h-11 justify-self-start" disabled={form.processing}>Simpan akses cabang</Button>
    </form>;
}
