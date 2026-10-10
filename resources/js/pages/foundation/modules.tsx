import { Head, router } from '@inertiajs/react';
import { ErpPage, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/modules';
import type { Catalog, License } from '@/types/erp';

export default function Modules({
    license,
    catalog,
    entitlements,
}: {
    license: License;
    catalog: Catalog;
    entitlements: { code: string; is_enabled: boolean }[];
}) {
    return (
        <>
            <Head title="Modul & Lisensi" />
            <ErpPage
                title="Modul & Lisensi"
                description="Pilih menu yang digunakan perusahaan Anda. Menonaktifkan modul menyembunyikan menu dan membatasi akses; data tetap tersimpan."
            >
                <section className={panel}>
                    <h2 className="font-semibold">{license.package_name}</h2>
                    <div className="mt-3 flex flex-wrap gap-6 text-sm">
                        <span>Rp5.000.000 sekali bayar</span>
                        <span>
                            Status:{' '}
                            <strong>
                                {license.status === 'active'
                                    ? 'Aktif'
                                    : license.status === 'pending'
                                      ? 'Menunggu aktivasi'
                                      : 'Ditangguhkan'}
                            </strong>
                        </span>
                        <span>
                            Masa hak pakai:{' '}
                            {license.expires_at
                                ? new Date(
                                      license.expires_at,
                                  ).toLocaleDateString('id-ID')
                                : 'Tanpa tanggal berakhir'}
                        </span>
                    </div>
                    <p className="mt-3 text-xs leading-5 text-muted-foreground">
                        Aktivasi lisensi dilakukan oleh pengelola setelah
                        pembayaran diverifikasi. Masa support dan biaya hosting
                        dikelola terpisah dari hak pakai aplikasi.
                    </p>
                </section>
                <section className="grid gap-4 md:grid-cols-2">
                    {Object.entries(catalog).map(([code, module]) => {
                        const enabled =
                            entitlements.find((item) => item.code === code)
                                ?.is_enabled ?? false;
                        return (
                            <article key={code} className={panel}>
                                <div className="flex items-center justify-between gap-3">
                                    <h2 className="font-semibold">
                                        {module.name}
                                    </h2>
                                    <span
                                        className={`rounded-full px-2 py-1 text-xs ${enabled ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-muted text-muted-foreground'}`}
                                    >
                                        {enabled
                                            ? 'Diaktifkan'
                                            : 'Disembunyikan'}
                                    </span>
                                </div>
                                <p className="mt-3 text-sm leading-6 text-muted-foreground">
                                    {module.description}
                                </p>
                                <p className="mt-3 text-xs text-muted-foreground">
                                    {module.dependencies.length
                                        ? `Memerlukan: ${module.dependencies.map((dependency) => catalog[dependency].name).join(', ')}`
                                        : 'Termasuk paket dasar'}
                                </p>
                                <Button
                                    className="mt-4"
                                    variant="outline"
                                    onClick={() => {
                                        if (
                                            window.confirm(
                                                `${enabled ? 'Nonaktifkan' : 'Aktifkan'} modul ${module.name}?`,
                                            )
                                        )
                                            router.patch(update.url(code), {
                                                is_enabled: !enabled,
                                            });
                                    }}
                                >
                                    {enabled
                                        ? 'Sembunyikan modul'
                                        : 'Aktifkan modul'}
                                </Button>
                            </article>
                        );
                    })}
                </section>
            </ErpPage>
        </>
    );
}
