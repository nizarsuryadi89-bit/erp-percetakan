<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\AuditLog;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $filters['search'] ?? '';

        return Inertia::render('products/index', [
            'products' => Product::query()->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('name', 'like', '%'.$search.'%')->orWhere('code', 'like', '%'.$search.'%');
                });
            })->withCount('prices')->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
            'search' => $search, 'canManage' => $request->user()->can('produk.manage'),
        ]);
    }

    public function show(Request $request, Product $product): Response
    {
        $canManage = $request->user()->can('produk.manage');
        $prices = $product->prices()->orderBy('variant')->orderBy('qty_min')->orderBy('id')->get();
        if ($canManage) {
            $prices->each->makeVisible('hpp');
        }

        return Inertia::render('products/show', ['product' => $product, 'prices' => $prices, 'canManage' => $canManage, 'quote' => ($request->session()->get('price_quote.product_id') === $product->id ? $request->session()->get('price_quote') : null)]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $product = DB::transaction(function () use ($request): Product {
            $product = Product::create($request->validated());
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'produk.dibuat', 'metadata' => ['product_id' => $product->id]]);

            return $product;
        });

        return to_route('products.show', $product)->with('success', 'Produk berhasil ditambahkan. Lengkapi matriks harganya.');
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        DB::transaction(function () use ($request, $product): void {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = $product->only(array_keys($request->validated()));
            $product->update($request->validated());
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'produk.diperbarui', 'metadata' => ['product_id' => $product->id, 'before' => $before, 'after' => $request->validated()]]);
        });

        return back()->with('success', 'Produk berhasil diperbarui.');
    }
}
