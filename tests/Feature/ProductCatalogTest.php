<?php

use App\Actions\CreateCompany;
use App\Models\AuditLog;
use App\Models\License;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Models\TenantModuleEntitlement;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function inCatalogCompany(Tenant $tenant, Closure $action): mixed
{
    $context = app(TenantContext::class);
    $permissions = app(PermissionRegistrar::class);
    $previous = $context->tenant();
    $previousTeam = $permissions->getPermissionsTeamId();
    $context->set($tenant);
    $permissions->setPermissionsTeamId($tenant->id);
    try {
        return $action();
    } finally {
        $context->set($previous);
        $permissions->setPermissionsTeamId($previousTeam);
    }
}

/** @return array{0: User, 1: Tenant} */
function catalogCompany(): array
{
    $owner = User::factory()->create();
    $tenant = app(CreateCompany::class)->handle($owner, ['name' => 'Katalog Uji']);
    inCatalogCompany($tenant, function (): void {
        License::firstOrFail()->update(['status' => 'active', 'activated_at' => now()]);
        TenantModuleEntitlement::whereHas('module', fn ($query) => $query->where('code', 'POS'))->firstOrFail()->update(['is_granted' => true]);
    });

    return [$owner, $tenant];
}

/** @return array<string, mixed> */
function catalogProductInput(): array
{
    return ['code' => 'BANNER', 'name' => 'Banner Outdoor', 'category' => 'Large Format', 'unit_name' => 'm²', 'pricing_basis' => 'area', 'tier_basis' => 'billable', 'dimension_unit' => 'cm', 'minimum_length' => '0', 'minimum_width' => '0', 'is_active' => true];
}

/** @return array<string, mixed> */
function catalogPriceInput(): array
{
    return ['detail_product' => 'Reguler', 'variant' => '', 'machine' => '', 'print_side' => '', 'finishing' => '', 'qty_min' => '1', 'qty_max' => '', 'unit_price' => '25000', 'hpp' => '12000', 'minimum_charge' => '0', 'is_active' => true];
}

test('guests must log in before opening the product catalog', function (): void {
    $this->get(route('products.index'))->assertRedirect(route('login'));
});

test('owner creates a product in the active company and records the action', function (): void {
    [$owner, $tenant] = catalogCompany();

    $response = $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('products.store'), [...catalogProductInput(), 'tenant_id' => 999]);

    $product = inCatalogCompany($tenant, fn () => Product::firstOrFail());
    $response->assertRedirect(route('products.show', $product));
    $this->assertDatabaseHas('products', ['id' => $product->id, 'tenant_id' => $tenant->id, 'code' => 'BANNER']);
    $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'produk.dibuat', 'actor_id' => $owner->id]);
});

test('owner updates product settings without deleting its price matrix', function (): void {
    [$owner, $tenant] = catalogCompany();
    [$product, $price] = inCatalogCompany($tenant, function (): array {
        $product = Product::factory()->create(['code' => 'BANNER']);

        return [$product, ProductPrice::factory()->for($product)->create()];
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->patch(route('products.update', $product), [...catalogProductInput(), 'is_active' => false])->assertRedirect();

    $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false, 'pricing_basis' => 'area']);
    $this->assertModelExists($price);
    $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'produk.diperbarui']);
});

test('duplicate product codes are rejected within one company', function (): void {
    [$owner, $tenant] = catalogCompany();
    inCatalogCompany($tenant, fn () => Product::factory()->create(['code' => 'BANNER']));

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('products.store'), catalogProductInput())->assertSessionHasErrors(['code' => 'Kode produk sudah digunakan di perusahaan ini.']);

    $this->assertDatabaseCount('products', 1);
});

