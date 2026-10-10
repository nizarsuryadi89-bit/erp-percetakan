import { Head, Link, router, useForm, useHttp } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { DeskprintConnection } from '@/components/deskprint-connection';
import { ErpPage, field, panel } from '@/components/erp-page';
import { ProductQuoteSummary } from '@/components/product-quote-summary';
import { Button } from '@/components/ui/button';
import { useDeskprintSearch } from '@/hooks/use-deskprint-search';
import { catalog, destroy, preview as previewRoute, store, update } from '@/routes/print-order-items';
import { index, print as printRoute, status } from '@/routes/print-orders';
import type { Calculation, CatalogProduct, OrderItem, PrintOrder } from '@/types/deskprint';
import { dateInput, dateLabel, methodLabels, statusLabels } from '@/types/deskprint';
import { rupiah } from '@/types/product';
type Props = { order: PrintOrder; canManage: boolean; requestKey: string; timezone: string };
const blank = { product_id: '', qty: '1', length: '', width: '', variant: '', machine: '', print_side: '', finishing: '', notes: '', process_location: '', deadline: '' };
const eventLabels: Record<string, string> = { 'spk.dibuat': 'SPK dibuat', 'spk.item_ditambah': 'Item ditambahkan', 'spk.item_direvisi': 'Item direvisi', 'spk.item_dihapus': 'Item dikeluarkan dari draft', 'spk.status_diubah': 'Status diperbarui' };
const specificationLabels = { variant: 'Bahan / varian', machine: 'Mesin', print_side: 'Sisi cetak', finishing: 'Finishing' };
export default function OrderDetails({ order, canManage, requestKey, timezone }: Props) {
    const [step, setStep] = useState(0); const [search, setSearch] = useState('');
    const [product, setProduct] = useState<CatalogProduct | null>(null); const [editing, setEditing] = useState<OrderItem | null>(null);
    const [quote, setQuote] = useState<Calculation | null>(null); const [previewError, setPreviewError] = useState('');
    const form = useForm({ ...blank, request_key: requestKey, version: order.version });
    const mutation = useForm({ request_key: requestKey, version: order.version, status: '', reason: '' });
    const preview = useHttp<typeof form.data, { quote: Calculation }>(form.data);
    const editable = canManage && order.status === 'draft'; const active = order.items.filter((item) => item.state === 'active');
    const busy = form.processing || mutation.processing;
    const results = useDeskprintSearch<{ products: CatalogProduct[] }>(editable ? catalog.url(order.id, { query: { search } }) : null);
    const editProduct = useDeskprintSearch<{ products: CatalogProduct[] }>(editing ? catalog.url(order.id, { query: { product_id: editing.product_id } }) : null);
    useEffect(() => { form.setData((data) => ({ ...data, request_key: requestKey, version: order.version })); mutation.setData((data) => ({ ...data, request_key: requestKey, version: order.version })); }, [requestKey, order.version]);
    useEffect(() => { if (editing && editProduct.data) setProduct(editProduct.data.products[0] ?? null); }, [editing, editProduct.data]);
    const priceInput = JSON.stringify([form.data.product_id, form.data.qty, form.data.length, form.data.width, form.data.variant, form.data.machine, form.data.print_side, form.data.finishing, order.version]);
    useEffect(() => {
        let current = true; setQuote(null); setPreviewError('');
        if (!editable || !form.data.product_id) return;
        const timer = setTimeout(() => {
            preview.transform(() => form.data);
            void preview.post(previewRoute.url(order.id), {
                onError: (errors) => { if (current) setPreviewError(Object.values(errors).join(' ')); },
                onNetworkError: () => { if (current) setPreviewError('Preview belum diterima. Periksa koneksi; harga dihitung ulang saat simpan.'); return false; },
                onHttpException: () => { if (current) setPreviewError('Preview tidak tersedia. Periksa status, akses, atau konfigurasi produk.'); return false; },
            }).then((response) => { if (current) setQuote(response.quote); }).catch(() => {});
        }, 450);
        return () => { current = false; clearTimeout(timer); preview.cancel(); };
    }, [priceInput, editable]);
    function selectProduct(value: CatalogProduct) {
        setProduct(value); setEditing(null);
        const specifications = Object.fromEntries(Object.keys(specificationLabels).map((key) => { const options = [...new Set(value.prices.map((price) => price[key as keyof typeof specificationLabels]))]; return [key, options.length === 1 ? options[0] : '']; }));
        form.setData({ ...blank, ...specifications, product_id: String(value.id), version: order.version, request_key: requestKey }); setStep(1);
    }
    function revise(item: OrderItem) {
        setEditing(item); setProduct(null);
        form.setData({ ...blank, ...item.snapshot.specifications, request_key: requestKey, version: order.version, product_id: String(item.product_id), qty: item.snapshot.qty, length: item.snapshot.input_length, width: item.snapshot.input_width, notes: item.notes ?? '', process_location: item.process_location ?? '', deadline: dateInput(item.deadline, timezone) });
        setStep(1); window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function changeStatus(value: string) { mutation.transform((data) => ({ ...data, status: value })); mutation.patch(status.url(order.id), { preserveScroll: true }); }
    return <><Head title={order.number} /><ErpPage title={order.number} description={`${methodLabels[order.method]} · ${order.company_snapshot.branch_name} · ${statusLabels[order.status]}`}>
        <DeskprintConnection />
        <div className="flex flex-wrap gap-3"><Link href={index.url()} className="inline-flex min-h-11 items-center underline">Daftar SPK</Link><Link href={printRoute.url(order.id)} className="inline-flex min-h-11 items-center rounded-lg border px-4">Lihat / cetak SPK</Link><Button variant="outline" className="min-h-11" disabled={busy} onClick={() => router.reload()}>Muat ulang data</Button></div>
        <section className={`${panel} grid gap-3 sm:grid-cols-2`}><div><h2 className="font-semibold">{order.customer_snapshot.name}</h2><p className="text-sm">{order.customer_snapshot.phone ?? 'Tanpa telepon'}</p><p className="text-sm text-muted-foreground">{order.customer_snapshot.address}</p></div><div className="text-sm"><p>Dibuat: {dateLabel(order.created_at, timezone)} · {order.creator_name}</p><p>Deadline: {dateLabel(order.deadline, timezone)} ({timezone})</p><p className="mt-2 whitespace-pre-wrap break-words">{order.notes}</p></div></section>
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-accent p-4"><p>{active.length} item · <strong>{rupiah(order.total)}</strong></p><Button type="button" variant="outline" className="min-h-11 xl:hidden" onClick={() => setStep(3)}>Review pesanan</Button></div>
        {editable && <>
            <nav aria-label="Langkah pemesanan" className="grid grid-cols-4 gap-1 xl:hidden">{['Produk', 'Spesifikasi', 'Rincian', 'Review'].map((label, number) => <button key={label} type="button" aria-current={step === number ? 'step' : undefined} className={`min-h-11 rounded-lg border px-1 text-xs ${step === number ? 'bg-primary text-primary-foreground' : ''}`} onClick={() => setStep(number)}>{number + 1}. {label}</button>)}</nav>
            <form onKeyDown={(event) => { if (event.key === 'Enter' && (event.target as HTMLElement).tagName !== 'TEXTAREA' && window.matchMedia('(max-width: 1279px)').matches && step !== 2) { event.preventDefault(); if (product) setStep(2); } }} onSubmit={(event) => {
                event.preventDefault(); const options = { preserveScroll: true, onSuccess: () => { setEditing(null); setProduct(null); form.setData((data) => ({ ...data, ...blank })); setStep(3); } };
                if (editing) form.patch(update.url({ order: order.id, item: editing.id }), options); else form.post(store.url(order.id), options);
            }} className="grid gap-4 xl:grid-cols-3">
                <section className={`${panel} min-w-0 ${step === 0 ? '' : 'hidden xl:block'}`}><h2 className="mb-4 font-semibold">1. Produk</h2><label className="grid gap-2 text-sm">Cari nama / kode<input className={`${field} h-11`} value={search} onChange={(e) => setSearch(e.target.value)} /></label>{results.loading && <p className="mt-3 text-sm" role="status">Mencari produk…</p>}{results.error && <div role="alert"><p>{results.error}</p><Button type="button" variant="outline" className="min-h-11" onClick={results.retry}>Coba pencarian lagi</Button></div>}<div className="mt-4 grid max-h-96 gap-2 overflow-y-auto">{results.data?.products.map((value) => <button disabled={busy} key={value.id} type="button" onClick={() => selectProduct(value)} className="min-h-11 rounded-lg border p-3 text-left hover:bg-accent"><p className="text-sm font-medium">{value.name}</p><p className="text-xs text-muted-foreground">{value.code} · {value.category}</p></button>)}{results.data?.products.length === 0 && <p className="text-sm">Produk tidak ditemukan.</p>}</div></section>
                <section className={`${panel} min-w-0 ${step === 1 ? '' : 'hidden xl:block'}`}><h2 className="mb-4 font-semibold">2. Spesifikasi</h2>{product ? <div className="grid gap-4"><p className="font-medium">{product.name}</p>{Object.entries(specificationLabels).map(([key, label]) => { const name = key as keyof typeof specificationLabels; const options = [...new Set(product.prices.map((price) => price[name]))]; return <label className="grid gap-2 text-sm" key={key}>{label}<select className={`${field} h-11`} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)}><option value="">{options.includes('') ? 'Reguler / tanpa pilihan' : 'Pilih sesuai konfigurasi'}</option>{options.filter(Boolean).map((value) => <option key={value} value={value}>{value}</option>)}</select></label>; })}<p className="text-xs text-muted-foreground">Kombinasi harus tersedia pada matriks harga produk.</p><Button type="button" variant="outline" className="min-h-11 xl:hidden" onClick={() => setStep(2)}>Lanjut ke rincian</Button></div> : <p className="text-sm">{editing ? 'Memuat produk untuk revisi. Produk nonaktif tidak dapat dihitung ulang.' : 'Pilih produk terlebih dahulu.'}</p>}{editProduct.error && <div role="alert"><p>{editProduct.error}</p><Button type="button" variant="outline" className="min-h-11" onClick={editProduct.retry}>Coba pencarian lagi</Button></div>}</section>
                <section className={`${panel} min-w-0 xl:col-span-1 ${step === 2 ? '' : 'hidden xl:block'}`}><h2 className="mb-4 font-semibold">3. Rincian pesanan</h2><div className="grid gap-4">
                    <label className="grid gap-2 text-sm">Jumlah ({product?.unit_name ?? 'unit'})<input required inputMode="decimal" className={`${field} h-11`} value={form.data.qty} onChange={(e) => form.setData('qty', e.target.value)} /></label>
                    {product && product.pricing_basis !== 'quantity' && <label className="grid gap-2 text-sm">Panjang ({product.dimension_unit})<input required inputMode="decimal" className={`${field} h-11`} value={form.data.length} onChange={(e) => form.setData('length', e.target.value)} /></label>}
                    {product?.pricing_basis === 'area' && <label className="grid gap-2 text-sm">Lebar ({product.dimension_unit})<input required inputMode="decimal" className={`${field} h-11`} value={form.data.width} onChange={(e) => form.setData('width', e.target.value)} /></label>}
                    <label className="grid gap-2 text-sm">Lokasi proses (catatan kerja)<input maxLength={150} className={`${field} h-11`} value={form.data.process_location} onChange={(e) => form.setData('process_location', e.target.value)} /></label>
                    <label className="grid gap-2 text-sm">Deadline item ({timezone})<input type="datetime-local" className={`${field} h-11 min-w-0`} value={form.data.deadline} onChange={(e) => form.setData('deadline', e.target.value)} /></label>
                    <label className="grid gap-2 text-sm">Catatan pekerjaan<textarea maxLength={2000} className={`${field} h-20 py-2`} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} /></label>
                    {preview.processing && <p role="status">Menghitung estimasi…</p>}{previewError && <p className="text-sm text-destructive" role="alert">{previewError}</p>}{quote && <div className="rounded-lg border p-3"><ProductQuoteSummary quote={quote} context="preview" /></div>}
                    <Button className="min-h-11" disabled={busy || !product}>{form.processing ? 'Menyimpan…' : editing ? 'Simpan revisi item' : 'Tambahkan ke SPK'}</Button>
                    {form.data.product_id && <Button type="button" variant="outline" className="min-h-11" disabled={busy} onClick={() => { setEditing(null); setProduct(null); form.setData((data) => ({ ...data, ...blank })); setStep(0); }}>Batal isian item</Button>}
                </div></section>
            </form>
        </>}
        <section className={`grid gap-4 ${editable && step !== 3 ? 'hidden xl:grid' : ''}`} aria-label="Review SPK">
            <h2 className="text-lg font-semibold">Review pesanan</h2>{!active.length && <p className={panel}>Belum ada item. Pilih produk untuk mengisi SPK.</p>}
            {active.map((item) => <article key={item.id} className={`${panel} grid gap-4`}><h3 className="font-semibold">{item.snapshot.product_name}</h3><ProductQuoteSummary quote={item.snapshot} context="order" /><p className="whitespace-pre-wrap break-words text-sm">{item.notes}</p><p className="text-sm">Lokasi: {item.process_location || 'Belum ditentukan'} · Deadline: {dateLabel(item.deadline, timezone)}</p>{editable && <div className="flex flex-wrap gap-3"><Button type="button" className="min-h-11" variant="outline" disabled={busy} onClick={() => revise(item)}>Revisi item</Button><Button type="button" className="min-h-11" variant="outline" disabled={busy} onClick={() => { if (window.confirm('Keluarkan item ini dari draft? Riwayat tetap disimpan.')) mutation.delete(destroy.url({ order: order.id, item: item.id }), { preserveScroll: true }); }}>Hapus dari draft</Button></div>}</article>)}
            <div className={`${panel} grid gap-4`}><p className="text-xl font-semibold">Total item: {rupiah(order.total)}</p><p className="text-sm text-muted-foreground">Total ini belum mencakup diskon, pajak, ongkir atau pembayaran.</p>{editable && <><p className="text-sm">Setelah dikonfirmasi, item terkunci. Untuk koreksi berikutnya, batalkan dengan alasan dan buat SPK pengganti.</p>{form.data.product_id && <p role="status" className="text-sm">Simpan atau batalkan isian item sebelum konfirmasi.</p>}<Button className="min-h-11" disabled={busy || !active.length || Boolean(form.data.product_id)} onClick={() => { if (window.confirm('Konfirmasi dan kunci rincian SPK ini?')) changeStatus('confirmed'); }}>Konfirmasi SPK</Button></>}
                {canManage && order.status !== 'cancelled' && <form className="grid gap-3 border-t pt-4" onSubmit={(e) => { e.preventDefault(); if (window.confirm('Batalkan SPK ini? Nomor dan riwayat tetap tersimpan.')) changeStatus('cancelled'); }}><label className="grid gap-2 text-sm">Alasan pembatalan<textarea required maxLength={1000} className={`${field} h-20 py-2`} value={mutation.data.reason} onChange={(e) => mutation.setData('reason', e.target.value)} /></label><Button variant="outline" className="min-h-11" disabled={busy}>Batalkan SPK</Button></form>}
            </div>
            <details className={panel}><summary className="min-h-11 cursor-pointer font-semibold">Riwayat perubahan</summary><ol className="grid gap-3">{order.events.map((event) => <li className="rounded-lg border p-3 text-sm" key={event.id}><p>{eventLabels[event.action] ?? event.action} · {event.actor_name}</p><p>{dateLabel(event.created_at, timezone)}</p>{event.metadata.to && <p>{event.metadata.from} → {event.metadata.to}</p>}{event.metadata.item_id && <p>Item #{event.metadata.item_id}{event.metadata.previous_item_id ? ` menggantikan #${event.metadata.previous_item_id}` : ''}</p>}{event.metadata.reason && <p className="whitespace-pre-wrap break-words">{event.metadata.reason}</p>}</li>)}</ol>{order.items.filter((item) => item.state !== 'active').map((item) => <details className="mt-3 rounded-lg border p-3" key={item.id}><summary>Item #{item.id} · {item.state === 'removed' ? 'Dihapus dari draft' : 'Digantikan'} · {item.snapshot.product_name}</summary><ProductQuoteSummary quote={item.snapshot} context="order" /></details>)}</details>
        </section>
    </ErpPage></>;
}
