import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ErpPage, field, panel } from '@/components/erp-page';
import { ProductFields } from '@/components/product-fields';
import { ProductQuoteSummary } from '@/components/product-quote-summary';
import { Button } from '@/components/ui/button';
import { index, update as updateProduct } from '@/routes/products';
import {
    store as storePrice,
    update as updatePrice,
} from '@/routes/product-prices';
import { store as calculate, show as showQuote } from '@/routes/product-quotes';
import { pricingLabels, rupiah } from '@/types/product';
import type {
    PriceQuote,
    Product,
    ProductData,
    ProductPrice,
} from '@/types/product';

type PriceData = Omit<ProductPrice, 'id' | 'qty_max' | 'hpp'> & {
    qty_max: string;
    hpp: string;
};
const emptyPrice: PriceData = {
    detail_product: '',
    variant: '',
    machine: '',
    print_side: '',
    finishing: '',
    qty_min: '1',
    qty_max: '',
    unit_price: '',
    hpp: '0',
    minimum_charge: '0',
    is_active: true,
};
function PriceEditor({
    productId,
    price,
    onCancel,
}: {
    productId: number;
    price: ProductPrice | null;
    onCancel: () => void;
}) {
    const form = useForm<PriceData>(
        price
            ? {
                  detail_product: price.detail_product,
                  variant: price.variant,
                  machine: price.machine,
                  print_side: price.print_side,
                  finishing: price.finishing,
                  qty_min: price.qty_min,
                  qty_max: price.qty_max ?? '',
                  unit_price: price.unit_price,
                  hpp: price.hpp ?? '0',
                  minimum_charge: price.minimum_charge,
                  is_active: price.is_active,
              }
            : emptyPrice,
    );
    return (
        <form
            className={`${panel} grid gap-4`}
            onSubmit={(event) => {
                event.preventDefault();
                const options = {
                    onSuccess: () => {
                        form.reset();
                        onCancel();
                    },
                };
                if (price) {
                    form.patch(
                        updatePrice.url({
                            product: productId,
                            price: price.id,
                        }),
                        options,
                    );
                } else {
                    form.post(storePrice.url(productId), options);
                }
            }}
        >
            <h2 className="font-semibold">
                {price ? 'Ubah Harga' : 'Tambah Harga'}
            </h2>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                {(
                    [
                        ['detail_product', 'Detail produk / harga'],
                        ['variant', 'Varian / Bahan'],
                        ['machine', 'Mesin / Jenis print'],
                        ['print_side', 'Sisi cetak'],
                        ['finishing', 'Finishing'],
                    ] as const
                ).map(([key, label]) => (
                    <label key={key} className="grid gap-2 text-sm">
                        {label}
                        <input
                            className={field}
                            maxLength={key === 'detail_product' ? 255 : 100}
                            required={key === 'detail_product'}
                            value={form.data[key]}
                            onChange={(event) =>
                                form.setData(key, event.target.value)
                            }
                        />
                    </label>
                ))}
                {(
                    [
                        ['qty_min', 'Jumlah minimum'],
                        ['qty_max', 'Jumlah maksimum (kosong = tanpa batas)'],
                        ['unit_price', 'Harga jual (Rp)'],
                        ['hpp', 'HPP (Rp)'],
                        ['minimum_charge', 'Harga minimum per item (Rp)'],
                    ] as const
                ).map(([key, label]) => (
                    <label key={key} className="grid gap-2 text-sm">
                        {label}
                        <input
                            className={field}
                            type="number"
                            min="0"
                            step={key.startsWith('qty_') ? '0.0001' : '0.01'}
                            required={key !== 'qty_max'}
                            value={form.data[key]}
                            onChange={(event) =>
                                form.setData(key, event.target.value)
                            }
                        />
                    </label>
                ))}
            </div>
            <p className="text-xs leading-5 text-muted-foreground">
                Rentang inklusif; contoh 1–99, lalu 100 ke atas. Rentang aktif
                dengan spesifikasi sama tidak boleh bertumpuk. Finishing yang
                dipilih sudah termasuk dalam harga baris ini. Masukkan nominal
                tanpa pemisah ribuan.
            </p>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={form.data.is_active}
                    onChange={(event) =>
                        form.setData('is_active', event.target.checked)
                    }
                />
                Harga aktif
            </label>
            <div className="flex gap-2">
                <Button disabled={form.processing}>Simpan harga</Button>
                {price && (
                    <Button type="button" variant="outline" onClick={onCancel}>
                        Batal
                    </Button>
                )}
            </div>
        </form>
    );
}

