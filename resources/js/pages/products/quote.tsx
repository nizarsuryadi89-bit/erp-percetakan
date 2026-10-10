import { Head, Link } from '@inertiajs/react';
import { ErpPage, panel } from '@/components/erp-page';
import { ProductQuoteSummary } from '@/components/product-quote-summary';
import { show } from '@/routes/products';
import type { PriceQuote } from '@/types/product';

export default function SavedProductQuote({ quote }: { quote: PriceQuote }) {
    return <>
        <Head title={`Hasil harga — ${quote.product_name}`} />
        <ErpPage title={quote.product_name} description={`Perhitungan tersimpan · ${new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(quote.quoted_at))}`}>
            <Link className="text-sm underline underline-offset-4" href={show.url(quote.product_id)}>Kembali ke produk dan hitung ulang</Link>
            <section className={`${panel} max-w-2xl`}><ProductQuoteSummary quote={quote} /></section>
        </ErpPage>
    </>;
}