test('product validation rejects unsupported pricing and malformed decimal input', function (string $key, mixed $value): void {
    [$owner, $tenant] = catalogCompany();

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('products.store'), [...catalogProductInput(), $key => $value])->assertSessionHasErrors($key);

    $this->assertDatabaseCount('products', 0);
})->with(['unknown basis' => ['pricing_basis', 'volume'], 'unknown tier' => ['tier_basis', 'customer'], 'unknown unit' => ['dimension_unit', 'mm'], 'negative minimum' => ['minimum_width', '-1'], 'exponent' => ['minimum_length', '1e2'], 'excess decimal' => ['minimum_length', '0.00001'], 'too large' => ['minimum_length', '10001']]);

test('product management is granted only to owner and administrator', function (string $role, bool $allowed): void {
    [$owner, $tenant] = catalogCompany();
    $user = User::factory()->create();
    $tenant->users()->attach($user, ['is_active' => true]);
    inCatalogCompany($tenant, function () use ($user, $role): void {
        $user->assignRole($role);
        expect($user->can('produk.manage'))->toBe(in_array($role, ['Owner', 'Administrator'], true));
    });

    $response = $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->post(route('products.store'), catalogProductInput());

    if ($allowed) {
        $response->assertRedirect();
        $this->assertDatabaseCount('products', 1);
    } else {
        $response->assertForbidden();
        $this->assertDatabaseCount('products', 0);
    }
})->with(['owner' => ['Owner', true], 'administrator' => ['Administrator', true], 'cashier' => ['Kasir', false], 'production' => ['Operator Produksi', false], 'finance' => ['Keuangan', false], 'read only' => ['Read Only', false]]);

test('catalog list and search never show another company products', function (): void {
    [$owner, $tenant] = catalogCompany();
    [, $foreign] = catalogCompany();
    $product = inCatalogCompany($tenant, fn () => Product::factory()->create(['name' => 'Kartu Nama']));
    inCatalogCompany($foreign, fn () => Product::factory()->create(['name' => 'Kartu Nama Rahasia']));

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('products.index', ['search' => 'Kartu']))->assertOk()->assertInertia(fn (Assert $page) => $page->component('products/index')->has('products.data', 1)->where('products.data.0.id', $product->id));
});

test('foreign company product detail update and calculation return 404', function (): void {
    [$owner, $tenant] = catalogCompany();
    [, $foreign] = catalogCompany();
    $product = inCatalogCompany($foreign, fn () => Product::factory()->create());
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);

    $this->get(route('products.show', $product))->assertNotFound();
    $this->patch(route('products.update', $product), catalogProductInput())->assertNotFound();
    $this->post(route('product-quotes.store', $product), ['qty' => '1'])->assertNotFound();
});

test('owner saves a price with server controlled product and tenant ids', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, fn () => Product::factory()->create());

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-prices.store', $product), [...catalogPriceInput(), 'product_id' => 999, 'tenant_id' => 999])->assertRedirect();

    $this->assertDatabaseHas('product_prices', ['tenant_id' => $tenant->id, 'product_id' => $product->id, 'unit_price' => 25000, 'qty_max' => null]);
    $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'harga_produk.disimpan']);
});

test('cashiers and read only users cannot change price rows', function (string $role): void {
    [$owner, $tenant] = catalogCompany();
    [$product, $price] = inCatalogCompany($tenant, function (): array {
        $product = Product::factory()->create();

        return [$product, ProductPrice::factory()->for($product)->create()];
    });
    $user = User::factory()->create();
    $tenant->users()->attach($user, ['is_active' => true]);
    inCatalogCompany($tenant, fn () => $user->assignRole($role));

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->patch(route('product-prices.update', [$product, $price]), catalogPriceInput())->assertForbidden();

    $this->assertDatabaseHas('product_prices', ['id' => $price->id, 'unit_price' => 10000]);
})->with(['Kasir', 'Read Only']);

