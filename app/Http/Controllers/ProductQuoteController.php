<?php

namespace App\Http\Controllers;

use App\Actions\CalculateProductPrice;
use App\Http\Requests\QuoteProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;

class ProductQuoteController extends Controller
{
    public function store(QuoteProductRequest $request, Product $product, CalculateProductPrice $calculate): RedirectResponse
    {
        $quote = $calculate->handle($product, $request->validated());

        return to_route('products.show', $product)->with('price_quote', $quote);
    }
}
