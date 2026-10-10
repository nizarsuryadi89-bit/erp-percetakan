import { Head, Link, useForm } from '@inertiajs/react';
import { DeskprintConnection } from '@/components/deskprint-connection';
import { ErpPage, field, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import { index as cashierIndex } from '@/routes/invoices';
import { store, update } from '@/routes/payment-methods';
import type { PaymentMethod } from '@/types/cashier';
import { methodKinds } from '@/types/cashier';
export default function PaymentMethods({ methods }: { methods: PaymentMethod[] }) {
    const form = useForm({ kind: 'cash', name: '', details: '' });
    const toggle = useForm({ is_active: true });
    const busy = form.processing || toggle.processing;
    return <><Head title="Metode pembayaran" /><ErpPage title="Metode / akun pembayaran" description="Atur tujuan pembayaran milik perusahaan Anda. Referensi pembayaran dimasukkan kasir; tidak ada koneksi otomatis ke bank atau gateway.">
        <DeskprintConnection /><Link className="inline-flex min-h-11 items-center underline" href={cashierIndex.url()}>Kembali ke kasir</Link>
        <form className={`${panel} grid gap-4 sm:grid-cols-2`} onSubmit={(event) => { event.preventDefault(); form.post(store.url(), { onSuccess: () => form.reset('name', 'details') }); }}><fieldset disabled={busy} className="contents"><label className="grid gap-2 text-sm">Jenis<select className={`${field} h-11`} value={form.data.kind} onChange={(event) => form.setData('kind', event.target.value)}>{Object.entries(methodKinds).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label><label className="grid gap-2 text-sm">Nama metode / akun<input required maxLength={100} className={`${field} h-11`} value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} /></label><label className="grid gap-2 text-sm sm:col-span-2">Keterangan rekening / perangkat (opsional)<input maxLength={150} className={`${field} h-11`} value={form.data.details} onChange={(event) => form.setData('details', event.target.value)} /></label><Button className="min-h-11 justify-self-start">Tambah metode</Button></fieldset></form>
        <section className="grid gap-3">{methods.map((method) => <article className={`${panel} flex flex-wrap items-center justify-between gap-4`} key={method.id}><div><h2 className="font-semibold">{method.name}</h2><p className="break-words text-sm">{methodKinds[method.kind]} · {method.details || 'Tanpa keterangan akun'}</p><p className="text-sm text-muted-foreground">{method.is_active ? 'Aktif' : 'Nonaktif'}</p></div><Button type="button" className="min-h-11" variant="outline" disabled={busy} onClick={() => { toggle.transform(() => ({ is_active: !method.is_active })); toggle.patch(update.url(method.id), { preserveScroll: true }); }}>{method.is_active ? 'Nonaktifkan' : 'Aktifkan'}</Button></article>)}</section>
        <p className="text-sm text-muted-foreground">Untuk mengganti nama/akun, buat metode baru dan nonaktifkan yang lama. Bukti historis tetap memakai rincian saat pembayaran dicatat.</p>
    </ErpPage></>;
}
