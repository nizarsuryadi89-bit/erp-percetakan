<?php

use App\Actions\CreateCompany;
use App\Actions\RecordProductQuote;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductQuote;
use App\Models\Tenant;
use App\Models\TenantModuleEntitlement;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function withinPricingTenant(Tenant $tenant, Closure $callback): mixed
{
    $context = app(TenantContext::class);
    $permissions = app(PermissionRegistrar::class);
    $previous = $context->tenant();
    $team = $permissions->getPermissionsTeamId();
    $context->set($tenant);
    $permissions->setPermissionsTeamId($tenant->id);
    try {
        return $callback();
    } finally {
        $context->set($previous);
        $permissions->setPermissionsTeamId($team);
    }
}

/** @return array{0: User, 1: Tenant, 2: Product, 3: ProductPrice} */
function pricingFixture(array $productSettings = [], array $priceSettings = []): array
{
    $owner = User::factory()->create();
    $tenant = app(CreateCompany::class)->handle($owner, ['name' => 'Percetakan Pricing']);
    $tenant->refresh();
    [$product, $price] = withinPricingTenant($tenant, function () use ($productSettings, $priceSettings): array {
        License::firstOrFail()->update(['status' => 'active', 'activated_at' => now()]);
        TenantModuleEntitlement::whereHas('module', fn ($query) => $query->where('code', 'POS'))->firstOrFail()->update(['is_granted' => true]);
        $product = Product::factory()->create($productSettings);

        return [$product, ProductPrice::factory()->for($product)->create($priceSettings)];
    });

    return [$owner, $tenant, $product, $price];
}

function storedPricingQuote(Tenant $tenant): ProductQuote
{
    return withinPricingTenant($tenant, fn () => ProductQuote::orderByDesc('id')->firstOrFail());
}

/** Confirmed by the owner on 10 October 2026; these are samples, not inferred price tiers. */
test('confirmed TGM product samples produce the approved totals with POS alone', function (array $productSettings, array $priceSettings, array $input, string $total): void {
    [$owner, $tenant, $product] = pricingFixture($productSettings, $priceSettings);
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), $input)
        ->assertRedirect(route('products.show', $product))->assertSessionHas('price_quote.subtotal', $total);
    $quote = storedPricingQuote($tenant);
    expect($quote->snapshot['subtotal'])->toBe($total);
    expect($quote->snapshot['specifications']['variant'])->toBe($input['variant']);
    $this->assertDatabaseCount('product_quotes', 1);
    $this->get(route('product-quotes.show', ['product' => $product, 'quote' => $quote->uuid]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('products/quote')->where('quote.subtotal', $total)->missing('quote.hpp')->missing('quote.cost_snapshot'));
})->with([
    'HVS 100 gr two sides 20 sheets at 4100' => [
        ['name' => 'HVS 100 gr', 'unit_name' => 'Lembar'],
        ['variant' => 'HVS 100 gr', 'print_side' => '2 sisi', 'qty_min' => '20', 'qty_max' => '20', 'unit_price' => '4100', 'hpp' => '0'],
        ['qty' => '20', 'variant' => 'HVS 100 gr', 'print_side' => '2 sisi'],
        '82000.00',
    ],
    'lanyard 2 cm with stopper 5 pieces at 12000' => [
        ['name' => 'Lanyard', 'unit_name' => 'Pcs'],
        ['variant' => '2 cm', 'finishing' => 'Stopper', 'qty_min' => '5', 'qty_max' => '5', 'unit_price' => '12000', 'hpp' => '0'],
        ['qty' => '5', 'variant' => '2 cm', 'finishing' => 'Stopper'],
        '60000.00',
    ],
]);

test('original dimensions and billing units remain distinct from charged minimum dimensions', function (string $basis, array $input, array $expected): void {
    [$owner, $tenant, $product] = pricingFixture(['pricing_basis' => $basis, 'unit_name' => 'cm', 'minimum_length' => '100', 'minimum_width' => '50']);
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), $input)->assertSessionHasNoErrors();
    $snapshot = storedPricingQuote($tenant)->snapshot;
    foreach ($expected as $key => $value) {
        expect($snapshot[$key])->toBe($value);
    }
})->with([
    'area in centimeters' => ['area', ['qty' => '2', 'length' => '25', 'width' => '20'], ['input_length' => '25', 'input_width' => '20', 'length' => '100.0000', 'width' => '50.0000', 'billing_unit' => 'm²', 'subtotal' => '10000.00']],
    'length in centimeters' => ['length', ['qty' => '2', 'length' => '25'], ['input_length' => '25', 'length' => '100.0000', 'billing_unit' => 'm', 'subtotal' => '20000.00']],
]);

