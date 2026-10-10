import { Head, Link, router, useForm } from '@inertiajs/react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { DeskprintConnection } from '@/components/deskprint-connection';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/print-orders';
import type { PrintOrder } from '@/types/deskprint';
import { statusLabels } from '@/types/deskprint';
import { rupiah } from '@/types/product';
type Props = { orders: { data: PrintOrder[]; prev_page_url: string | null; next_page_url: string | null }; filters: { search?: string; status?: string }; canManage: boolean };
export default function Orders({ orders, filters, canManage }: Props) {
    const form = useForm({ search: filters.search ?? '', status: filters.status ?? '' });
    return <><Head title="Deskprint & SPK" /><ErpPage title="Deskprint & SPK" description="Mulai pemesanan, siapkan rincian pekerjaan, lalu konfirmasi SPK.">
        <DeskprintConnection />
        {canManage && <Link className="inline-flex min-h-11 items-center self-start rounded-lg bg-primary px-5 text-primary-foreground" href={create.url()}>Buat SPK</Link>}
        <form className={`${panel} grid gap-3 sm:grid-cols-[1fr_12rem_auto]`} onSubmit={(event) => { event.preventDefault(); form.get(index.url(), { preserveState: true }); }}>
            <label className="grid gap-2 text-sm">Nomor SPK / pelanggan<input className={`${field} h-11`} value={form.data.search} onChange={(e) => form.setData('search', e.target.value)} /></label>
            <label className="grid gap-2 text-sm">Status<select className={`${field} h-11`} value={form.data.status} onChange={(e) => form.setData('status', e.target.value)}><option value="">Semua status</option>{Object.entries(statusLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
            <Button className="min-h-11 self-end" disabled={form.processing}>Cari</Button>
        </form>
        <section className="grid gap-3" aria-label="Daftar SPK">
            {orders.data.length === 0 && <p className={panel}>Belum ada SPK yang dapat diakses. Staf memerlukan assignment cabang dari pengelola.</p>}
            {orders.data.map((order) => <Link key={order.id} href={show.url(order.id)} className={`${panel} grid gap-3 sm:grid-cols-[1fr_auto]`}>
                <div><p className="font-mono text-sm font-semibold">{order.number}</p><p className="mt-1">{order.customer_snapshot.name}</p><p className="text-sm text-muted-foreground">{order.company_snapshot.branch_name}</p></div>
                <div className="text-sm sm:text-right"><p>{statusLabels[order.status]}</p><p className="mt-2 font-semibold">{rupiah(order.total)}</p></div>
            </Link>)}
        </section>
        <div className="flex gap-3"><Button variant="outline" className="min-h-11" disabled={!orders.prev_page_url} onClick={() => orders.prev_page_url && router.get(orders.prev_page_url)}>Sebelumnya</Button><Button variant="outline" className="min-h-11" disabled={!orders.next_page_url} onClick={() => orders.next_page_url && router.get(orders.next_page_url)}>Berikutnya</Button></div>
    </ErpPage></>;
}
