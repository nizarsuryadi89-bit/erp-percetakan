import type { PriceQuote } from '@/types/product';
import { rupiah } from '@/types/product';

export function ProductQuoteSummary({ quote }: { quote: PriceQuote }) {
    return (
        <div className="grid gap-4">
            <p className="text-sm text-muted-foreground">Hasil perhitungan · {quote.detail_product}</p>
            <p className="text-3xl font-semibold">{rupiah(quote.subtotal)}</p>
            <dl className="grid grid-cols-2 gap-3 text-sm">
                <dt>Jumlah pesanan</dt><dd>{quote.qty}</dd>
                {quote.pricing_basis !== 'quantity' && <>
                    <dt>Ukuran pesanan</dt>
                    <dd>{quote.input_length}{quote.pricing_basis === 'area' ? ` × ${quote.input_width}` : ''} {quote.dimension_unit}</dd>
                    <dt>Ukuran ditagihkan</dt>
                    <dd>{quote.length}{quote.pricing_basis === 'area' ? ` × ${quote.width}` : ''} {quote.dimension_unit}</dd>
                </>}
                <dt>Jumlah ditagihkan</dt><dd>{quote.billable_qty} {quote.billing_unit}</dd>
                <dt>Harga satuan</dt><dd>{rupiah(quote.unit_price)} / {quote.billing_unit}</dd>
                <dt>Dasar rentang harga</dt><dd>{quote.tier_basis === 'quantity' ? 'Jumlah pesanan' : 'Jumlah ditagihkan'}: {quote.lookup_qty}</dd>
                <dt>Rentang terpilih</dt><dd>{quote.qty_min}–{quote.qty_max ?? 'tanpa batas'}</dd>
                <dt>Spesifikasi</dt><dd className="break-words">{Object.values(quote.specifications).filter(Boolean).join(' · ') || 'Reguler'}</dd>
            </dl>
            {quote.minimum_applied && <p className="text-sm">Harga minimum {rupiah(quote.minimum_charge)} diterapkan.</p>}
            <p className="text-xs leading-5 text-muted-foreground">Total dibulatkan ke rupiah terdekat. Hasil tersimpan mengikuti harga saat dihitung; hitung ulang untuk harga terkini. Simulasi ini belum menjadi transaksi atau SPK.</p>
        </div>
    );
}
