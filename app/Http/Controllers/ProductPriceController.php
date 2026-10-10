<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductPriceRequest;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductPriceController extends Controller
{
    public function store(StoreProductPriceRequest $request, Product $product): RedirectResponse
    {
        $this->save($request, $product);

        return back()->with('success', 'Harga produk berhasil ditambahkan.');
    }

    public function update(StoreProductPriceRequest $request, Product $product, ProductPrice $price): RedirectResponse
    {
        $this->save($request, $product, $price);

        return back()->with('success', 'Harga produk berhasil diperbarui.');
    }

    private function save(StoreProductPriceRequest $request, Product $product, ?ProductPrice $price = null): void
    {
        abort_if($price && $price->product_id !== $product->id, 404);
        DB::transaction(function () use ($request, $product, $price): void {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $data = $request->validated();
            foreach (['variant', 'machine', 'print_side', 'finishing'] as $key) {
                $data[$key] = $data[$key] ?? '';
            }
            $data['qty_max'] = $data['qty_max'] ?? null;
            if ($data['is_active']) {
                $overlap = $product->prices()->where('is_active', true)->when($price, fn (Builder $query): Builder => $query->where('id', '!=', $price->id));
                foreach (['variant', 'machine', 'print_side', 'finishing'] as $key) {
                    $overlap->where($key, $data[$key]);
                }
                if ($data['qty_max'] !== null) {
                    $overlap->where('qty_min', '<=', $data['qty_max']);
                }
                $overlap->where(function (Builder $query) use ($data): void {
                    $query->whereNull('qty_max')->orWhere('qty_max', '>=', $data['qty_min']);
                });
                if ($overlap->exists()) {
                    throw ValidationException::withMessages(['qty_min' => 'Rentang harga aktif bertumpuk untuk spesifikasi yang sama. Periksa batas minimum dan maksimum.']);
                }
            }
            $before = $price?->only(array_keys($data));
            if ($price) {
                $price->update($data);
            } else {
                $price = $product->prices()->create($data);
            }
            AuditLog::create(['actor_id' => $request->user()->id, 'action' => 'harga_produk.disimpan', 'metadata' => ['product_id' => $product->id, 'price_id' => $price->id, 'before' => $before, 'after' => $data]]);
        });
    }
}
