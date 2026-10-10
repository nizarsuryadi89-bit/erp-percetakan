<?php

use App\Actions\CreateCompany;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\License;
use App\Models\PrintOrder;
use App\Models\PrintOrderItem;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Models\TenantModuleEntitlement;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});
function inDesk(Tenant $tenant, Closure $work): mixed
{
    $context = app(TenantContext::class);
    $registrar = app(PermissionRegistrar::class);
    $old = $context->tenant();
    $team = $registrar->getPermissionsTeamId();
    $context->set($tenant);
    $registrar->setPermissionsTeamId($tenant->id);
    try {
        return $work();
    } finally {
        $context->set($old);
        $registrar->setPermissionsTeamId($team);
    }
}
/** @return array{0:User,1:Tenant,2:Branch,3:Customer,4:Product} */
function deskFixture(string $role = 'Owner'): array
{
    $user = User::factory()->create();
    $tenant = app(CreateCompany::class)->handle($user, ['name' => 'Cetak Contoh'])->refresh();
    [$branch,$customer,$product] = inDesk($tenant, function () use ($user, $role, $tenant): array {
        License::firstOrFail()->update(['status' => 'active', 'activated_at' => now()]);
        TenantModuleEntitlement::whereHas('module', fn ($q) => $q->where('code', 'POS'))->firstOrFail()->update(['is_granted' => true]);
        $user->syncRoles([$role]);
        $branch = Branch::firstOrFail();
        DB::table('branch_user')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'user_id' => $user->id]);
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        $product = Product::factory()->create(['name' => 'HVS 100 gr']);
        ProductPrice::factory()->for($product)->create(['variant' => 'HVS 100 gr', 'print_side' => '2 sisi', 'qty_min' => '20', 'qty_max' => '20', 'unit_price' => '4100', 'hpp' => '2173']);

        return [$branch, $customer, $product];
    });

    return [$user, $tenant, $branch, $customer, $product];
}
/** @return array<string,mixed> */
function deskHeader(Branch $branch, Customer $customer): array
{
    return ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'customer_id' => $customer->id, 'method' => 'walk_in'];
}
/** @return array<string,mixed> */
function deskItem(Product $product, int $version = 1): array
{
    return ['request_key' => (string) Str::uuid(), 'version' => $version, 'product_id' => $product->id, 'qty' => '20', 'variant' => 'HVS 100 gr', 'print_side' => '2 sisi'];
}
function deskLatest(Tenant $tenant): PrintOrder
{
    return inDesk($tenant, fn () => PrintOrder::latest('id')->firstOrFail());
}