test('saved quotes preserve names prices specifications and internal cost after master changes', function (): void {
    [$owner, $tenant, $product, $price] = pricingFixture(['name' => 'Produk lama'], ['unit_price' => '4100', 'hpp' => '2000']);
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '20'])->assertSessionHasNoErrors();
    $saved = storedPricingQuote($tenant);
    withinPricingTenant($tenant, function () use ($product, $price): void {
        $product->update(['name' => 'Produk diperbarui', 'unit_name' => 'Pcs']);
        $price->update(['unit_price' => '5000', 'hpp' => '3000', 'finishing' => 'Baru']);
    });
    $this->flushSession();
    $this->withSession(['tenant_id' => $tenant->id])->get(route('product-quotes.show', ['product' => $product, 'quote' => $saved->uuid]))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('quote.product_name', 'Produk lama')->where('quote.unit_name', 'Lembar')->where('quote.unit_price', '4100.00')->where('quote.subtotal', '82000.00')->where('quote.specifications.finishing', '')->missing('quote.cost_snapshot'));
    expect(storedPricingQuote($tenant)->cost_snapshot['hpp'])->toBe('2000.00');
    expect(storedPricingQuote($tenant)->snapshot)->toBe($saved->snapshot);
});

test('public revision reflects selling rules but never fingerprints confidential HPP', function (): void {
    $this->freezeTime();
    [$owner, $tenant, $product, $price] = pricingFixture();
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('product-quotes.store', $product), ['qty' => '1'])->assertSessionHasNoErrors();
    $first = storedPricingQuote($tenant);
    withinPricingTenant($tenant, fn () => $price->update(['hpp' => '1234']));
    $this->post(route('product-quotes.store', $product), ['qty' => '2'])->assertSessionHasNoErrors();
    $second = storedPricingQuote($tenant);
    expect($second->snapshot['price_revision'])->toBe($first->snapshot['price_revision']);
    expect($second->uuid)->not->toBe($first->uuid);
    withinPricingTenant($tenant, fn () => $price->update(['unit_price' => '12000']));
    $this->post(route('product-quotes.store', $product), ['qty' => '1'])->assertSessionHasNoErrors();
    expect(storedPricingQuote($tenant)->snapshot['price_revision'])->not->toBe($first->snapshot['price_revision']);
    withinPricingTenant($tenant, fn () => $product->update(['minimum_length' => '10']));
    $third = storedPricingQuote($tenant);
    $this->post(route('product-quotes.store', $product), ['qty' => '1'])->assertSessionHasNoErrors();
    expect(storedPricingQuote($tenant)->snapshot['price_revision'])->not->toBe($third->snapshot['price_revision']);
});

test('ambiguous active legacy price ranges produce a validation error and no snapshot', function (): void {
    [$owner, $tenant, $product] = pricingFixture();
    withinPricingTenant($tenant, fn () => ProductPrice::factory()->for($product)->create(['qty_min' => '10', 'unit_price' => '1']));
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '20'])
        ->assertSessionHasErrors(['price' => 'Lebih dari satu harga aktif cocok. Perbaiki rentang harga sebelum menghitung.']);
    $this->assertDatabaseCount('product_quotes', 0);
});

test('clients cannot forge snapshot totals cost ids or original dimensions', function (): void {
    [$owner, $tenant, $product] = pricingFixture();
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '2', 'subtotal' => '1', 'unit_price' => '0', 'hpp' => '0', 'price_id' => 999, 'tenant_id' => 999, 'input_length' => '999', 'cost_snapshot' => ['hpp' => '0']])->assertSessionHasNoErrors();
    $quote = storedPricingQuote($tenant);
    expect($quote->tenant_id)->toBe($tenant->id);
    expect($quote->snapshot['subtotal'])->toBe('20000.00');
    expect($quote->snapshot['input_length'])->toBe('0');
    expect($quote->cost_snapshot['hpp'])->toBe('5000.00');
    expect($quote->toArray())->not->toHaveKey('cost_snapshot');
});

test('saved quotes cannot be changed through model updates', function (): void {
    [$owner, $tenant] = pricingFixture();
    withinPricingTenant($tenant, function (): void {
        $quote = ProductQuote::factory()->create();
        expect(fn () => $quote->update(['snapshot' => ['subtotal' => '1']]))->toThrow(LogicException::class);
    });
});

test('cross tenant and mismatched product snapshot URLs return 404', function (): void {
    [$owner, $tenant, $product] = pricingFixture();
    [$otherOwner, $otherTenant, $otherProduct] = pricingFixture();
    $quote = withinPricingTenant($tenant, fn () => app(RecordProductQuote::class)->handle($owner, $product, ['qty' => '1']));
    $this->actingAs($otherOwner)->withSession(['tenant_id' => $otherTenant->id]);
    $this->get(route('product-quotes.show', ['product' => $product, 'quote' => $quote->uuid]))->assertNotFound();
    $this->get(route('product-quotes.show', ['product' => $otherProduct, 'quote' => $quote->uuid]))->assertNotFound();
    $this->get(route('products.show', $otherProduct))->assertInertia(fn (Assert $page) => $page->has('savedQuotes', 0));
});

