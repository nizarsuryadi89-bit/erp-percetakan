import { field } from '@/components/erp-page';
import type { ProductData } from '@/types/product';
import { pricingLabels } from '@/types/product';

export function ProductFields({
    data,
    setData,
}: {
    data: ProductData;
    setData: <K extends keyof ProductData>(
        key: K,
        value: ProductData[K],
    ) => void;
}) {
    return (
        <div className="grid gap-4 md:grid-cols-2">
            {(
                [
                    ['code', 'Kode produk'],
                    ['name', 'Nama produk'],
                    ['category', 'Kategori'],
                    ['unit_name', 'Satuan'],
                ] as const
            ).map(([key, label]) => (
                <label key={key} className="grid gap-2 text-sm">
                    {label}
                    <input
                        className={field}
                        required
                        maxLength={
                            key === 'code' || key === 'unit_name'
                                ? 40
                                : key === 'category'
                                  ? 100
                                  : 255
                        }
                        value={data[key]}
                        onChange={(event) => setData(key, event.target.value)}
                    />
                </label>
            ))}
            <label className="grid gap-2 text-sm">
                Dasar perhitungan harga
                <select
                    className={field}
                    value={data.pricing_basis}
                    onChange={(event) =>
                        setData(
                            'pricing_basis',
                            event.target.value as ProductData['pricing_basis'],
                        )
                    }
                >
                    {Object.entries(pricingLabels).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </label>
            <label className="grid gap-2 text-sm">
                Dasar rentang harga
                <select
                    className={field}
                    value={data.tier_basis}
                    onChange={(event) =>
                        setData(
                            'tier_basis',
                            event.target.value as ProductData['tier_basis'],
                        )
                    }
                >
                    <option value="quantity">Jumlah pesanan</option>
                    <option value="billable">
                        Jumlah setelah ukuran dihitung
                    </option>
                </select>
            </label>
            <label className="grid gap-2 text-sm">
                Satuan ukuran yang diinput
                <select
                    className={field}
                    value={data.dimension_unit}
                    onChange={(event) =>
                        setData(
                            'dimension_unit',
                            event.target.value as ProductData['dimension_unit'],
                        )
                    }
                >
                    <option value="cm">Sentimeter (cm)</option>
                    <option value="m">Meter (m)</option>
                </select>
            </label>
            <div className="grid gap-3 text-sm">
                <p>Ukuran minimum ({data.dimension_unit})</p>
                <div className="grid grid-cols-2 gap-3">
                    {(
                        [
                            ['minimum_length', 'Panjang minimum'],
                            ['minimum_width', 'Lebar minimum'],
                        ] as const
                    ).map(([key, label]) => (
                        <label key={key} className="grid gap-1 text-xs">
                            {label}
                            <input
                                className={field}
                                type="number"
                                required
                                min="0"
                                max="10000"
                                step="0.0001"
                                value={data[key]}
                                onChange={(event) =>
                                    setData(key, event.target.value)
                                }
                            />
                        </label>
                    ))}
                </div>
            </div>
            <p className="text-xs leading-5 text-muted-foreground md:col-span-2">
                Harga panjang dihitung per meter; harga luas per m². Ukuran cm
                otomatis dikonversi. Isi ukuran minimum 0 jika tidak digunakan.
                Dasar jumlah mengabaikan ukuran.
            </p>
            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(event) =>
                        setData('is_active', event.target.checked)
                    }
                />
                Produk aktif
            </label>
        </div>
    );
}