test('POS alone creates multi item SPK and preserves historical customer branding prices and HPP', function (): void {
    [$user,$tenant,$branch,$customer,$product] = deskFixture('Kasir');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), [...deskHeader($branch, $customer), 'deadline' => '2026-10-12T15:30', 'notes' => '<script>unsafe()</script>'])->assertRedirect()->assertSessionHasNoErrors();
    $order = deskLatest($tenant);
    expect($order->status)->toBe('draft');
    expect($order->deadline->format('H:i'))->toBe('08:30');
    $input = deskItem($product);
    $this->postJson(route('print-order-items.preview', $order), $input)->assertOk()->assertJsonPath('quote.subtotal', '82000.00')->assertJsonMissingPath('quote.hpp');
    $this->post(route('print-order-items.store', $order), $input)->assertSessionHasNoErrors();
    $lanyard = inDesk($tenant, function (): Product {
        $p = Product::factory()->create(['name' => 'Lanyard', 'unit_name' => 'Pcs']);
        ProductPrice::factory()->for($p)->create(['variant' => '2 cm', 'finishing' => 'Stopper', 'qty_min' => '5', 'qty_max' => '5', 'unit_price' => '12000']);

        return $p;
    });
    $this->post(route('print-order-items.store', $order), ['request_key' => (string) Str::uuid(), 'version' => 2, 'product_id' => $lanyard->id, 'qty' => '5', 'variant' => '2 cm', 'finishing' => 'Stopper'])->assertSessionHasNoErrors();
    $this->patch(route('print-orders.status', $order), ['request_key' => (string) Str::uuid(), 'version' => 3, 'status' => 'confirmed'])->assertSessionHasNoErrors();
    $oldCustomer = $customer->name;
    inDesk($tenant, function () use ($product, $customer, $tenant): void {
        $product->prices()->first()->update(['unit_price' => '9000', 'hpp' => '5555']);
        $product->update(['name' => 'Nama baru']);
        $customer->update(['name' => 'Pelanggan baru']);
        $tenant->update(['name' => 'Brand baru']);
    });
    $this->get(route('print-orders.print', $order))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page->component('deskprint/print')->where('order.total', '142000.00')->where('order.status', 'confirmed')->where('order.customer_snapshot.name', $oldCustomer)->where('order.company_snapshot.name', 'Cetak Contoh')->where('order.items.0.snapshot.product_name', 'HVS 100 gr')->where('order.items.0.snapshot.subtotal', '82000.00')->missing('order.items.0.cost_snapshot'));
    expect(inDesk($tenant, fn () => PrintOrderItem::first()->cost_snapshot['hpp']))->toBe('2173.00');
    $this->assertDatabaseCount('product_quotes', 0);
    $this->assertDatabaseCount('print_order_events', 4);
});
test('draft numbering and retries do not duplicate or reuse SPK numbers', function (): void {
    $this->freezeTime();
    [$user,$tenant,$branch,$customer] = deskFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $input = deskHeader($branch, $customer);
    $first = $this->post(route('print-orders.store'), $input)->assertRedirect()->headers->get('Location');
    $this->post(route('print-orders.store'), $input)->assertRedirect($first);
    $this->assertDatabaseCount('print_orders', 1);
    $this->assertDatabaseCount('deskprint_requests', 1);
    $order = deskLatest($tenant);
    $this->patch(route('print-orders.status', $order), ['request_key' => (string) Str::uuid(), 'version' => 1, 'status' => 'cancelled', 'reason' => 'Batal'])->assertSessionHasNoErrors();
    $this->post(route('print-orders.store'), deskHeader($branch, $customer))->assertSessionHasNoErrors();
    $numbers = inDesk($tenant, fn () => PrintOrder::orderBy('id')->pluck('number')->all());
    expect($numbers[0])->toEndWith('-000001');
    expect($numbers[1])->toEndWith('-000002');
    $this->post(route('print-orders.store'), [...$input, 'method' => 'online'])->assertSessionHasErrors('request_key');
    $this->assertDatabaseCount('print_orders', 2);
});
test('customer search creation and retry are branch scoped without premium CRM', function (): void {
    [$user,$tenant,$branch] = deskFixture('Kasir');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $data = ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'name' => 'Pelanggan Baru', 'phone' => '0812987654', 'address' => 'Alamat'];
    $id = $this->postJson(route('customers.store'), $data)->assertOk()->json('customer.id');
    $this->postJson(route('customers.store'), $data)->assertOk()->assertJsonPath('customer.id', $id);
    $this->getJson(route('customers.index', ['branch_id' => $branch->id, 'search' => '987654']))->assertOk()->assertJsonCount(1, 'customers')->assertJsonPath('customers.0.name', 'Pelanggan Baru');
    $this->assertDatabaseCount('customers', 2);
    $this->assertDatabaseHas('audit_logs', ['action' => 'pelanggan.dibuat']);
});
test('draft item revisions retain snapshots and stale writes are rejected', function (): void {
    [$user,$tenant,$branch,$customer,$product] = deskFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $order = deskLatest($tenant);
    $data = deskItem($product);
    $this->post(route('print-order-items.store', $order), $data)->assertSessionHasNoErrors();
    $this->post(route('print-order-items.store', $order), $data)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('print_order_items', 1);
    $item = inDesk($tenant, fn () => PrintOrderItem::firstOrFail());
    inDesk($tenant, fn () => $product->prices()->first()->update(['unit_price' => '5000']));
    $revision = deskItem($product, 2);
    $this->patch(route('print-order-items.update', [$order, $item]), $revision)->assertSessionHasNoErrors();
    $this->patch(route('print-order-items.update', [$order, $item]), $revision)->assertSessionHasNoErrors();
    $this->post(route('print-order-items.store', $order), deskItem($product, 2))->assertSessionHasErrors('version');
    $new = inDesk($tenant, fn () => PrintOrderItem::latest('id')->firstOrFail());
    expect($new->snapshot['subtotal'])->toBe('100000.00');
    expect($item->refresh()->snapshot['subtotal'])->toBe('82000.00');
    expect($item->state)->toBe('superseded');
    $remove = ['request_key' => (string) Str::uuid(), 'version' => 3];
    $this->delete(route('print-order-items.destroy', [$order, $new]), $remove)->assertSessionHasNoErrors();
    $this->delete(route('print-order-items.destroy', [$order, $new]), $remove)->assertSessionHasNoErrors();
    expect(deskLatest($tenant)->total)->toBe('0.00');
    $this->assertDatabaseCount('print_order_items', 2);
});
test('confirmed and cancelled SPK refuse edits while retries and history remain safe', function (): void {
    [$user,$tenant,$branch,$customer,$product] = deskFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $order = deskLatest($tenant);
    $this->patch(route('print-orders.status', $order), ['request_key' => (string) Str::uuid(), 'version' => 1, 'status' => 'confirmed'])->assertSessionHasErrors('status');
    $input = deskItem($product);
    $this->post(route('print-order-items.store', $order), $input);
    $confirm = ['request_key' => (string) Str::uuid(), 'version' => 2, 'status' => 'confirmed'];
    $this->patch(route('print-orders.status', $order), $confirm)->assertSessionHasNoErrors();
    $this->patch(route('print-orders.status', $order), $confirm)->assertSessionHasNoErrors();
    $this->post(route('print-order-items.store', $order), $input)->assertSessionHasNoErrors();
    $this->post(route('print-order-items.store', $order), deskItem($product, 3))->assertSessionHasErrors('status');
    $item = inDesk($tenant, fn () => PrintOrderItem::first());
    $this->patch(route('print-order-items.update', [$order, $item]), deskItem($product, 3))->assertSessionHasErrors('status');
    $this->delete(route('print-order-items.destroy', [$order, $item]), ['request_key' => (string) Str::uuid(), 'version' => 3])->assertSessionHasErrors('status');
    $cancel = ['request_key' => (string) Str::uuid(), 'version' => 3, 'status' => 'cancelled'];
    $this->patch(route('print-orders.status', $order), $cancel)->assertSessionHasErrors('reason');
    $this->patch(route('print-orders.status', $order), [...$cancel, 'reason' => 'Pelanggan membatalkan'])->assertSessionHasNoErrors();
    $this->patch(route('print-orders.status', $order), ['request_key' => (string) Str::uuid(), 'version' => 4, 'status' => 'confirmed'])->assertSessionHasErrors('status');
    expect(deskLatest($tenant)->total)->toBe('82000.00');
    $this->assertDatabaseCount('print_order_items', 1);
});
test('browser money IDs and tenant fields cannot forge snapshots', function (): void {
    [$user,$tenant,$branch,$customer,$product] = deskFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), [...deskHeader($branch, $customer), 'tenant_id' => 999, 'total' => '1', 'number' => 'FORGED', 'status' => 'confirmed']);
    $order = deskLatest($tenant);
    expect($order->status)->toBe('draft');
    $this->post(route('print-order-items.store', $order), [...deskItem($product), 'tenant_id' => 999, 'branch_id' => 999, 'unit_price' => '1', 'subtotal' => '1', 'price_id' => 999, 'cost_snapshot' => ['hpp' => 0]])->assertSessionHasNoErrors();
    $item = inDesk($tenant, fn () => PrintOrderItem::firstOrFail());
    expect(deskLatest($tenant)->total)->toBe('82000.00');
    expect($item->cost_snapshot['hpp'])->toBe('2173.00');
    inDesk($tenant, function () use ($item, $order): void {
        expect(fn () => $item->update(['snapshot' => ['subtotal' => '1']]))->toThrow(LogicException::class);
        expect(fn () => $order->update(['customer_snapshot' => ['name' => 'Forged']]))->toThrow(LogicException::class);
    });
});
test('ambiguous matrix aborts item receipt and order total atomically', function (): void {
    [$user,$tenant,$branch,$customer,$product] = deskFixture();
    inDesk($tenant, fn () => ProductPrice::factory()->for($product)->create(['variant' => 'HVS 100 gr', 'print_side' => '2 sisi', 'unit_price' => '5000']));
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $this->post(route('print-order-items.store', deskLatest($tenant)), deskItem($product))->assertSessionHasErrors(['price' => 'Lebih dari satu harga aktif cocok. Perbaiki rentang harga sebelum menghitung.']);
    $this->assertDatabaseCount('print_order_items', 0);
    $this->assertDatabaseCount('deskprint_requests', 1);
    expect(deskLatest($tenant)->version)->toBe(1);
});
test('preview and item snapshots have identical dimensional decimal minimum and rounding results', function (array $settings, array $dimensions, string $expected, string $minimum): void {
    [$user,$tenant,$branch,$customer,$product] = deskFixture();
    inDesk($tenant, function () use ($product, $settings, $minimum): void {
        $product->update($settings);
        $product->prices()->first()->update(['qty_min' => '1', 'qty_max' => null, 'unit_price' => '25000', 'minimum_charge' => $minimum]);
    });
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $order = deskLatest($tenant);
    $data = [...deskItem($product), ...$dimensions];
    $quote = $this->postJson(route('print-order-items.preview', $order), $data)->assertOk()->assertJsonPath('quote.subtotal', $expected)->json('quote');
    $this->post(route('print-order-items.store', $order), $data)->assertSessionHasNoErrors();
    expect(inDesk($tenant, fn () => PrintOrderItem::first()->snapshot))->toBe($quote);
    expect(deskLatest($tenant)->total)->toBe($expected);
})->with([
    'area cm' => [['pricing_basis' => 'area'], ['qty' => '3', 'length' => '150', 'width' => '200'], '225000.00', '0'],
    'fractional length m' => [['pricing_basis' => 'length', 'dimension_unit' => 'm'], ['qty' => '0.5', 'length' => '1.2'], '15000.00', '0'],
    'minimum dimensions' => [['pricing_basis' => 'area', 'minimum_length' => '5', 'minimum_width' => '5'], ['qty' => '1', 'length' => '2', 'width' => '3'], '63.00', '0'],
    'minimum charge' => [['pricing_basis' => 'area'], ['qty' => '1', 'length' => '50', 'width' => '50'], '10000.00', '10000'],
]);
test('default role operation permissions follow the intended matrix', function (string $role, bool $allowed): void {
    [$user,$tenant] = deskFixture($role);
    expect(inDesk($tenant, fn () => $user->can('spk.manage')))->toBe($allowed);
})->with([['Owner', true], ['Administrator', true], ['Kasir', true], ['Read Only', false], ['Keuangan', false], ['Operator Produksi', false]]);
test('read only can view and print assigned SPK but cannot mutate it', function (): void {
    [$owner,$tenant,$branch,$customer] = deskFixture();
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $order = deskLatest($tenant);
    inDesk($tenant, fn () => $owner->syncRoles(['Read Only']));
    $this->get(route('print-orders.show', $order))->assertOk();
    $this->get(route('print-orders.print', $order))->assertOk();
    $this->post(route('print-orders.store'), deskHeader($branch, $customer))->assertForbidden();
    $this->postJson(route('customers.store'), [])->assertForbidden();
    $this->patch(route('print-orders.status', $order), [])->assertForbidden();
});
test('branch assignment controls customers SPK listing and branch switching', function (): void {
    [$user,$tenant,$branch,$customer] = deskFixture('Kasir');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $order = deskLatest($tenant);
    DB::table('branch_user')->where('user_id', $user->id)->delete();
    $this->get(route('print-orders.show', $order))->assertNotFound();
    $this->get(route('print-orders.print', $order))->assertNotFound();
    $this->getJson(route('customers.index', ['branch_id' => $branch->id]))->assertNotFound();
    $this->post(route('branches.switch'), ['branch_id' => $branch->id])->assertNotFound();
    $this->get(route('print-orders.index'))->assertInertia(fn (Assert $p) => $p->has('orders.data', 0)->has('workspace.branches', 0));
});
test('foreign tenant and mismatched customer product item relations return 404', function (): void {
    [$user,$tenant,$branch,$customer,$product] = deskFixture();
    [$other,$foreignTenant,$foreignBranch,$foreignCustomer,$foreignProduct] = deskFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $foreignCustomer))->assertNotFound();
    $this->post(route('print-orders.store'), deskHeader($foreignBranch, $foreignCustomer))->assertNotFound();
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $order = deskLatest($tenant);
    $this->post(route('print-order-items.store', $order), deskItem($foreignProduct))->assertNotFound();
    $this->post(route('print-order-items.store', $order), deskItem($product));
    $item = inDesk($tenant, fn () => PrintOrderItem::first());
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $this->patch(route('print-order-items.update', [deskLatest($tenant), $item]), deskItem($product))->assertNotFound();
    $this->actingAs($other)->withSession(['tenant_id' => $foreignTenant->id]);
    $this->get(route('print-orders.show', $order))->assertNotFound();
    $this->get(route('print-order-items.catalog', $order))->assertNotFound();
});
test('POS license membership branch and permission revocations stop writes and retries', function (string $restriction): void {
    [$user,$tenant,$branch,$customer] = deskFixture('Kasir');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $data = deskHeader($branch, $customer);
    $this->post(route('print-orders.store'), $data);
    $order = deskLatest($tenant);
    inDesk($tenant, function () use ($restriction, $tenant, $user, $branch): void {
        match ($restriction) {
            'POS' => TenantModuleEntitlement::query()->update(['is_enabled' => false]),'license' => License::first()->update(['status' => 'suspended']),
            'membership' => $tenant->users()->updateExistingPivot($user->id, ['is_active' => false]),'branch' => $branch->update(['is_active' => false]),'permission' => $user->syncRoles(['Read Only']),
        };
    });
    $expected = $restriction === 'branch' ? 404 : 403;
    $this->withSession(['tenant_id' => $tenant->id])->post(route('print-orders.store'), $data)->assertStatus($expected);
    if ($restriction !== 'permission') {
        $this->withSession(['tenant_id' => $tenant->id])->get(route('print-orders.show', $order))->assertStatus($expected);
    }
})->with(['POS', 'license', 'membership', 'branch', 'permission']);
test('only active tenant members can be explicitly assigned by a branch manager', function (): void {
    [$owner,$tenant,$branch] = deskFixture();
    $staff = User::factory()->create();
    $foreign = User::factory()->create();
    $tenant->users()->attach($staff, ['is_active' => true]);
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->patch(route('branch-memberships.update', $branch), ['user_ids' => [$foreign->id]])->assertSessionHasErrors('user_ids');
    $this->patch(route('branch-memberships.update', $branch), ['user_ids' => [$staff->id]])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('branch_user', ['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'user_id' => $staff->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'cabang.akses_diperbarui']);
    inDesk($tenant, fn () => $owner->syncRoles(['Kasir']));
    $this->patch(route('branch-memberships.update', $branch), ['user_ids' => []])->assertForbidden();
});
test('pending Deskprint migration fails clearly while the catalog remains accessible', function (): void {
    [$user,$tenant] = deskFixture();
    Schema::drop('deskprint_requests');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->get(route('print-orders.index'))->assertStatus(503);
    $this->get(route('products.index'))->assertOk();
});
test('Deskprint requires authentication', function (): void {
    $this->get(route('print-orders.index'))->assertRedirect(route('login'));
    $this->post(route('print-orders.store'), [])->assertRedirect(route('login'));
});
test('invalid quantities dimensions tiers and specifications never persist items', function (array $override, string $field): void {
    [$user,$tenant,$branch,$customer,$product] = deskFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $this->post(route('print-order-items.store', deskLatest($tenant)), [...deskItem($product), ...$override])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('print_order_items', 0);
})->with([[['qty' => '0'], 'qty'], [['qty' => '-1'], 'qty'], [['qty' => '2e1'], 'qty'], [['qty' => '20.00001'], 'qty'], [['length' => '-1'], 'length'], [['finishing' => 'Belum dikonfigurasi'], 'price'], [['qty' => '21'], 'price']]);
test('methods and marketplace metadata are manually recorded on the draft', function (string $method): void {
    [$user,$tenant,$branch,$customer] = deskFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), [...deskHeader($branch, $customer), 'method' => $method, 'marketplace_name' => 'Toko Online', 'marketplace_reference' => 'REF-1', 'marketplace_tracking' => 'RESI-1'])->assertSessionHasNoErrors();
    $order = deskLatest($tenant);
    expect($order->method)->toBe($method);
    expect($order->marketplace)->toBe($method === 'marketplace' ? ['name' => 'Toko Online', 'reference' => 'REF-1', 'tracking' => 'RESI-1'] : null);
})->with(['walk_in', 'online', 'marketplace']);
test('database constraints refuse cross branch customers and cross tenant products', function (): void {
    [$owner,$tenant,$branch,$customer,$product] = deskFixture();
    [$other,$foreignTenant,$foreignBranch,$foreignCustomer,$foreignProduct] = deskFixture();
    $another = inDesk($tenant, fn () => Branch::factory()->create());
    inDesk($tenant, fn () => expect(fn () => PrintOrder::factory()->create(['customer_id' => $customer->id, 'branch_id' => $another->id]))->toThrow(QueryException::class));
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $order = deskLatest($tenant);
    inDesk($tenant, fn () => expect(fn () => PrintOrderItem::create(['print_order_id' => $order->id, 'product_id' => $foreignProduct->id, 'snapshot' => [], 'cost_snapshot' => []]))->toThrow(QueryException::class));
});
test('SPK listing filters status and searches customer snapshots rather than current master', function (): void {
    [$user,$tenant,$branch,$customer] = deskFixture();
    inDesk($tenant, fn () => $customer->update(['name' => 'Pelanggan Khusus']));
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $order = deskLatest($tenant);
    $this->get(route('print-orders.index', ['search' => 'Khusus', 'status' => 'draft']))->assertInertia(fn (Assert $p) => $p->has('orders.data', 1));
    $this->get(route('print-orders.index', ['status' => 'confirmed']))->assertInertia(fn (Assert $p) => $p->has('orders.data', 0));
    $this->getJson(route('print-order-items.catalog', $order))->assertOk()->assertJsonMissingPath('products.0.prices.0.hpp');
});