test('cashier product props and price quote never expose HPP', function (): void {
    [$owner, $tenant] = catalogCompany();
    [$product, $price] = inCatalogCompany($tenant, function (): array {
        $product = Product::factory()->create();

        return [$product, ProductPrice::factory()->for($product)->create()];
    });
    $cashier = User::factory()->create();
    $tenant->users()->attach($cashier, ['is_active' => true]);
    inCatalogCompany($tenant, fn () => $cashier->assignRole('Kasir'));
    $this->actingAs($cashier)->withSession(['tenant_id' => $tenant->id]);

    $this->get(route('products.show', $product))->assertOk()->assertInertia(fn (Assert $page) => $page->missing('prices.0.hpp')->where('canManage', false));
    $this->post(route('product-quotes.store', $product), ['qty' => '2'])->assertSessionHas('price_quote.subtotal', '20000.00')->assertSessionMissing('price_quote.hpp');
});

test('owner product props include HPP for price configuration', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function (): Product {
        $product = Product::factory()->create();
        ProductPrice::factory()->for($product)->create(['hpp' => '12345.67']);

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('products.show', $product))->assertOk()->assertInertia(fn (Assert $page) => $page->where('prices.0.hpp', '12345.67')->where('canManage', true));
});

test('active price ranges with identical specifications cannot overlap', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function (): Product {
        $product = Product::factory()->create();
        ProductPrice::factory()->for($product)->create(['qty_min' => '1', 'qty_max' => '100']);

        return $product;
    });
    $auditCount = AuditLog::withoutGlobalScopes()->count();

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-prices.store', $product), [...catalogPriceInput(), 'qty_min' => '100'])->assertSessionHasErrors(['qty_min' => 'Rentang harga aktif bertumpuk untuk spesifikasi yang sama. Periksa batas minimum dan maksimum.']);

    $this->assertDatabaseCount('product_prices', 1);
    $this->assertDatabaseCount('audit_logs', $auditCount);
});

test('different specifications allow identical price ranges', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function (): Product {
        $product = Product::factory()->create();
        ProductPrice::factory()->for($product)->create(['print_side' => '1 sisi']);

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-prices.store', $product), [...catalogPriceInput(), 'print_side' => '2 sisi'])->assertSessionHasNoErrors();

    $this->assertDatabaseCount('product_prices', 2);
});

test('price updates exclude their own range and can disable a row', function (): void {
    [$owner, $tenant] = catalogCompany();
    [$product, $price] = inCatalogCompany($tenant, function (): array {
        $product = Product::factory()->create();

        return [$product, ProductPrice::factory()->for($product)->create()];
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->patch(route('product-prices.update', [$product, $price]), [...catalogPriceInput(), 'is_active' => false])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('product_prices', ['id' => $price->id, 'is_active' => false, 'unit_price' => 25000]);
    $this->assertDatabaseCount('product_prices', 1);
});

test('a price row cannot be updated through another product or tenant', function (): void {
    [$owner, $tenant] = catalogCompany();
    [$product, $other, $price] = inCatalogCompany($tenant, function (): array {
        $product = Product::factory()->create();
        $other = Product::factory()->create();

        return [$product, $other, ProductPrice::factory()->for($other)->create()];
    });
    [, $foreign] = catalogCompany();
    $foreignPrice = inCatalogCompany($foreign, fn () => ProductPrice::factory()->create());
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);

    $this->patch(route('product-prices.update', [$product, $price]), catalogPriceInput())->assertNotFound();
    $this->patch(route('product-prices.update', [$product, $foreignPrice]), catalogPriceInput())->assertNotFound();

    $this->assertDatabaseHas('product_prices', ['id' => $price->id, 'product_id' => $other->id, 'unit_price' => 10000]);
});

test('database prevents a price row from referencing another company product', function (): void {
    [$owner, $tenant] = catalogCompany();
    [, $foreign] = catalogCompany();
    $product = inCatalogCompany($foreign, fn () => Product::factory()->create());

    expect(fn () => DB::table('product_prices')->insert(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'detail_product' => 'Foreign', 'unit_price' => 10000]))->toThrow(QueryException::class);
});

