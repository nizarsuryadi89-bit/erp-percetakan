import { Head, Link, useForm } from '@inertiajs/react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/platform';
import { update } from '@/routes/platform/companies';
import type { Company, License, PlatformModule } from '@/types/erp';

type Grant = { module_id: number; is_granted: boolean; is_enabled: boolean };
type AccessForm = { module_codes: string[]; access_status: string; expires_at: string; module_expiries: Record<string, string> };

export default function CompanyModules({ company, modules, entitlements, license, expiresOn, moduleExpiries }: {
    company: Company & { is_active: boolean };
    modules: PlatformModule[];
    entitlements: Grant[];
    license: License;
    expiresOn: string | null;
    moduleExpiries: Record<string, string>;
}) {
    const form = useForm<AccessForm>({
        module_codes: modules.filter((module) => entitlements.some((row) => row.module_id === module.id && row.is_granted)).map((module) => module.code),
        access_status: license.status,
        expires_at: expiresOn ?? '',
        module_expiries: moduleExpiries,
    });
    function selectModule(code: string, checked: boolean) {
        const selected = new Set(form.data.module_codes);
        const visit = (current: string) => {
            if (selected.has(current)) return;
            selected.add(current);
            modules.find((module) => module.code === current)?.dependencies.forEach((dependency) => visit(dependency.code));
        };
        if (checked) visit(code);
        else {
            selected.delete(code);
            let changed = true;
            while (changed) {
                changed = false;
                modules.forEach((module) => {
                    if (selected.has(module.code) && module.dependencies.some((dependency) => !selected.has(dependency.code))) {
                        selected.delete(module.code);
                        changed = true;
                    }
                });
            }
        }
        form.setData('module_codes', [...selected]);
    }
    return <>
        <Head title={`Hak modul — ${company.name}`} />
        <ErpPage title={company.name} description="Centang modul yang diberikan. Modul pendukung dipilih bersama; pencabutan akses tidak menghapus data perusahaan.">
            <Link className="text-sm underline underline-offset-4" href={index.url()}>Kembali ke perusahaan</Link>
            {!company.is_active && <p role="status" className={panel}>Perusahaan tidak aktif. Hak modul tidak mengaktifkan perusahaan secara otomatis.</p>}
            <form className="grid gap-6" onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({ ...data, module_expiries: Object.fromEntries(data.module_codes.map((code) => [code, data.module_expiries[code] ?? ''])) }));
                form.patch(update.url(company.uuid), { preserveScroll: true });
            }}>
                <section className={`${panel} grid gap-4 sm:grid-cols-2`}>
                    <label className="grid gap-2 text-sm">Status akses
                        <select className={field} value={form.data.access_status} onChange={(event) => form.setData('access_status', event.target.value)}>
                            <option value="pending">Menunggu aktivasi</option><option value="active">Aktif</option><option value="suspended">Ditangguhkan</option>
                        </select>
                    </label>
                    <label className="grid gap-2 text-sm">Batas akses perusahaan (kosong = tanpa batas)
                        <input className={field} type="date" value={form.data.expires_at} onChange={(event) => form.setData('expires_at', event.target.value)} />
                    </label>
                </section>
                <section className="grid gap-4 md:grid-cols-2">{modules.map((module) => {
                    const selected = form.data.module_codes.includes(module.code);
                    const row = entitlements.find((item) => item.module_id === module.id);
                    return <article className={panel} key={module.code}>
                        <label className="flex items-center gap-3 font-medium">
                            <input type="checkbox" checked={selected} disabled={!module.is_active && !selected} onChange={(event) => selectModule(module.code, event.target.checked)} />
                            {module.name}
                        </label>
                        <p className="mt-3 text-sm text-muted-foreground">{module.description}</p>
                        <p className="mt-3 text-xs text-muted-foreground">{module.dependencies.length ? `Memerlukan: ${module.dependencies.map((item) => item.name).join(', ')}` : 'Tidak memerlukan modul lain'}</p>
                        {!module.is_active && <p className="mt-2 text-xs">Modul belum tersedia.</p>}
                        {selected && row && !row.is_enabled && <p className="mt-2 text-xs">Modul disembunyikan oleh perusahaan; grant tidak mengubah pilihan tersebut.</p>}
                        <label className="mt-4 grid gap-2 text-sm">Batas akses modul (kosong = tanpa batas)
                            <input type="date" className={field} disabled={!selected} value={form.data.module_expiries[module.code] ?? ''} onChange={(event) => form.setData('module_expiries', { ...form.data.module_expiries, [module.code]: event.target.value })} />
                        </label>
                    </article>;
                })}</section>
                <Button className="justify-self-start" disabled={form.processing}>Simpan hak akses</Button>
            </form>
        </ErpPage>
    </>;
}
