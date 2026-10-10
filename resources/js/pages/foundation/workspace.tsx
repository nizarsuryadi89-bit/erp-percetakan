import { Head, Link } from '@inertiajs/react';
import { ErpPage, panel } from '@/components/erp-page';
import { index as ordersIndex } from '@/routes/print-orders';
import { index as productIndex } from '@/routes/products';
import type { ModuleDefinition } from '@/types/erp';

export default function ModuleWorkspace({
    module,
    code,
}: {
    code: string;
    module: ModuleDefinition;
}) {
    return (
        <>
            <Head title={module.name} />
            <ErpPage title={module.name} description={module.description}>
                {code === 'POS' && (
                    <section className={panel}>
                        <h2 className="font-semibold">Deskprint, SPK & Harga</h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Buat pesanan multi-item, hitung harga otomatis, lalu konfirmasi dan cetak SPK dasar.
                        </p>
                        <Link className="mt-4 mr-3 inline-flex min-h-11 items-center rounded-md bg-primary px-4 text-sm text-primary-foreground" href={ordersIndex.url()}>Buka Deskprint & SPK</Link>
                        <Link
                            className="mt-4 inline-flex rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground"
                            href={productIndex.url()}
                        >
                            Buka produk & harga
                        </Link>
                    </section>
                )}
                <section className={panel}>
                    <span className="rounded-full bg-amber-100 px-3 py-1 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                        {code === 'POS' ? 'Deskprint dan SPK dasar tersedia' : 'Alur operasional belum tersedia'}
                    </span>
                    <h2 className="mt-5 font-semibold">
                        Urutan kerja yang akan dipertahankan
                    </h2>
                    <ol className="mt-5 grid gap-4 md:grid-cols-4">
                        {module.steps.map((step, index) => (
                            <li key={step} className="rounded-lg border p-4">
                                <span className="text-xs text-muted-foreground">
                                    Langkah {index + 1}
                                </span>
                                <p className="mt-2 text-sm font-medium">
                                    {step}
                                </p>
                            </li>
                        ))}
                    </ol>
                    <p className="mt-5 max-w-3xl text-sm leading-6 text-muted-foreground">
                        {code === 'POS' ? 'Pemesanan dan SPK dasar tersedia. Pembayaran, DP, piutang dan invoice final belum tersedia pada tahap ini.' : 'Halaman ini menyiapkan akses modul. Alur operasional akan ditambahkan sesuai tahap implementasi.'}
                    </p>
                </section>
            </ErpPage>
        </>
    );
}
