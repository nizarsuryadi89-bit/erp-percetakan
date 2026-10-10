<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Support\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CalculateProductPrice
{
    public function __construct(private TenantContext $context) {}

    /**
     * @param  array{qty: string|int, length?: string|int|null, width?: string|int|null, variant?: string|null, machine?: string|null, print_side?: string|null, finishing?: string|null}  $input
     * @return array{version: int, product_id: int, product_name: string, price_id: int, detail_product: string, unit_name: string, pricing_basis: string, dimension_unit: string, qty: string, length: string, width: string, billable_qty: string, pricing_qty: string, unit_price: string, minimum_charge: string, minimum_applied: bool, subtotal: string, specifications: array<string, string>, price_updated_at: string}
     */
    public function handle(Product $product, array $input): array
    {
        if ((int) $product->tenant_id !== $this->context->id()) {
            throw new AuthorizationException('Produk bukan milik perusahaan aktif.');
        }
        if (! $product->is_active) {
            throw ValidationException::withMessages(['product' => 'Produk ini sedang nonaktif.']);
        }
        $qty = BigDecimal::of((string) $input['qty']);
        $length = BigDecimal::of((string) ($input['length'] ?? '0'));
        $width = BigDecimal::of((string) ($input['width'] ?? '0'));
        if ($qty->isLessThanOrEqualTo(0) || $length->isLessThan(0) || $width->isLessThan(0)) {
            throw ValidationException::withMessages(['qty' => 'Jumlah harus positif dan ukuran tidak boleh negatif.']);
        }
        $billable = $qty;
        if (in_array($product->pricing_basis, ['length', 'area'], true)) {
            if ($length->isLessThanOrEqualTo(0)) {
                throw ValidationException::withMessages(['length' => 'Panjang wajib diisi untuk produk ini.']);
            }
            $length = $length->isLessThan($product->minimum_length) ? BigDecimal::of($product->minimum_length) : $length;
            $divisor = $product->dimension_unit === 'cm' ? 100 : 1;
            $billable = $qty->multipliedBy($length)->dividedByExact($divisor);
            if ($product->pricing_basis === 'area') {
                if ($width->isLessThanOrEqualTo(0)) {
                    throw ValidationException::withMessages(['width' => 'Lebar wajib diisi untuk produk luas.']);
                }
                $width = $width->isLessThan($product->minimum_width) ? BigDecimal::of($product->minimum_width) : $width;
                $billable = $billable->multipliedBy($width)->dividedByExact($divisor);
            }
        }
        $pricingQty = $product->tier_basis === 'billable' ? $billable : $qty;
        $lookupQty = $pricingQty->isLessThan(1) ? BigDecimal::of(1) : $pricingQty;
        $specifications = [];
        $query = $product->prices()->where('is_active', true);
        foreach (['variant', 'machine', 'print_side', 'finishing'] as $key) {
            $specifications[$key] = $input[$key] ?? '';
            $query->where($key, $specifications[$key]);
        }
        $query->where('qty_min', '<=', (string) $lookupQty)->where(function (Builder $query) use ($lookupQty): void {
            $query->whereNull('qty_max')->orWhere('qty_max', '>=', (string) $lookupQty);
        });
        /** @var ProductPrice|null $price */
        $price = $query->orderByDesc('qty_min')->orderBy('id')->first();
        if (! $price) {
            throw ValidationException::withMessages(['price' => 'Harga belum dikonfigurasi untuk spesifikasi dan jumlah ini.']);
        }
        $subtotal = $billable->multipliedBy($price->unit_price);
        $minimumApplied = $subtotal->isLessThan($price->minimum_charge);
        if ($minimumApplied) {
            $subtotal = BigDecimal::of($price->minimum_charge);
        }
        $subtotal = $subtotal->toScale(0, RoundingMode::HalfUp)->toScale(2);
        if ($subtotal->isGreaterThan('9999999999999.99')) {
            throw ValidationException::withMessages(['qty' => 'Total melampaui batas nominal. Kurangi jumlah atau ukuran.']);
        }

        return [
            'version' => 1, 'product_id' => $product->id, 'product_name' => $product->name,
            'price_id' => $price->id, 'detail_product' => $price->detail_product, 'unit_name' => $product->unit_name,
            'pricing_basis' => $product->pricing_basis, 'dimension_unit' => $product->dimension_unit,
            'qty' => (string) $qty, 'length' => (string) $length, 'width' => (string) $width,
            'billable_qty' => (string) $billable, 'pricing_qty' => (string) $pricingQty,
            'unit_price' => $price->unit_price, 'minimum_charge' => $price->minimum_charge,
            'minimum_applied' => $minimumApplied, 'subtotal' => (string) $subtotal,
            'specifications' => $specifications, 'price_updated_at' => $price->updated_at->toISOString(),
        ];
    }
}
