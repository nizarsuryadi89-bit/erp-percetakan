import { Head, Link, router, useForm } from '@inertiajs/react';
import { DeskprintConnection } from '@/components/deskprint-connection';
import { ErpPage, field, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/invoices';
import { index as methodsIndex } from '@/routes/payment-methods';
import type { Invoice } from '@/types/cashier';
import { paymentLabels } from '@/types/cashier';
import type { PrintOrder } from '@/types/deskprint';
import { rupiah } from '@/types/product';
type View = 'ready' | 'invoices' | 'receivables';
type Record = Pick<PrintOrder, 'id' | 'number' | 'customer_snapshot' | 'total'> & Partial<Pick<Invoice, 'status' | 'summary' | 'order_number'>>;
type Props = { records: { data: Record[]; prev_page_url: string | null; next_page_url: string | null }; filters: { search?: string; status?: string }; view: View; canIssue: boolean; canManageMethods: boolean };
export default function CashierIndex({ records, filters, view, canIssue, canManageMethods }: Props) {
    const form = useForm({ view, search: filters.search ?? '', status: filters.status ?? '' });
    return <><Head title="Kasir & invoice" /><ErpPage title="Kasir & invoice" description="Tagih SPK terkonfirmasi, catat DP atau pelunasan, dan periksa sisa tagihan.">
        <DeskprintConnection />
        <nav className="flex flex-wrap gap-2" aria-label="Daftar kasir">
            {([['ready', 'SPK siap tagih'], ['invoices', 'Semua invoice'], ['receivables', 'Piutang']] as const).map(([key, label]) => <Link key={key} href={index.url({ query: { view: key } })} className={`inline-flex min-h-11 items-center rounded-lg border px-4 text-sm ${view === key ? 'bg-primary text-primary-foreground' : ''}`}>{label}</Link>)}
            {canManageMethods && <Link className="inline-flex min-h-11 items-center px-4 underline" href={methodsIndex.url()}>Metode / akun pembayaran</Link>}
        </nav>
        <form className={`${panel} grid gap-3 sm:grid-cols-[1fr_12rem_auto]`} onSubmit={(event) => { event.preventDefault(); form.get(index.url()); }}>
            <label className="grid gap-2 text-sm">Nomor SPK / invoice / pelanggan<input className={`${field} h-11`} value={form.data.search} onChange={(event) => form.setData('search', event.target.value)} /></label>
            <label className="grid gap-2 text-sm">Status<select className={`${field} h-11`} disabled={view === 'ready'} value={form.data.status} onChange={(event) => form.setData('status', event.target.value)}><option value="">Semua</option>{Object.entries(paymentLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}{view !== 'receivables' && <option value="void">Void</option>}</select></label>
            <Button className="min-h-11 self-end" disabled={form.processing}>Cari</Button>
        </form>
        {records.data.length === 0 && <p className={panel}>Belum ada data yang sesuai filter pada cabang Anda.</p>}
        <section className="grid gap-3" aria-label="Daftar tagihan">{records.data.map((record) => <article key={record.id} className={`${panel} grid gap-3 sm:grid-cols-[1fr_auto]`}>
            <div><h2 className="font-mono font-semibold">{record.number}</h2><p>{record.customer_snapshot.name}</p>{record.order_number && <p className="text-sm text-muted-foreground">SPK {record.order_number}</p>}</div>
            <div className="grid gap-2 text-sm sm:text-right"><p>Total {rupiah(record.total)}</p>{record.summary && <><p>{record.status === 'void' ? 'VOID' : paymentLabels[record.summary.payment_status]}</p><p className="font-semibold">Sisa ditagih {rupiah(record.summary.collectible)}</p></>}
                {view === 'ready' ? canIssue && <Link className="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary px-4 text-primary-foreground" href={create.url(record.id)}>Periksa & tagih</Link> : <Link className="inline-flex min-h-11 items-center justify-center rounded-lg border px-4" href={show.url(record.id)}>Rincian / pembayaran</Link>}
            </div>
        </article>)}</section>
        {view === 'receivables' && <p className="text-sm text-muted-foreground">Piutang operasional adalah sisa invoice aktif. Penagihan otomatis dan laporan akuntansi belum tersedia.</p>}
        <div className="flex gap-3"><Button variant="outline" className="min-h-11" disabled={!records.prev_page_url} onClick={() => records.prev_page_url && router.get(records.prev_page_url)}>Sebelumnya</Button><Button variant="outline" className="min-h-11" disabled={!records.next_page_url} onClick={() => records.next_page_url && router.get(records.next_page_url)}>Berikutnya</Button></div>
    </ErpPage></>;
}
