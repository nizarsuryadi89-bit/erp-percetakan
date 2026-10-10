import { Head, Link, usePage } from '@inertiajs/react';
import { Printer, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { dashboard, login, register } from '@/routes';

export default function Welcome() {
    const { auth } = usePage().props;
    return (
        <>
            <Head title="ERP Percetakan" />
            <div className="min-h-screen bg-background">
                <header className="mx-auto flex max-w-6xl items-center justify-between border-b px-6 py-5">
                    <div className="flex items-center gap-3 font-semibold">
                        <Printer className="size-6" />
                        ERP Percetakan
                    </div>
                    <Button variant="outline" asChild>
                        <Link href={auth.user ? dashboard() : login()}>
                            {auth.user ? 'Dashboard' : 'Masuk'}
                        </Link>
                    </Button>
                </header>
                <main className="mx-auto max-w-6xl px-6 py-20 md:py-28">
                    <p className="text-xs font-medium tracking-widest text-muted-foreground uppercase">
                        Dari order sampai laporan
                    </p>
                    <h1 className="mt-5 max-w-3xl text-4xl leading-tight font-semibold tracking-tight md:text-6xl">
                        Alur percetakan yang
                        <br />
                        sudah Anda kenal.
                    </h1>
                    <p className="mt-6 max-w-xl text-base leading-7 text-muted-foreground">
                        Transaksi dan SPK, BOM, routing, produksi, keuangan,
                        serta laba rugi dalam satu aplikasi. Setiap perusahaan
                        memiliki pengaturan dan hak akses sendiri.
                    </p>
                    <div className="mt-8 flex flex-wrap gap-3">
                        <Button asChild>
                            <Link href={auth.user ? dashboard() : register()}>
                                {auth.user
                                    ? 'Buka dashboard'
                                    : 'Daftarkan akun'}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href={login()}>Masuk ke akun</Link>
                        </Button>
                    </div>
                    <div className="mt-14 grid gap-4 sm:grid-cols-3">
                        {[
                            [
                                'Transaksi / SPK',
                                'Pesanan, pembayaran kasir, dan invoice.',
                            ],
                            [
                                'BOM / Routing / Produksi',
                                'Kebutuhan bahan dan urutan pekerjaan.',
                            ],
                            [
                                'Keuangan / Laba Rugi',
                                'Piutang, biaya, dan laporan usaha.',
                            ],
                        ].map(([title, description]) => (
                            <article
                                key={title}
                                className="rounded-xl border p-5"
                            >
                                <h2 className="font-medium">{title}</h2>
                                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                                    {description}
                                </p>
                            </article>
                        ))}
                    </div>
                    <p className="mt-8 text-xs text-muted-foreground">
                        Pengembangan tahap fondasi. Alur operasional sedang
                        dipindahkan bertahap dari ERP TGM.
                    </p>
                </main>
            </div>
        </>
    );
}