function Calculator({
    product,
    prices,
    quote,
}: {
    product: Product;
    prices: ProductPrice[];
    quote: PriceQuote | null;
}) {
    const combinations = [
        ...new Map(
            prices
                .filter((price) => price.is_active)
                .map((price) => [
                    JSON.stringify([
                        price.variant,
                        price.machine,
                        price.print_side,
                        price.finishing,
                    ]),
                    price,
                ]),
        ).values(),
    ];
    const first = combinations[0];
    const form = useForm({
        qty: '1',
        length: '',
        width: '',
        variant: first?.variant ?? '',
        machine: first?.machine ?? '',
        print_side: first?.print_side ?? '',
        finishing: first?.finishing ?? '',
    });
    const [showResult, setShowResult] = useState(quote !== null);
    const currentKey = JSON.stringify([
        form.data.variant,
        form.data.machine,
        form.data.print_side,
        form.data.finishing,
    ]);
    return (
        <section className={`${panel} grid gap-5 lg:grid-cols-2`}>
            <form
                className="grid gap-4"
                onChange={() => setShowResult(false)}
                onSubmit={(event) => {
                    event.preventDefault();
                    setShowResult(false);
                    form.post(calculate.url(product.id), {
                        preserveScroll: true,
                        onSuccess: () => setShowResult(true),
                    });
                }}
            >
                <h2 className="font-semibold">Simulasi Harga</h2>
                <label className="grid gap-2 text-sm">
                    Spesifikasi
                    <select
                        className={field}
                        value={currentKey}
                        disabled={!first}
                        onChange={(event) => {
                            const price = combinations.find(
                                (item) =>
                                    JSON.stringify([
                                        item.variant,
                                        item.machine,
                                        item.print_side,
                                        item.finishing,
                                    ]) === event.target.value,
                            );
                            if (price) {
                                form.setData({
                                    ...form.data,
                                    variant: price.variant,
                                    machine: price.machine,
                                    print_side: price.print_side,
                                    finishing: price.finishing,
                                });
                            }
                        }}
                    >
                        {combinations.map((price) => (
                            <option
                                key={price.id}
                                value={JSON.stringify([
                                    price.variant,
                                    price.machine,
                                    price.print_side,
                                    price.finishing,
                                ])}
                            >
                                {[
                                    price.variant,
                                    price.machine,
                                    price.print_side,
                                    price.finishing,
                                ]
                                    .filter(Boolean)
                                    .join(' · ') || 'Reguler'}
                            </option>
                        ))}
                    </select>
                </label>
                <div className="grid gap-3 sm:grid-cols-3">
                    <label className="grid gap-2 text-sm">
                        Jumlah (
                        {product.pricing_basis === 'quantity'
                            ? product.unit_name
                            : 'buah'}
                        )
                        <input
                            className={field}
                            type="number"
                            required
                            min="0.0001"
                            max="1000000"
                            step="0.0001"
                            value={form.data.qty}
                            onChange={(event) =>
                                form.setData('qty', event.target.value)
                            }
                        />
                    </label>
                    {product.pricing_basis !== 'quantity' && (
                        <label className="grid gap-2 text-sm">
                            Panjang ({product.dimension_unit})
                            <input
                                className={field}
                                type="number"
                                required
                                min="0.0001"
                                max="10000"
                                step="0.0001"
                                value={form.data.length}
                                onChange={(event) =>
                                    form.setData('length', event.target.value)
                                }
                            />
                        </label>
                    )}
                    {product.pricing_basis === 'area' && (
                        <label className="grid gap-2 text-sm">
                            Lebar ({product.dimension_unit})
                            <input
                                className={field}
                                type="number"
                                required
                                min="0.0001"
                                max="10000"
                                step="0.0001"
                                value={form.data.width}
                                onChange={(event) =>
                                    form.setData('width', event.target.value)
                                }
                            />
                        </label>
                    )}
                </div>
                <p className="text-xs leading-5 text-muted-foreground">
                    Dasar harga: {pricingLabels[product.pricing_basis]}. Rentang
                    berdasarkan{' '}
                    {product.tier_basis === 'quantity'
                        ? 'jumlah pesanan'
                        : 'jumlah setelah ukuran dihitung'}
                    . Jumlah di bawah 1 memakai rentang 1; tagihan tetap memakai
                    jumlah sebenarnya. Total dibulatkan ke rupiah terdekat.
                </p>
                <Button
                    className="justify-self-start"
                    disabled={form.processing || !product.is_active || !first}
                >
                    Hitung harga
                </Button>
                {!product.is_active && (
                    <p className="text-sm text-muted-foreground">
                        Aktifkan produk untuk simulasi.
                    </p>
                )}
                {!first && (
                    <p className="text-sm text-muted-foreground">
                        Belum ada harga aktif untuk produk ini.
                    </p>
                )}
            </form>
            <div
                aria-live="polite"
                className="flex flex-col justify-center rounded-lg bg-muted/50 p-5"
            >
                {quote && showResult ? (
                    <div className="grid gap-4">
                        <ProductQuoteSummary quote={quote} />
                        <Link className="text-sm underline underline-offset-4" href={showQuote.url({ product: product.id, quote: quote.uuid })}>Buka hasil tersimpan</Link>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Pilih spesifikasi dan isi jumlah untuk melihat harga.
                        Hasil dihitung oleh server.
                    </p>
                )}
            </div>
        </section>
    );
}

