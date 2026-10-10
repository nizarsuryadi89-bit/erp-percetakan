<?php

namespace App\Http\Controllers;

use App\Actions\RecordProductQuote;
use App\Http\Requests\QuoteProductRequest;
use App\Models\Product;
use App\Models\ProductQuote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class ProductQuoteController extends Controller
{
    public function store(QuoteProductRequest $request, Product $product, RecordProductQuote $record): RedirectResponse
    {
        $quote = $record->handle($request->user(), $product, $request->validated());

        return to_route('products.show', $product)->with('price_quote', $quote->publicSnapshot());
    }

    public function show(Product $product, string $quote): Response
    {
        abort_unless(Schema::hasTable('product_quotes'), 503, 'Penyimpanan hasil harga sedang disiapkan. Hubungi pengelola.');
        $saved = ProductQuote::where('product_id', $product->id)->where('uuid', $quote)->firstOrFail();

        return Inertia::render('products/quote', ['quote' => $saved->publicSnapshot()]);
    }
}
