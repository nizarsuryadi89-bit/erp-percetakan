import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowUpRight,
    Building2,
    Users,
    LockKeyhole,
    ClipboardList,
} from 'lucide-react';
import { ErpPage, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import type { Catalog, License } from '@/types/erp';

type Props = {
    license: License;
    branchCount: number;
    userCount: number;
    catalog: Catalog;
    recentActivity: { id: number; action: string; created_at: string }[];
};
export default function Dashboard({
    license,
    branchCount,
    userCount,
    catalog,
    recentActivity,
}: Props) {
    const { workspace } = usePage().props;
    const usable =
        license.status === 'active' &&
        license.activated_at !== null &&
        new Date(license.activated_at) <= new Date() &&
        (!license.expires_at || new Date(license.expires_at) > new Date());
    const modulesUrl = workspace.settings.find(
        (item) => item.code === 'modul',
    )?.href;
    return (
        <>
            <Head title="Dashboard" />
            <ErpPage
                title={workspace.tenant?.name ?? 'Dashboard'}
                description="Kelola percetakan dari satu tempat. Menu dan urutan kerja mengikuti ERP yang sudah Anda gunakan."
            >
                <section className="grid gap-4 sm:grid-cols-3">
                    {[
                        {
                            label: 'Cabang',
                            value: branchCount,
                            icon: Building2,
                        },
                        {
                            label: 'Pengguna aktif',
                            value: userCount,
                            icon: Users,
                        },
                        {
                            label: 'Modul paket',
                            value: Object.keys(catalog).length,
                            icon: ClipboardList,
                        },
                    ].map((item) => (
                        <div className={panel} key={item.label}>
                            <div className="flex items-center justify-between text-sm text-muted-foreground">
                                <span>{item.label}</span>
                                <item.icon className="size-4" />
                            </div>
                            <p className="mt-3 text-3xl font-semibold">
                                {item.value}
                            </p>
                        </div>
                    ))}
                </section>
                <section className="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-muted/30 p-5">
                    <div className="flex items-center gap-3">
                        <LockKeyhole className="size-5 text-muted-foreground" />
                        <div>
                            <h2 className="font-medium">
                                {license.package_name}
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Rp5.000.000 sekali bayar ·{' '}
                                {usable
                                    ? 'Lisensi aktif'
                                    : 'Menunggu aktivasi lisensi'}
                            </p>
                        </div>
                    </div>
                    {modulesUrl && (
                        <Button variant="outline" asChild>
                            <Link href={modulesUrl}>Modul & lisensi</Link>
                        </Button>
                    )}
                </section>
                <section>
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="font-semibold">Menu operasional</h2>
                        <span className="text-xs text-muted-foreground">
                            Alur ERP TGM
                        </span>
                    </div>
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {Object.entries(catalog).map(
                            ([code, module], index) => {
                                const entry = workspace.navigation.find(
                                    (item) => item.code === code,
                                );
                                return (
                                    <article className={panel} key={code}>
                                        <div className="flex items-center justify-between">
                                            <span className="text-xs font-medium text-muted-foreground">
                                                0{index + 1}
                                            </span>
                                            <span className="rounded-full bg-muted px-2 py-1 text-xs">
                                                {entry
                                                    ? 'Fondasi tersedia'
                                                    : usable
                                                      ? 'Tidak tersedia untuk akun ini'
                                                      : 'Menunggu lisensi'}
                                            </span>
                                        </div>
                                        <h3 className="mt-4 font-semibold">
                                            {module.name}
                                        </h3>
                                        <p className="mt-2 min-h-12 text-sm leading-6 text-muted-foreground">
                                            {module.description}
                                        </p>
                                        {entry ? (
                                            <Link
                                                href={entry.href}
                                                className="mt-4 inline-flex items-center gap-2 text-sm font-medium"
                                            >
                                                Buka modul
                                                <ArrowUpRight className="size-4" />
                                            </Link>
                                        ) : (
                                            <span className="mt-4 inline-block text-xs text-muted-foreground">
                                                {modulesUrl
                                                    ? 'Atur melalui Modul & Lisensi'
                                                    : 'Hubungi administrator perusahaan'}
                                            </span>
                                        )}
                                    </article>
                                );
                            },
                        )}
                    </div>
                    <p className="mt-3 text-xs leading-5 text-muted-foreground">
                        Tahap awal: akun, perusahaan, cabang, dan hak akses.
                        Alur transaksi, BOM, routing, produksi, serta laporan
                        akan dipindahkan bertahap.
                    </p>
                </section>
                <section className={panel}>
                    <h2 className="mb-3 font-semibold">
                        Aktivitas pengaturan terakhir
                    </h2>
                    {recentActivity.length ? (
                        <ul className="divide-y">
                            {recentActivity.map((log) => (
                                <li
                                    key={log.id}
                                    className="flex justify-between gap-3 py-3 text-sm"
                                >
                                    <span>
                                        {log.action
                                            .replaceAll('.', ' ')
                                            .replaceAll('_', ' ')}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {new Date(
                                            log.created_at,
                                        ).toLocaleDateString('id-ID')}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Belum ada aktivitas pengaturan.
                        </p>
                    )}
                </section>
            </ErpPage>
        </>
    );
}