test('price validation rejects invalid amounts and ranges', function (string $key, string $value): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, fn () => Product::factory()->create());

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-prices.store', $product), [...catalogPriceInput(), $key => $value])->assertSessionHasErrors($key);

    $this->assertDatabaseCount('product_prices', 0);
})->with(['negative price' => ['unit_price', '-100'], 'negative hpp' => ['hpp', '-10'], 'too many decimals' => ['unit_price', '10.001'], 'currency formatting' => ['unit_price', '25.000,00'], 'exponent price' => ['unit_price', '1e3'], 'reversed range' => ['qty_max', '0.5'], 'excess qty precision' => ['qty_min', '0.00001']]);

test('price simulation calculates quantity length and area using exact decimals', function (array $settings, array $input, string $subtotal, string $billable): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function () use ($settings): Product {
        $product = Product::factory()->create($settings);
        ProductPrice::factory()->for($product)->create(['unit_price' => '25000']);

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), $input)->assertRedirect(route('products.show', $product))->assertSessionHas('price_quote.subtotal', $subtotal)->assertSessionHas('price_quote.billable_qty', $billable);

    $this->assertDatabaseCount('product_prices', 1);
})->with([
    'quantity ignores dimensions' => [[], ['qty' => '2', 'length' => '100', 'width' => '100'], '50000.00', '2'],
    'centimeters to square meters' => [['pricing_basis' => 'area', 'tier_basis' => 'billable'], ['qty' => '3', 'length' => '150', 'width' => '200'], '225000.00', '9'],
    'meters to square meters' => [['pricing_basis' => 'area', 'dimension_unit' => 'm'], ['qty' => '2', 'length' => '1.5', 'width' => '2'], '150000.00', '6'],
    'length centimeters' => [['pricing_basis' => 'length'], ['qty' => '2', 'length' => '150'], '75000.00', '3'],
    'length meters' => [['pricing_basis' => 'length', 'dimension_unit' => 'm'], ['qty' => '0.5', 'length' => '1.2'], '15000.00', '0.6'],
    'fractional area keeps actual quantity' => [['pricing_basis' => 'area', 'tier_basis' => 'billable'], ['qty' => '1', 'length' => '50', 'width' => '50'], '6250.00', '0.25'],
    'minimum dimensions are applied' => [['pricing_basis' => 'area', 'minimum_length' => '5', 'minimum_width' => '5'], ['qty' => '1', 'length' => '2', 'width' => '3'], '63.00', '0.0025'],
]);

test('price simulation applies minimum charge after dimensional calculation', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function (): Product {
        $product = Product::factory()->area()->create();
        ProductPrice::factory()->for($product)->create(['unit_price' => '25000', 'minimum_charge' => '10000']);

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '1', 'length' => '50', 'width' => '50'])->assertSessionHas('price_quote.subtotal', '10000.00')->assertSessionHas('price_quote.minimum_applied', true);
});

test('tier boundaries use either the order quantity or billable quantity', function (string $tierBasis, array $input, string $subtotal): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function () use ($tierBasis): Product {
        $product = Product::factory()->area()->create(['tier_basis' => $tierBasis]);
        ProductPrice::factory()->for($product)->create(['qty_min' => '1', 'qty_max' => '9.9999', 'unit_price' => '25000']);
        ProductPrice::factory()->for($product)->create(['qty_min' => '10', 'qty_max' => null, 'unit_price' => '20000']);

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), $input)->assertSessionHas('price_quote.subtotal', $subtotal);
})->with(['area reaches ten' => ['billable', ['qty' => '2', 'length' => '250', 'width' => '200'], '200000.00'], 'pieces remain two' => ['quantity', ['qty' => '2', 'length' => '250', 'width' => '200'], '250000.00'], 'exact quantity ten' => ['quantity', ['qty' => '10', 'length' => '100', 'width' => '100'], '200000.00']]);