test('saved quote access rechecks membership role entitlement and license', function (string $restriction): void {
    [$owner, $tenant, $product] = pricingFixture();
    $quote = withinPricingTenant($tenant, fn () => app(RecordProductQuote::class)->handle($owner, $product, ['qty' => '1']));
    withinPricingTenant($tenant, function () use ($restriction, $owner, $tenant): void {
        match ($restriction) {
            'role' => $owner->syncRoles(['Operator Produksi']),
            'grant' => TenantModuleEntitlement::query()->update(['is_granted' => false]),
            'license' => License::firstOrFail()->update(['status' => 'suspended']),
            'membership' => $tenant->users()->updateExistingPivot($owner, ['is_active' => false]),
        };
    });
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('product-quotes.show', ['product' => $product, 'quote' => $quote->uuid]))->assertForbidden();
    $this->assertDatabaseCount('product_quotes', 1);
})->with(['role', 'grant', 'license', 'membership']);

test('quote guests must log in and unknown quote ids are not exposed', function (): void {
    [$owner, $tenant, $product] = pricingFixture();
    $url = route('product-quotes.show', ['product' => $product, 'quote' => '12345678-1234-4234-8234-123456789012']);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get($url)->assertNotFound();
});

test('quotes cannot reference a product from a different tenant', function (): void {
    [$owner, $tenant] = pricingFixture();
    [$otherOwner, $otherTenant, $product] = pricingFixture();
    withinPricingTenant($tenant, fn () => expect(fn () => ProductQuote::create(['product_id' => $product->id, 'snapshot' => [], 'cost_snapshot' => []]))->toThrow(QueryException::class));
});

test('pending quote migration keeps the catalog readable and fails writes clearly', function (): void {
    [$owner, $tenant, $product] = pricingFixture();
    Schema::drop('product_quotes');
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('products.show', $product))->assertOk()->assertInertia(fn (Assert $page) => $page->has('savedQuotes', 0));
    $this->post(route('product-quotes.store', $product), ['qty' => '1'])->assertStatus(503);
    $this->get(route('product-quotes.show', ['product' => $product, 'quote' => '12345678-1234-4234-8234-123456789012']))->assertStatus(503);
});

test('latest quote history is bounded and ordered without internal costs', function (): void {
    [$owner, $tenant, $product] = pricingFixture();
    withinPricingTenant($tenant, function () use ($owner, $product): void {
        foreach (range(1, 12) as $quantity) {
            app(RecordProductQuote::class)->handle($owner, $product, ['qty' => (string) $quantity]);
        }
    });
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('products.show', $product))->assertInertia(fn (Assert $page) => $page->has('savedQuotes', 10)->where('savedQuotes.0.qty', '12')->where('savedQuotes.9.qty', '3')->missing('savedQuotes.0.cost_snapshot'));
});

test('legacy session quotes are discarded instead of rendering an invalid saved quote link', function (): void {
    [$owner, $tenant, $product] = pricingFixture();
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id, 'price_quote' => ['version' => 1, 'product_id' => $product->id, 'subtotal' => '10000.00']])->get(route('products.show', $product))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('quote', null));
});

test('deactivating a product preserves saved results but prevents new quotes', function (): void {
    [$owner, $tenant, $product] = pricingFixture();
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '1'])->assertSessionHasNoErrors();
    $quote = storedPricingQuote($tenant);
    withinPricingTenant($tenant, fn () => $product->update(['is_active' => false]));
    $this->get(route('product-quotes.show', ['product' => $product, 'quote' => $quote->uuid]))->assertOk()->assertInertia(fn (Assert $page) => $page->where('quote.subtotal', '10000.00'));
    $this->post(route('product-quotes.store', $product), ['qty' => '1'])->assertSessionHasErrors('product');
    $this->assertDatabaseCount('product_quotes', 1);
});

test('the snapshot migration preserves existing catalog data on upgrade', function (): void {
    [$owner, $tenant, $product, $price] = pricingFixture();
    Schema::drop('product_quotes');
    $migration = require database_path('migrations/2026_10_10_070531_create_product_quotes_table.php');
    $migration->up();
    $this->assertDatabaseHas('products', ['id' => $product->id, 'tenant_id' => $tenant->id, 'code' => $product->code]);
    $this->assertDatabaseHas('product_prices', ['id' => $price->id, 'unit_price' => '10000.00', 'hpp' => '5000.00']);
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '1'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('product_quotes', 1);
});