test('staff assigned to one branch cannot access another branch in the same tenant', function (): void {
    [$owner,$tenant,$branch,$customer] = deskFixture();
    [$another,$otherCustomer] = inDesk($tenant, function (): array {
        $b = Branch::factory()->create();

        return [$b, Customer::factory()->create(['branch_id' => $b->id])];
    });
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($another, $otherCustomer))->assertSessionHasNoErrors();
    $otherOrder = deskLatest($tenant);
    inDesk($tenant, fn () => $owner->syncRoles(['Kasir']));
    $this->get(route('print-orders.show', $otherOrder))->assertNotFound();
    $this->getJson(route('customers.index', ['branch_id' => $another->id]))->assertNotFound();
    $this->post(route('print-orders.store'), deskHeader($branch, $otherCustomer))->assertNotFound();
    $this->post(route('print-orders.store'), deskHeader($branch, $customer))->assertSessionHasNoErrors();
    $this->get(route('print-orders.index'))->assertInertia(fn (Assert $p) => $p->has('orders.data', 1));
});
test('reusing an idempotency key across branches rolls back the conflicting operation', function (): void {
    [$owner,$tenant,$branch,$customer] = deskFixture();
    [$another,$otherCustomer] = inDesk($tenant, function (): array {
        $b = Branch::factory()->create();

        return [$b, Customer::factory()->create(['branch_id' => $b->id])];
    });
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $first = deskHeader($branch, $customer);
    $this->post(route('print-orders.store'), $first)->assertSessionHasNoErrors();
    $this->post(route('print-orders.store'), [...deskHeader($another, $otherCustomer), 'request_key' => $first['request_key']])->assertSessionHasErrors('request_key');
    $this->assertDatabaseCount('print_orders', 1);
    expect($another->refresh()->spk_sequence)->toBe(0);
});
test('order total overflow rolls back only the new item and receipt', function (): void {
    [$owner,$tenant,$branch,$customer,$product] = deskFixture();
    inDesk($tenant, fn () => $product->prices()->first()->update(['unit_price' => '250000000000']));
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), deskHeader($branch, $customer));
    $order = deskLatest($tenant);
    $this->post(route('print-order-items.store', $order), deskItem($product))->assertSessionHasNoErrors();
    $this->post(route('print-order-items.store', $order), deskItem($product, 2))->assertSessionHasErrors('total');
    expect(deskLatest($tenant)->total)->toBe('5000000000000.00');
    expect(deskLatest($tenant)->version)->toBe(2);
    $this->assertDatabaseCount('print_order_items', 1);
    $this->assertDatabaseCount('deskprint_requests', 2);
});
test('marketplace identity invalid deadline and missing request identifiers are rejected', function (array $override, string $field): void {
    [$owner,$tenant,$branch,$customer] = deskFixture();
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('print-orders.store'), [...deskHeader($branch, $customer), ...$override])->assertSessionHasErrors($field);
    $this->assertDatabaseCount('print_orders', 0);
})->with([[['method' => 'marketplace'], 'marketplace_name'], [['deadline' => 'not-a-date'], 'deadline'], [['request_key' => 'not-uuid'], 'request_key'], [['method' => 'unconfigured'], 'method']]);
test('permission upgrade preserves catalog licenses and existing role assignments', function (): void {
    [$owner,$tenant,$branch,$customer,$product] = deskFixture('Kasir');
    $before = DB::table('model_has_roles')->where('model_id', $owner->id)->get()->toArray();
    $license = inDesk($tenant, fn () => License::first()->getAttributes());
    DB::table('permissions')->where('name', 'spk.manage')->delete();
    $migration = require collect(glob(database_path('migrations/*grant_deskprint_permissions.php')))->first();
    $migration->up();
    $owner->unsetRelation('roles')->unsetRelation('permissions');
    expect(inDesk($tenant, fn () => $owner->can('spk.manage')))->toBeTrue();
    expect(DB::table('model_has_roles')->where('model_id', $owner->id)->get()->toArray())->toEqual($before);
    expect(inDesk($tenant, fn () => License::first()->getAttributes()))->toBe($license);
    expect(inDesk($tenant, fn () => Product::findOrFail($product->id)->name))->toBe('HVS 100 gr');
});
test('Deskprint schema upgrade preserves products and branch identity without auto granting staff branches', function (): void {
    [$owner,$tenant,$branch,$customer,$product] = deskFixture();
    $migration = require collect(glob(database_path('migrations/*create_deskprint_tables.php')))->first();
    $migration->down();
    $migration->up();
    expect(inDesk($tenant, fn () => Product::findOrFail($product->id)->prices()->first()->unit_price))->toBe('4100.00');
    expect(inDesk($tenant,fn () => Branch::findOrFail($branch->id)->code))->toBe($branch->code);
    $this->assertDatabaseCount('branch_user',0);
});
