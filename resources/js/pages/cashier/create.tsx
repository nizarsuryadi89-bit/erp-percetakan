import { Head, Link, useForm } from '@inertiajs/react';
import { DeskprintConnection } from '@/components/deskprint-connection';
import { ErpPage, panel } from '@/components/erp-page';
import { InvoiceLines } from '@/components/invoice-lines';
import { Button } from '@/components/ui/button';
import { index, store } from '@/routes/invoices';
import type { PrintOrder } from '@/types/deskprint';
import { dateLabel } from '@/types/deskprint';
import { rupiah } from '@/types/product';
export default function CreateInvoice({ order, requestKey, timezone }: { order: PrintOrder; requestKey: string; timezone: string }) {
    const form = useForm({ request_key: requestKey });
    return <><Head title="Terbitkan invoice" /><ErpPage title="Periksa tagihan" description="Rincian mengikuti harga SPK terkonfirmasi. Pembayaran dicatat setelah invoice diterbitkan.">
        <DeskprintConnection /><Link className="inline-flex min-h-11 items-center underline" href={index.url()}>Kembali ke kasir</Link>
        <div className="grid gap-5 lg:grid-cols-[2fr_1fr]"><section className={panel}><h2 className="mb-4 font-semibold">{order.number} · {order.customer_snapshot.name}</h2><InvoiceLines items={order.items.map((item) => ({ ...item, source_item_id: item.id }))} /></section>
            <form className={`${panel} grid content-start gap-4`} onSubmit={(event) => { event.preventDefault(); form.post(store.url(order.id)); }}>
                <h2 className="font-semibold">Konfirmasi penerbitan</h2><p>{order.company_snapshot.name} · {order.company_snapshot.branch_name}</p><p className="text-sm">SPK dibuat {dateLabel(order.created_at, timezone)}</p><p className="text-2xl font-semibold">{rupiah(order.total)}</p><p className="text-sm text-muted-foreground">Satu invoice utama per SPK. Tidak ada diskon, pajak atau ongkir tambahan. Invoice belum menjadi bukti pembayaran.</p>
                <Button className="min-h-11" disabled={form.processing}>{form.processing ? 'Menunggu server…' : 'Terbitkan invoice'}</Button>
            </form></div>
    </ErpPage></>;
}
