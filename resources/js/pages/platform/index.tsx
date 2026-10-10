import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/platform';
import { show } from '@/routes/platform/companies';

type CompanyRow = { id: number; uuid: string; name: string; is_active: boolean; users_count: number };
type Companies = { data: CompanyRow[]; total: number; prev_page_url: string | null; next_page_url: string | null };

export default function Platform({ companies, search }: { companies: Companies; search: string }) {
    const [query, setQuery] = useState(search);
    return <>
        <Head title="Panel Developer" />
        <ErpPage title="Panel Developer" description="Kelola hak modul dan masa akses perusahaan pelanggan.">
            <section className={panel}>
                <form className="flex flex-wrap gap-3" onSubmit={(event) => {
                    event.preventDefault();
                    router.get(index.url(), { search: query }, { preserveState: true });
                }}>
                    <input className={`${field} max-w-sm`} aria-label="Cari perusahaan" placeholder="Cari perusahaan…" value={query} maxLength={100} onChange={(event) => setQuery(event.target.value)} />
                    <Button variant="outline">Cari</Button>
                </form>
                <p className="mt-4 text-sm text-muted-foreground">{companies.total} perusahaan</p>
                <div className="mt-4 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead><tr className="border-b"><th className="pb-3">Perusahaan</th><th>Status</th><th>Pengguna</th><th>Hak modul</th></tr></thead>
                        <tbody>{companies.data.map((company) => <tr className="border-b" key={company.id}>
                            <td className="py-4 pr-4 font-medium">{company.name}</td>
                            <td>{company.is_active ? 'Aktif' : 'Tidak aktif'}</td>
                            <td>{company.users_count}</td>
                            <td><Link className="underline underline-offset-4" href={show.url(company.uuid)}>Atur modul</Link></td>
                        </tr>)}</tbody>
                    </table>
                </div>
                {!companies.data.length && <p className="mt-5 text-sm text-muted-foreground">Belum ada perusahaan yang cocok.</p>}
                <div className="mt-5 flex gap-4">
                    {companies.prev_page_url && <Link href={companies.prev_page_url}>Sebelumnya</Link>}
                    {companies.next_page_url && <Link href={companies.next_page_url}>Berikutnya</Link>}
                </div>
            </section>
        </ErpPage>
    </>;
}