export default function ProductDetail({
    product,
    prices,
    canManage,
    quote,
    savedQuotes,
}: {
    product: Product;
    prices: ProductPrice[];
    canManage: boolean;
    quote: PriceQuote | null;
    savedQuotes: { uuid: string; quoted_at: string; qty: string; subtotal: string }[];
}) {
    const form = useForm<ProductData>({
        code: product.code,
        name: product.name,
        category: product.category,
        unit_name: product.unit_name,
        pricing_basis: product.pricing_basis,
        tier_basis: product.tier_basis,
        dimension_unit: product.dimension_unit,
        minimum_length: product.minimum_length,
        minimum_width: product.minimum_width,
        is_active: product.is_active,
    });
    const [editing, setEditing] = useState<ProductPrice | null>(null);
    return (
        <>
            <Head title={product.name} />
            <ErpPage
                title={product.name}
                description={`${product.code} · ${product.category} · ${product.is_active ? 'Aktif' : 'Nonaktif'}`}
            >
                <Link
                    className="text-sm underline underline-offset-4"
                    href={index.url()}
                >
                    ← Daftar produk
                </Link>
                <Calculator
                    key={product.id}
                    product={product}
                    prices={prices}
                    quote={quote}
                />
                {savedQuotes.length > 0 && <section className={`${panel} grid gap-4`}>
                    <h2 className="font-semibold">Perhitungan terbaru</h2>
                    <p className="text-sm text-muted-foreground">Sepuluh hasil terakhir untuk produk ini. Perubahan harga master tidak mengubah hasil tersimpan.</p>
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {savedQuotes.map((saved) => <li key={saved.uuid}>
                            <Link className="flex min-h-11 flex-wrap items-center justify-between gap-2 rounded-lg border p-3 text-sm hover:bg-muted" href={showQuote.url({ product: product.id, quote: saved.uuid })}>
                                <span>{new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(saved.quoted_at))} · Qty {saved.qty}</span>
                                <span className="font-medium">{rupiah(saved.subtotal)}</span>
                            </Link>
                        </li>)}
                    </ul>
                </section>}
                <section className={panel}>
                    <h2 className="mb-5 font-semibold">Matriks Harga</h2>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead>
                                <tr className="border-b text-muted-foreground">
                                    <th className="pb-3 pr-5">
                                        Detail & Spesifikasi
                                    </th>
                                    <th className="pr-5">Rentang</th>
                                    <th className="pr-5">Harga jual</th>
                                    {canManage && <th className="pr-5">HPP</th>}
                                    <th className="pr-5">Minimum</th>
                                    <th className="pr-5">Status</th>
                                    {canManage && <th>Ubah</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {prices.map((price) => (
                                    <tr
                                        key={price.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="py-4 pr-5">
                                            <p className="font-medium">
                                                {price.detail_product}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {[
                                                    price.variant,
                                                    price.machine,
                                                    price.print_side,
                                                    price.finishing,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') || 'Reguler'}
                                            </p>
                                        </td>
                                        <td className="pr-5 whitespace-nowrap">
                                            {Number(price.qty_min)}–
                                            {price.qty_max === null
                                                ? '∞'
                                                : Number(price.qty_max)}
                                        </td>
                                        <td className="pr-5 whitespace-nowrap">
                                            {rupiah(price.unit_price)}
                                        </td>
                                        {canManage && (
                                            <td className="pr-5 whitespace-nowrap">
                                                {rupiah(price.hpp ?? '0')}
                                            </td>
                                        )}
                                        <td className="pr-5 whitespace-nowrap">
                                            {rupiah(price.minimum_charge)}
                                        </td>
                                        <td className="pr-5">
                                            {price.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'}
                                        </td>
                                        {canManage && (
                                            <td>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        setEditing(price)
                                                    }
                                                >
                                                    Ubah
                                                </Button>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {prices.length === 0 && (
                        <p className="py-5 text-sm text-muted-foreground">
                            Belum ada harga. Lengkapi matriks sebelum produk
                            dipakai dalam SPK.
                        </p>
                    )}
                </section>
                {canManage && (
                    <>
                        <PriceEditor
                            key={editing?.id ?? 'new'}
                            productId={product.id}
                            price={editing}
                            onCancel={() => setEditing(null)}
                        />
                        <form
                            className={`${panel} grid gap-5`}
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.patch(updateProduct.url(product.id));
                            }}
                        >
                            <h2 className="font-semibold">Pengaturan Produk</h2>
                            <ProductFields
                                data={form.data}
                                setData={form.setData}
                            />
                            <Button
                                className="justify-self-start"
                                disabled={form.processing}
                            >
                                Simpan pengaturan
                            </Button>
                        </form>
                    </>
                )}
            </ErpPage>
        </>
    );
}
