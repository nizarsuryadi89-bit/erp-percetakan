import type { InvoiceLine } from '@/types/cashier';
import { rupiah } from '@/types/product';
export function InvoiceLines({ items }: { items: InvoiceLine[] }) {
    return <div className="grid gap-4">{items.map((item, index) => {
        const quote = item.snapshot;
        return <section key={item.source_item_id} className="break-inside-avoid rounded-lg border p-4 text-sm print:rounded-none print:border-gray-400 print:p-2">
            <h3 className="font-semibold">{index + 1}. {quote.product_name}</h3>
            <p className="mt-1 break-words">{Object.values(quote.specifications).filter(Boolean).join(' · ') || 'Reguler'}</p>
            <p>Pesanan: {quote.qty} {quote.unit_name}</p>
            {quote.pricing_basis !== 'quantity' && <><p>Ukuran pesanan: {quote.input_length}{quote.pricing_basis === 'area' ? ` × ${quote.input_width}` : ''} {quote.dimension_unit}</p><p>Ukuran tagihan: {quote.length}{quote.pricing_basis === 'area' ? ` × ${quote.width}` : ''} {quote.dimension_unit}</p></>}
            <p>{quote.billable_qty} {quote.billing_unit} × {rupiah(quote.unit_price)}</p>
            {quote.minimum_applied && <p>Harga minimum {rupiah(quote.minimum_charge)} diterapkan.</p>}
            <p className="mt-2 font-semibold">Subtotal {rupiah(quote.subtotal)}</p>
            {item.notes && <p className="mt-2 whitespace-pre-wrap break-words">{item.notes}</p>}
        </section>;
    })}</div>;
}
