import { Head, useForm } from '@inertiajs/react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/companies';
import type { Company } from '@/types/erp';

export default function CompanyEdit({ company }: { company: Company }) {
    const form = useForm({
        name: company.name,
        phone: company.phone ?? '',
        address: company.address ?? '',
    });
    return (
        <>
            <Head title="Profil Perusahaan" />
            <ErpPage
                title="Profil Perusahaan"
                description="Identitas perusahaan yang digunakan untuk dokumen dan kegiatan operasional."
            >
                <form
                    className={`${panel} grid max-w-2xl gap-5`}
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(update.url());
                    }}
                >
                    <label className="grid gap-2 text-sm">
                        Nama perusahaan
                        <input
                            className={field}
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                    </label>
                    <label className="grid gap-2 text-sm">
                        Nomor telepon
                        <input
                            className={field}
                            value={form.data.phone}
                            onChange={(e) =>
                                form.setData('phone', e.target.value)
                            }
                            maxLength={30}
                        />
                    </label>
                    <label className="grid gap-2 text-sm">
                        Alamat
                        <textarea
                            className={`${field} h-24 py-2`}
                            value={form.data.address}
                            onChange={(e) =>
                                form.setData('address', e.target.value)
                            }
                        />
                    </label>
                    <Button disabled={form.processing}>Simpan perubahan</Button>
                </form>
            </ErpPage>
        </>
    );
}
