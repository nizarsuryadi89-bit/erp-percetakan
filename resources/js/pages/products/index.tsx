import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { ProductFields } from '@/components/product-fields';
import { Button } from '@/components/ui/button';
import { index, show, store } from '@/routes/products';
import { emptyProduct, pricingLabels } from '@/types/product';
import type { Product, ProductData } from '@/types/product';

type ProductPage = {
    data: Product[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
export default function Products({
    products,
    search,
    canManage,
}: {
    products: ProductPage;
    search: string;
    canManage: boolean;
}) {
    const form = useForm<ProductData>(emptyProduct);
    const [searchText, setSearchText] = useState(search);
    return (
        <>
            <Head title="Produk & Harga" />
            <ErpPage
                title="Produk & Harga"
                description="Katalog dan matriks harga untuk transaksi dan SPK. Gunakan istilah kategori, satuan, dan spesifikasi seperti di ERP TGM."
            >
                <section className={panel}>
                    <form
                        className="mb-5 flex flex-wrap gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                index.url(),
                                { search: searchText },
                                { preserveState: true },
                            );
                        }}
                    >
                        <input
                            aria-label="Cari nama atau kode produk"
                            className={`${field} max-w-sm`}
                            value={searchText}
                            onChange={(event) =>
                                setSearchText(event.target.value)
                            }
                            placeholder="Cari nama atau kode produk…"
                        />
                        <Button variant="outline">Cari</Button>
                    </form>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b text-muted-foreground">
                                    <th className="pb-3">Produk</th>
                                    <th>Kategori / Satuan</th>
                                    <th>Dasar harga</th>
                                    <th>Status</th>
                                    <th>Harga</th>
                                </tr>
                            </thead>
                            <tbody>
                                {products.data.map((product) => (
                                    <tr
                                        className="border-b last:border-0"
                                        key={product.id}
                                    >
                                        <td className="py-4 pr-4">
                                            <Link
                                                className="font-medium underline underline-offset-4"
                                                href={show.url(product.id)}
                                            >
                                                {product.name}
                                            </Link>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {product.code}
                                            </p>
                                        </td>
                                        <td className="pr-4">
                                            {product.category}
                                            <p className="text-xs text-muted-foreground">
                                                {product.unit_name}
                                            </p>
                                        </td>
                                        <td className="pr-4">
                                            {
                                                pricingLabels[
                                                    product.pricing_basis
                                                ]
                                            }
                                        </td>
                                        <td className="pr-4">
                                            {product.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'}
                                        </td>
                                        <td>{product.prices_count} baris</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {products.data.length === 0 && (
                        <p className="py-8 text-sm text-muted-foreground">
                            {search
                                ? 'Produk tidak ditemukan.'
                                : 'Belum ada produk. Tambahkan produk, lalu lengkapi matriks harga.'}
                        </p>
                    )}
                    <div className="mt-5 flex items-center justify-between gap-3 text-xs text-muted-foreground">
                        <span>
                            {products.total} produk · Halaman{' '}
                            {products.current_page} dari {products.last_page}
                        </span>
                        <div className="flex gap-2">
                            {products.prev_page_url && (
                                <Link
                                    className="rounded-md border px-3 py-2"
                                    href={products.prev_page_url}
                                >
                                    Sebelumnya
                                </Link>
                            )}
                            {products.next_page_url && (
                                <Link
                                    className="rounded-md border px-3 py-2"
                                    href={products.next_page_url}
                                >
                                    Berikutnya
                                </Link>
                            )}
                        </div>
                    </div>
                </section>
                {canManage && (
                    <form
                        className={`${panel} grid gap-5`}
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(store.url());
                        }}
                    >
                        <h2 className="font-semibold">Tambah Produk</h2>
                        <ProductFields
                            data={form.data}
                            setData={form.setData}
                        />
                        <Button
                            disabled={form.processing}
                            className="justify-self-start"
                        >
                            Simpan produk
                        </Button>
                    </form>
                )}
            </ErpPage>
        </>
    );
}
