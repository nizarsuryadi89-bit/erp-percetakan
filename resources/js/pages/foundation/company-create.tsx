import { Head, useForm } from '@inertiajs/react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/companies';

export default function CreateCompany() {
    const form = useForm({ name: '', phone: '', address: '' });
    return (
        <>
            <Head title="Buat Perusahaan" />
            <ErpPage
                title="Daftarkan percetakan Anda"
                description="Mulai dengan profil perusahaan. Anda menjadi Owner dan satu Cabang Utama akan dibuat otomatis."
            >
                <form
                    className={`${panel} grid max-w-2xl gap-5`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(store.url());
                    }}
                >
                    <label className="grid gap-2 text-sm font-medium">
                        Nama perusahaan
                        <Input
                            value={form.data.name}
                            onChange={(value) => form.setData('name', value)}
                            required
                        />
                    </label>
                    <label className="grid gap-2 text-sm font-medium">
                        Nomor telepon
                        <Input
                            value={form.data.phone}
                            onChange={(value) => form.setData('phone', value)}
                        />
                    </label>
                    <label className="grid gap-2 text-sm font-medium">
                        Alamat
                        <textarea
                            className={`${field} h-24 py-2`}
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                        />
                    </label>
                    <div className="rounded-lg bg-muted p-4 text-sm leading-6 text-muted-foreground">
                        Paket Rp5.000.000 sekali bayar: Transaksi, BOM, Routing,
                        Produksi, Keuangan, dan Laba Rugi. Aktivasi lisensi
                        dilakukan oleh pengelola setelah pembayaran
                        diverifikasi.
                    </div>
                    <Button type="submit" disabled={form.processing}>
                        Buat perusahaan
                    </Button>
                </form>
            </ErpPage>
        </>
    );
}
function Input({
    value,
    onChange,
    required = false,
}: {
    value: string;
    onChange: (value: string) => void;
    required?: boolean;
}) {
    return (
        <input
            className={field}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            required={required}
            maxLength={255}
        />
    );
}
