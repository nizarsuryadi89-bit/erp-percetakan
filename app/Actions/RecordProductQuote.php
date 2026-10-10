<?php

namespace App\Actions;

use App\Models\Product;
use App\Models\ProductQuote;
use App\Models\User;
use App\Support\ModuleAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecordProductQuote
{
    public function __construct(private CalculateProductPrice $calculate, private ModuleAccess $access) {}

    /** @param array{qty: string|int, length?: string|int|null, width?: string|int|null, variant?: string|null, machine?: string|null, print_side?: string|null, finishing?: string|null} $input */
    public function handle(User $actor, Product $product, array $input): ProductQuote
    {
        abort_unless($this->access->allows($actor, 'POS'), 403);
        abort_unless(Schema::hasTable('product_quotes'), 503, 'Penyimpanan hasil harga sedang disiapkan. Hubungi pengelola.');

        return DB::transaction(function () use ($actor, $product, $input): ProductQuote {
            $product = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->calculate->handle($product, $input);
            $price = $product->prices()->whereKey($snapshot['price_id'])->firstOrFail();

            return ProductQuote::create([
                'product_id' => $product->id,
                'created_by' => $actor->id,
                'snapshot' => $snapshot,
                'cost_snapshot' => ['price_id' => $price->id, 'hpp' => $price->hpp],
            ]);
        });
    }
}