test('variant machine side and finishing select the exact price row', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function (): Product {
        $product = Product::factory()->create();
        ProductPrice::factory()->for($product)->create(['variant' => 'Art Carton', 'machine' => 'Digital', 'print_side' => '1 sisi', 'finishing' => 'Laminasi', 'unit_price' => '1000']);
        ProductPrice::factory()->for($product)->create(['variant' => 'Art Carton', 'machine' => 'Digital', 'print_side' => '2 sisi', 'finishing' => 'Laminasi', 'unit_price' => '1800']);

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '100', 'variant' => 'Art Carton', 'machine' => 'Digital', 'print_side' => '2 sisi', 'finishing' => 'Laminasi'])->assertSessionHas('price_quote.subtotal', '180000.00');
});

test('unknown specifications do not silently use a different price', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function (): Product {
        $product = Product::factory()->create();
        ProductPrice::factory()->for($product)->create(['print_side' => '1 sisi']);

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '1', 'print_side' => '2 sisi'])->assertSessionHasErrors(['price' => 'Harga belum dikonfigurasi untuk spesifikasi dan jumlah ini.']);
});

test('inactive price rows and tier gaps cannot be quoted', function (bool $active, string $qty): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function () use ($active): Product {
        $product = Product::factory()->create();
        ProductPrice::factory()->for($product)->create(['qty_max' => '10', 'is_active' => $active]);

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => $qty])->assertSessionHasErrors('price');
})->with(['inactive price' => [false, '1'], 'gap after upper bound' => [true, '11']]);

test('inactive products cannot be quoted', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, fn () => Product::factory()->create(['is_active' => false]));

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '1'])->assertSessionHasErrors(['product' => 'Produk ini sedang nonaktif.']);
});

test('quote validation rejects nonpositive quantities malformed decimals and missing dimensions', function (array $input, string $error): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function (): Product {
        $product = Product::factory()->area()->create();
        ProductPrice::factory()->for($product)->create();

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), $input)->assertSessionHasErrors($error);
})->with(['zero quantity' => [['qty' => '0'], 'qty'], 'negative quantity' => [['qty' => '-1'], 'qty'], 'exponent quantity' => [['qty' => '1e3'], 'qty'], 'fraction precision' => [['qty' => '0.00001'], 'qty'], 'negative dimension' => [['qty' => '1', 'length' => '-1'], 'length'], 'length required' => [['qty' => '1'], 'length'], 'width required' => [['qty' => '1', 'length' => '100'], 'width']]);

test('overflow totals return a validation error instead of an imprecise amount', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function (): Product {
        $product = Product::factory()->create();
        ProductPrice::factory()->for($product)->create(['unit_price' => '9999999999999']);

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '2'])->assertSessionHasErrors(['qty' => 'Total melampaui batas nominal. Kurangi jumlah atau ukuran.']);
});

test('rounding happens after multiplication at the nearest rupiah', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function (): Product {
        $product = Product::factory()->create();
        ProductPrice::factory()->for($product)->create(['unit_price' => '100.10']);

        return $product;
    });

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('product-quotes.store', $product), ['qty' => '5'])->assertSessionHas('price_quote.subtotal', '501.00');
});

test('hidden transaction module blocks every catalog endpoint', function (): void {
    [$owner, $tenant] = catalogCompany();
    $product = inCatalogCompany($tenant, function (): Product {
        TenantModuleEntitlement::whereHas('module', fn ($query) => $query->where('code', 'POS'))->firstOrFail()->update(['is_enabled' => false]);

        return Product::factory()->create();
    });
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);

    $this->get(route('products.index'))->assertForbidden();
    $this->post(route('products.store'), catalogProductInput())->assertForbidden();
    $this->post(route('product-prices.store', $product), catalogPriceInput())->assertForbidden();
    $this->post(route('product-quotes.store', $product), ['qty' => '1'])->assertForbidden();

    $this->assertDatabaseCount('product_prices', 0);
});

test('pending license blocks catalog access', function (): void {
    [$owner, $tenant] = catalogCompany();
    inCatalogCompany($tenant, fn () => License::firstOrFail()->update(['status' => 'pending']));

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('products.index'))->assertForbidden();
});
