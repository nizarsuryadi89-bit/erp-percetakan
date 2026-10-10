import { Head, Link, useForm, useHttp, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { DeskprintConnection } from '@/components/deskprint-connection';
import { Button } from '@/components/ui/button';
import { useDeskprintSearch } from '@/hooks/use-deskprint-search';
import { index as searchCustomers, store as storeCustomer } from '@/routes/customers';
import { index, store } from '@/routes/print-orders';
import type { Customer } from '@/types/deskprint';
import { methodLabels } from '@/types/deskprint';
type Props = { branches: { id: number; name: string }[]; orderKey: string; customerKey: string; timezone: string };
export default function CreateOrder({ branches, orderKey, customerKey, timezone }: Props) {
    const workspace = usePage().props.workspace;
    const defaultBranch = branches.find((branch) => branch.id === workspace.branchId)?.id ?? branches[0]?.id;
    const form = useForm({ request_key: orderKey, branch_id: String(defaultBranch ?? ''), customer_id: '', method: 'walk_in', marketplace_name: '', marketplace_reference: '', marketplace_tracking: '', deadline: '', notes: '' });
    const [search, setSearch] = useState(''); const [selected, setSelected] = useState<Customer | null>(null);
    const [adding, setAdding] = useState(false); const [failure, setFailure] = useState('');
    const lookup = useDeskprintSearch<{ customers: Customer[] }>(form.data.branch_id ? searchCustomers.url({ query: { branch_id: form.data.branch_id, search } }) : null);
    const customer = useHttp<{ request_key: string; branch_id: string; name: string; phone: string; address: string }, { customer: Customer; request_key: string }>({ request_key: customerKey, branch_id: form.data.branch_id, name: '', phone: '', address: '' });
    function choose(value: Customer) { setSelected(value); form.setData('customer_id', String(value.id)); }
    return <><Head title="Buat SPK" /><ErpPage title="Mulai pemesanan" description="Pilih metode dan pelanggan. Nomor SPK dibuat sebelum item dan pembayaran.">
        <DeskprintConnection /><Link className="underline" href={index.url()}>Kembali ke daftar SPK</Link>
        {!branches.length ? <p className={panel}>Belum ada cabang yang ditugaskan kepada Anda. Minta pengelola mengatur akses cabang.</p> : <>
            <section className={`${panel} grid gap-4 md:grid-cols-2`}>
                <label className="grid gap-2 text-sm">Cabang<select disabled={form.processing || customer.processing} className={`${field} h-11`} value={form.data.branch_id} onChange={(e) => { form.setData({ ...form.data, branch_id: e.target.value, customer_id: '' }); setSelected(null); customer.setData('branch_id', e.target.value); }} >{branches.map((branch) => <option key={branch.id} value={branch.id}>{branch.name}</option>)}</select></label>
                <label className="grid gap-2 text-sm">Metode pesanan<select className={`${field} h-11`} value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>{Object.entries(methodLabels).map(([key, label]) => <option key={key} value={key}>{label}</option>)}</select></label>
            </section>
            <section className={`${panel} grid gap-4`}>
                <h2 className="font-semibold">Pelanggan</h2><label className="grid gap-2 text-sm">Cari nama atau nomor telepon<input className={`${field} h-11`} value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Ketik nama / telepon" /></label>
                {lookup.loading && <p role="status">Mencari pelanggan…</p>}{lookup.error && <div role="alert"><p>{lookup.error}</p><Button type="button" variant="outline" className="min-h-11" onClick={lookup.retry}>Coba pencarian lagi</Button></div>}
                <div className="grid max-h-64 gap-2 overflow-y-auto">{lookup.data?.customers.map((value) => <button disabled={form.processing || customer.processing} type="button" key={value.id} aria-pressed={selected?.id === value.id} onClick={() => choose(value)} className={`min-h-11 rounded-lg border p-3 text-left ${selected?.id === value.id ? 'border-primary bg-accent' : ''}`}><span className="font-medium">{value.name}</span><span className="ml-2 text-sm text-muted-foreground">{value.phone ?? 'Tanpa telepon'}</span></button>)}</div>
                {selected && <p role="status" className="text-sm">Dipilih: <strong>{selected.name}</strong></p>}
                <Button type="button" variant="outline" className="min-h-11 justify-self-start" onClick={() => setAdding(!adding)}>Tambah pelanggan</Button>
                {adding && <form className="grid gap-3 rounded-lg border p-4" onSubmit={(e) => {
                    e.preventDefault(); setFailure(''); customer.transform((data) => ({ ...data, branch_id: form.data.branch_id }));
                    void customer.post(storeCustomer.url(), { onSuccess: (response) => { choose(response.customer); customer.reset(); customer.setData('request_key', response.request_key); setAdding(false); }, onNetworkError: () => { setFailure('Koneksi terputus. Isian tetap tersedia; coba simpan kembali.'); return false; }, onHttpException: () => { setFailure('Penyimpanan ditolak server. Periksa akses atau muat ulang halaman.'); return false; } }).catch(() => {});
                }}>
                    <label className="grid gap-2 text-sm">Nama<input required maxLength={150} className={`${field} h-11`} value={customer.data.name} onChange={(e) => customer.setData('name', e.target.value)} /></label>
                    <label className="grid gap-2 text-sm">Telepon (opsional)<input inputMode="tel" maxLength={30} className={`${field} h-11`} value={customer.data.phone} onChange={(e) => customer.setData('phone', e.target.value)} /></label>
                    <label className="grid gap-2 text-sm">Alamat (opsional)<textarea maxLength={1000} className={`${field} h-20 py-2`} value={customer.data.address} onChange={(e) => customer.setData('address', e.target.value)} /></label>
                    {Object.values(customer.errors).map((error, i) => <p key={i} role="alert" className="text-sm text-destructive">{error}</p>)}{failure && <p role="alert">{failure}</p>}
                    <Button className="min-h-11" disabled={customer.processing || form.processing}>{customer.processing ? 'Menyimpan…' : 'Simpan pelanggan'}</Button>
                </form>}
            </section>
            <form className={`${panel} grid gap-4`} onSubmit={(e) => { e.preventDefault(); form.post(store.url()); }}>
                {form.data.method === 'marketplace' && <fieldset className="grid gap-3"><legend className="mb-3 font-semibold">Informasi marketplace</legend>{(['marketplace_name', 'marketplace_reference', 'marketplace_tracking'] as const).map((key, i) => <label className="grid gap-2 text-sm" key={key}>{['Nama marketplace', 'Nomor pesanan marketplace', 'Nomor resi (opsional)'][i]}<input required={i === 0} maxLength={i === 0 ? 100 : 150} className={`${field} h-11`} value={form.data[key]} onChange={(e) => form.setData(key, e.target.value)} /></label>)}</fieldset>}
                <label className="grid gap-2 text-sm">Deadline (opsional, {timezone})<input type="datetime-local" className={`${field} h-11 min-w-0`} value={form.data.deadline} onChange={(e) => form.setData('deadline', e.target.value)} /></label>
                <label className="grid gap-2 text-sm">Catatan pesanan<textarea maxLength={2000} className={`${field} h-24 py-2`} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} /></label>
                <Button className="min-h-11" disabled={form.processing || !selected || customer.processing}>{form.processing ? 'Membuat draft…' : 'Buat SPK & pilih produk'}</Button>
            </form>
        </>}
    </ErpPage></>;
}
