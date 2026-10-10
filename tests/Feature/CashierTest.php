<?php

use App\Actions\CreateCompany;
use App\Actions\ManagePrintOrder;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\License;
use App\Models\PaymentMethod;
use App\Models\PrintOrder;
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
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);
beforeEach(function (): void {
    $this->withoutVite();
});
function inCashier(Tenant $tenant, Closure $work): mixed
{
    $context = app(TenantContext::class);
    $registrar = app(PermissionRegistrar::class);
    $previous = $context->tenant();
    $team = $registrar->getPermissionsTeamId();
    $context->set($tenant);
    $registrar->setPermissionsTeamId($tenant->id);
    try {
        return $work();
    } finally {
        $context->set($previous);
        $registrar->setPermissionsTeamId($team);
    }
}
/** @return array{User,Tenant,Branch,PrintOrder,Product} */
function cashierFixture(string $role = 'Kasir'): array
{
    $user = User::factory()->create();
    $tenant = app(CreateCompany::class)->handle($user, ['name' => 'Percetakan Uji'])->refresh();
    [$branch,$order,$product] = inCashier($tenant, function () use ($user, $tenant, $role): array {
        License::firstOrFail()->update(['status' => 'active', 'activated_at' => now()]);
        TenantModuleEntitlement::whereHas('module', fn ($q) => $q->where('code', 'POS'))->firstOrFail()->update(['is_granted' => true]);
        $branch = Branch::firstOrFail();
        DB::table('branch_user')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'user_id' => $user->id]);
        $customer = Customer::factory()->create(['branch_id' => $branch->id]);
        $product = Product::factory()->create(['name' => 'HVS 100 gr']);
        ProductPrice::factory()->for($product)->create(['variant' => 'HVS 100 gr', 'print_side' => '2 sisi', 'qty_min' => '20', 'qty_max' => '20', 'unit_price' => '4100', 'hpp' => '2173']);
        $action = app(ManagePrintOrder::class);
        $order = $action->create($user, ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'customer_id' => $customer->id, 'method' => 'walk_in']);
        $order = $action->item($user, $order, ['request_key' => (string) Str::uuid(), 'version' => 1, 'product_id' => $product->id, 'qty' => '20', 'variant' => 'HVS 100 gr', 'print_side' => '2 sisi']);
        $order = $action->transition($user, $order, ['request_key' => (string) Str::uuid(), 'version' => 2, 'status' => 'confirmed']);
        $user->syncRoles([$role]);
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return [$branch, $order, $product];
    });

    return [$user, $tenant, $branch, $order, $product];
}
function cashierInvoice(Tenant $tenant): Invoice
{
    return inCashier($tenant, fn () => Invoice::latest('id')->firstOrFail());
}
/** @return array<string,mixed> */
function cashierPayment(Tenant $tenant, string $amount, string $kind = 'cash'): array
{
    $method = inCashier($tenant, fn () => PaymentMethod::where('kind', $kind)->firstOrFail());

    return ['request_key' => (string) Str::uuid(), 'payments' => [['payment_method_id' => $method->id, 'amount' => $amount, 'reference' => $kind === 'cash' ? null : 'Bukti uji', 'paid_at' => now($tenant->timezone)->subMinute()->format('Y-m-d\TH:i'), 'notes' => null]]];
}

test('POS only issues a historical invoice and receives DP then split payment until paid', function (): void {
    [$user,$tenant,$branch,$order,$product] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
    $invoice = cashierInvoice($tenant);
    expect($invoice->total)->toBe('82000.00');
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '20000'))->assertSessionHasNoErrors();
    $input = cashierPayment($tenant, '30000', 'transfer');
    $input['payments'][] = cashierPayment($tenant, '32000', 'ewallet')['payments'][0];
    $this->post(route('invoice-payments.store', $invoice), $input)->assertSessionHasNoErrors();
    $this->get(route('invoices.show', $invoice))->assertOk()->assertInertia(fn (Assert $page) => $page->component('cashier/show')->where('invoice.summary.payment_status', 'paid')->where('invoice.summary.paid', '82000.00')->where('invoice.summary.balance', '0.00')->has('invoice.payments', 3));
    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseCount('invoice_payments', 3);
    $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.pembayaran_dicatat']);
});

test('invoice issue and payment retries never duplicate evidence and changed payload is rejected', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $key = ['request_key' => (string) Str::uuid()];
    $location = $this->post(route('invoices.store', $order), $key)->assertSessionHasNoErrors()->headers->get('Location');
    $this->post(route('invoices.store', $order), $key)->assertRedirect($location);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()])->assertRedirect($location);
    $invoice = cashierInvoice($tenant);
    $input = cashierPayment($tenant, '20000');
    $this->post(route('invoice-payments.store', $invoice), $input)->assertSessionHasNoErrors();
    $this->post(route('invoice-payments.store', $invoice), $input)->assertSessionHasNoErrors();
    $input['payments'][0]['amount'] = '21000';
    $this->post(route('invoice-payments.store', $invoice), $input)->assertSessionHasErrors('request_key');
    $this->assertDatabaseCount('invoices', 1);
    $this->assertDatabaseCount('invoice_payments', 1);
    $this->assertDatabaseCount('invoice_events', 2);
    expect(inCashier($tenant, fn () => Branch::first()->invoice_sequence))->toBe(1);
    $this->get(route('invoices.create', $order))->assertRedirect($location);
});
test('payment cannot overpay and split batch fails atomically', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $input = cashierPayment($tenant, '50000');
    $input['payments'][] = cashierPayment($tenant, '40000', 'transfer')['payments'][0];
    $this->post(route('invoice-payments.store', $invoice), $input)->assertSessionHasErrors(['payments' => 'Pembayaran melebihi sisa tagihan atau tidak positif. Kembalian belum didukung.']);
    $this->assertDatabaseCount('invoice_payments', 0);
    $this->assertDatabaseCount('cashier_requests', 1);
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '82000'))->assertSessionHasNoErrors();
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '0.01'))->assertSessionHasErrors('payments');
    $this->assertDatabaseCount('invoice_payments', 1);
});
test('payment rejects malformed decimal money', function (mixed $amount): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $input = cashierPayment($tenant, '10');
    $input['payments'][0]['amount'] = $amount;
    $this->post(route('invoice-payments.store', $invoice), $input)->assertSessionHasErrors('payments.0.amount');
    $this->assertDatabaseCount('invoice_payments', 0);
})->with(['negative' => '-1', 'zero' => '0', 'fraction' => '0.001', 'comma' => '20,000', 'scientific' => '1e3', 'overflow' => '100000000000000', 'float' => 12.34, 'array' => [['value' => 12]], 'empty' => '']);
test('decimal split payment and later correction use exact cents', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $input = cashierPayment($tenant, '0.10');
    $input['payments'][] = cashierPayment($tenant, '0.20', 'debit')['payments'][0];
    $this->post(route('invoice-payments.store', $invoice), $input)->assertSessionHasNoErrors();
    $this->get(route('invoices.show', $invoice))->assertInertia(fn (Assert $page) => $page->where('invoice.summary.paid', '0.30')->where('invoice.summary.balance', '81999.70'));
    $entry = inCashier($tenant, fn () => InvoicePayment::where('entry_type', 'payment')->first());
    $this->post(route('invoice-payments.reverse', [$invoice, $entry]), ['request_key' => (string) Str::uuid(), 'reason' => 'Nominal salah', 'record_was_incorrect' => true])->assertSessionHasNoErrors();
    $this->get(route('invoices.show', $invoice))->assertInertia(fn (Assert $page) => $page->where('invoice.summary.paid', '0.20')->where('invoice.summary.balance', '81999.80'));
});
test('reversal is append only authorized idempotent and distinct from refund', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '82000'));
    $payment = inCashier($tenant, fn () => InvoicePayment::firstOrFail());
    $original = $payment->getAttributes();
    $data = ['request_key' => (string) Str::uuid(), 'reason' => 'Transfer salah dicatat', 'record_was_incorrect' => true];
    $this->post(route('invoice-payments.reverse', [$invoice, $payment]), [...$data, 'record_was_incorrect' => false])->assertSessionHasErrors('record_was_incorrect');
    $this->post(route('invoices.void', $invoice), ['request_key' => (string) Str::uuid(), 'reason' => 'Batalkan'])->assertSessionHasErrors('status');
    $this->post(route('invoice-payments.reverse', [$invoice, $payment]), $data)->assertSessionHasNoErrors();
    $this->post(route('invoice-payments.reverse', [$invoice, $payment]), $data)->assertSessionHasNoErrors();
    $this->post(route('invoice-payments.reverse', [$invoice, $payment]), [...$data, 'request_key' => (string) Str::uuid()])->assertSessionHasErrors('payment');
    expect(inCashier($tenant, fn () => $payment->refresh()->getAttributes()))->toBe($original);
    $this->assertDatabaseCount('invoice_payments', 2);
    $this->get(route('invoices.print', [$invoice, 'receipt' => $payment->id]))->assertInertia(fn (Assert $page) => $page->where('invoice.summary.payment_status', 'unpaid')->where('invoice.summary.paid', '0.00')->where('receipt.id', $payment->id)->where('invoice.payments.1.reverses_payment_id', $payment->id));
    $this->assertDatabaseHas('audit_logs', ['action' => 'invoice.pembayaran_dikoreksi']);
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '82000'))->assertSessionHasNoErrors();
});
test('SPK cancellation is blocked until unpaid invoice void and void cannot be reissued', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $cancel = ['request_key' => (string) Str::uuid(), 'version' => 3, 'status' => 'cancelled', 'reason' => 'Pelanggan batal'];
    $this->patch(route('print-orders.status', $order), $cancel)->assertSessionHasErrors('status');
    expect(inCashier($tenant, fn () => $order->refresh()->status))->toBe('confirmed');
    expect(fn () => inCashier($tenant, fn () => $order->update(['status' => 'cancelled'])))->toThrow(LogicException::class);
    $data = ['request_key' => (string) Str::uuid(), 'reason' => 'Pesanan batal sebelum bayar'];
    $this->post(route('invoices.void', $invoice), $data)->assertSessionHasNoErrors();
    $this->post(route('invoices.void', $invoice), $data)->assertSessionHasNoErrors();
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()])->assertSessionHasErrors('status');
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '100'))->assertSessionHasErrors('status');
    $this->patch(route('print-orders.status', $order), $cancel)->assertSessionHasNoErrors();
    $this->get(route('invoices.show', $invoice))->assertInertia(fn (Assert $page) => $page->where('invoice.status', 'void')->where('invoice.summary.collectible', '0.00')->where('invoice.total', '82000.00'));
    $this->assertDatabaseCount('invoices', 1);
});
test('invoice cannot issue for draft cancelled or corrupt SPK', function (string $state): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    inCashier($tenant, function () use ($order, $state): void {
        if ($state === 'corrupt') {
            DB::table('print_orders')->where('id', $order->id)->update(['total' => '1']);
        } else {
            DB::table('print_orders')->where('id', $order->id)->update(['status' => $state]);
        }
    });
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()])->assertSessionHasErrors($state === 'corrupt' ? 'total' : 'status');
    $this->assertDatabaseCount('invoices', 0);
})->with(['draft', 'cancelled', 'corrupt']);
test('historical invoice can be read and printed after session restart and master change without HPP', function (): void {
    [$user,$tenant,$branch,$order,$product] = cashierFixture();
    $oldCustomer = $order->customer_snapshot['name'];
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    inCashier($tenant, function () use ($product, $order, $tenant): void {
        $product->update(['name' => 'Nama baru', 'is_active' => false]);
        $product->prices()->first()->update(['unit_price' => '9999', 'hpp' => '9876']);
        Customer::findOrFail($order->customer_id)->update(['name' => 'Pelanggan baru']);
        $tenant->update(['name' => 'Brand baru']);
    });
    $this->flushSession();
    $this->withSession(['tenant_id' => $tenant->id]);
    foreach (['invoices.show', 'invoices.print'] as $route) {
        $response = $this->get(route($route, [$invoice, 'format' => 'thermal']));
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page->where('invoice.total', '82000.00')->where('invoice.items_snapshot.0.snapshot.product_name', 'HVS 100 gr')->where('invoice.items_snapshot.0.snapshot.subtotal', '82000.00')->where('invoice.customer_snapshot.name', $oldCustomer)->where('invoice.company_snapshot.name', 'Percetakan Uji')->missing('invoice.items_snapshot.0.cost_snapshot')->missing('invoice.items_snapshot.0.snapshot.hpp'));
        expect($response->getContent())->not->toContain('2173.00', '9876.00', 'cost_snapshot');
    }
});
test('cashier and read only cannot perform sensitive corrections or method setup', function (string $role): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '20'));
    $payment = inCashier($tenant, fn () => InvoicePayment::first());
    inCashier($tenant, fn () => $user->syncRoles([$role]));
    $user->unsetRelation('roles')->unsetRelation('permissions');
    $this->get(route('invoices.show', $invoice))->assertOk();
    $this->post(route('invoice-payments.reverse', [$invoice, $payment]), ['request_key' => (string) Str::uuid(), 'reason' => 'Salah', 'record_was_incorrect' => true])->assertForbidden();
    $this->post(route('invoices.void', $invoice), ['request_key' => (string) Str::uuid(), 'reason' => 'Batal'])->assertForbidden();
    $this->get(route('payment-methods.index'))->assertForbidden();
    if ($role === 'Read Only') {
        $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '20'))->assertForbidden();
        $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()])->assertForbidden();
    }
    $this->assertDatabaseCount('invoice_payments', 1);
})->with(['Kasir', 'Read Only']);
test('cross tenant invoice methods and source SPK direct URLs return 404', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    [$other,$otherTenant,$otherBranch,$otherOrder] = cashierFixture();
    $this->actingAs($other)->withSession(['tenant_id' => $otherTenant->id]);
    $this->post(route('invoices.store', $otherOrder), ['request_key' => (string) Str::uuid()]);
    $otherInvoice = cashierInvoice($otherTenant);
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    foreach (['invoices.show', 'invoices.print'] as $route) {
        $this->get(route($route, $otherInvoice))->assertNotFound();
    }
    $this->post(route('invoices.store', $otherOrder), ['request_key' => (string) Str::uuid()])->assertNotFound();
    $this->post(route('invoice-payments.store', $otherInvoice), cashierPayment($tenant, '20'))->assertNotFound();
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($otherTenant, '20'))->assertNotFound();
    $this->get(route('invoices.index', ['view' => 'invoices']))->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.id', $invoice->id));
});
test('explicit branch assignment restricts cashier read print write and listing', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    inCashier($tenant, function () use ($user, $tenant): void {
        $user->syncRoles(['Kasir']);
        $other = Branch::factory()->create();
        DB::table('branch_user')->where('user_id', $user->id)->delete();
        DB::table('branch_user')->insert(['tenant_id' => $tenant->id, 'branch_id' => $other->id, 'user_id' => $user->id]);
    });
    $user->unsetRelation('roles')->unsetRelation('permissions');
    $this->get(route('invoices.show', $invoice))->assertNotFound();
    $this->get(route('invoices.print', $invoice))->assertNotFound();
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '20'))->assertNotFound();
    $this->get(route('invoices.index', ['view' => 'invoices']))->assertInertia(fn (Assert $page) => $page->has('records.data', 0));
});
test('revoked access rejects both historical reads and retry writes', function (string $revoke): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $input = cashierPayment($tenant, '20');
    $this->post(route('invoice-payments.store', $invoice), $input)->assertSessionHasNoErrors();
    inCashier($tenant, function () use ($user, $tenant, $revoke): void {
        match ($revoke) {
            'license' => License::first()->update(['status' => 'suspended']),
            'grant' => TenantModuleEntitlement::whereHas('module', fn ($q) => $q->where('code', 'POS'))->update(['is_granted' => false]),
            'membership' => $tenant->users()->updateExistingPivot($user->id, ['is_active' => false]),
            'role' => $user->syncRoles(['Operator Produksi']),
            'branch' => DB::table('branch_user')->where('user_id', $user->id)->delete(),
        };
    });
    $user->unsetRelation('roles')->unsetRelation('permissions');
    $response = $this->get(route('invoices.show', $invoice));
    expect(in_array($response->status(), [403, 404], true))->toBeTrue();
    $response = $this->withSession(['tenant_id' => $tenant->id])->post(route('invoice-payments.store', $invoice), $input);
    expect(in_array($response->status(), [403, 404], true))->toBeTrue();
    $this->assertDatabaseCount('invoice_payments', 1);
})->with(['license', 'grant', 'membership', 'role', 'branch']);
test('tenant payment accounts preserve evidence after deactivation and cannot be used inactive', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('payment-methods.store'), ['kind' => 'transfer', 'name' => 'Bank Contoh', 'details' => 'Rekening 1234'])->assertSessionHasNoErrors();
    $method = inCashier($tenant, fn () => PaymentMethod::where('name', 'Bank Contoh')->firstOrFail());
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $data = cashierPayment($tenant, '20000', 'transfer');
    $data['payments'][0]['payment_method_id'] = $method->id;
    $this->post(route('invoice-payments.store', $invoice), $data)->assertSessionHasNoErrors();
    $this->patch(route('payment-methods.update', $method), ['is_active' => false])->assertSessionHasNoErrors();
    $this->post(route('invoice-payments.store', $invoice), [...$data, 'request_key' => (string) Str::uuid()])->assertNotFound();
    $this->post(route('invoice-payments.store', $invoice), $data)->assertSessionHasNoErrors();
    $this->get(route('invoices.print', $invoice))->assertInertia(fn (Assert $page) => $page->where('invoice.payments.0.method_snapshot.name', 'Bank Contoh')->where('invoice.payments.0.method_snapshot.details', 'Rekening 1234'));
});
test('non cash reference future time and invalid method are rejected', function (string $invalid): void {
    $this->freezeTime();
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $data = cashierPayment($tenant, '200', 'transfer');
    $field = 'payments.0.reference';
    if ($invalid === 'reference') {
        $data['payments'][0]['reference'] = '';
    } elseif ($invalid === 'future') {
        $data['payments'][0]['paid_at'] = now($tenant->timezone)->addDay()->format('Y-m-d\TH:i');
        $field = 'payments.0.paid_at';
    } elseif ($invalid === 'date') {
        $data['payments'][0]['paid_at'] = 'tomorrow';
        $field = 'payments.0.paid_at';
    } else {
        $data['payments'][0]['payment_method_id'] = 999999;
    }
    $response = $this->post(route('invoice-payments.store', $invoice), $data);
    if ($invalid === 'method') {
        $response->assertNotFound();
    } else {
        $response->assertSessionHasErrors($field);
    }
    $this->assertDatabaseCount('invoice_payments', 0);
})->with(['reference', 'future', 'date', 'method']);
test('piutang filters derived status and authenticated listing never leaks HPP', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->get(route('invoices.index'))->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $this->get(route('invoices.index'))->assertInertia(fn (Assert $page) => $page->has('records.data', 0));
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '20000'));
    $this->get(route('invoices.index', ['view' => 'receivables', 'search' => $order->number, 'status' => 'partially_paid']))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('records.data.0.summary.balance', '62000.00')->missing('records.data.0.items_snapshot')->missing('records.data.0.payments'));
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '62000'));
    $this->get(route('invoices.index', ['view' => 'receivables']))->assertInertia(fn (Assert $page) => $page->has('records.data', 0));
});
test('invoice and ledger snapshots reject mutation and deletion', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '20'));
    $entry = inCashier($tenant, fn () => InvoicePayment::first());
    expect(fn () => inCashier($tenant, fn () => $invoice->update(['total' => '1'])))->toThrow(LogicException::class);
    expect(fn () => inCashier($tenant, fn () => $invoice->delete()))->toThrow(LogicException::class);
    expect(fn () => inCashier($tenant, fn () => $entry->update(['amount' => '1'])))->toThrow(LogicException::class);
    expect(fn () => inCashier($tenant, fn () => $entry->delete()))->toThrow(LogicException::class);
});
test('browser supplied amount status tenant HPP and number cannot override invoice', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid(), 'total' => '1', 'paid_amount' => '82000', 'payment_status' => 'paid', 'tenant_id' => 999, 'number' => 'FORGED', 'items_snapshot' => [['hpp' => 999]]])->assertSessionHasNoErrors();
    $invoice = cashierInvoice($tenant);
    expect($invoice->total)->toBe('82000.00');
    expect($invoice->payment_status)->toBe('unpaid');
    expect($invoice->number)->toStartWith('INV-'.$branch->id.'-');
});
test('cashier missing schema returns controlled 503 before binding', function (): void {
    [$user,$tenant] = cashierFixture();
    Schema::drop('cashier_requests');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('invoices.show', 999999))->assertStatus(503);
});
test('unauthenticated cashier redirects to login', function (): void {
    $this->get(route('invoices.index'))->assertRedirect(route('login'));
});

test('large exact money at schema limit preserves cents without float conversion', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $invoice = inCashier($tenant, fn () => Invoice::factory()->create(['print_order_id' => $order->id, 'branch_id' => $branch->id, 'total' => '9999999999999.99', 'balance' => '9999999999999.99']));
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '9999999999999.98'))->assertSessionHasNoErrors();
    $this->get(route('invoices.show', $invoice))->assertInertia(fn (Assert $page) => $page->where('invoice.summary.balance', '0.01')->where('invoice.payments.0.amount', '9999999999999.98'));
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '0.01'))->assertSessionHasNoErrors();
    expect(inCashier($tenant, fn () => $invoice->refresh()->summary()['paid']))->toBe('9999999999999.99');
});
test('cashier permission backfill is idempotent preserves assignments and does not grant custom roles', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Kasir');
    $custom = inCashier($tenant, fn () => Role::create(['name' => 'Petugas Kustom', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));
    $before = DB::table('model_has_roles')->where('model_id', $user->id)->get()->toArray();
    $license = inCashier($tenant, fn () => License::first()->getAttributes());
    DB::table('permissions')->whereIn('name', ['invoice.issue', 'payment.record', 'payment.reverse', 'invoice.void', 'payment-methods.manage'])->delete();
    $migration = require collect(glob(database_path('migrations/*grant_cashier_permissions.php')))->first();
    $migration->up();
    $migration->up();
    $user->unsetRelation('roles')->unsetRelation('permissions');
    expect(inCashier($tenant, fn () => $user->can('invoice.issue')))->toBeTrue();
    expect(inCashier($tenant, fn () => $user->can('payment.reverse')))->toBeFalse();
    expect($custom->permissions()->count())->toBe(0);
    expect(DB::table('model_has_roles')->where('model_id', $user->id)->get()->toArray())->toEqual($before);
    expect(inCashier($tenant, fn () => License::first()->getAttributes()))->toBe($license);
});
test('cashier schema upgrade preserves existing SPK catalog assignments and sequence', function (): void {
    [$user,$tenant,$branch,$order,$product] = cashierFixture();
    $before = $order->getAttributes();
    $assignment = DB::table('branch_user')->get()->toArray();
    $migration = require collect(glob(database_path('migrations/*create_cashier_tables.php')))->first();
    $migration->down();
    $migration->up();
    expect(inCashier($tenant, fn () => $order->refresh()->getAttributes()))->toBe($before);
    expect(DB::table('branch_user')->get()->toArray())->toEqual($assignment);
    expect(inCashier($tenant, fn () => Product::findOrFail($product->id)->prices()->first()->unit_price))->toBe('4100.00');
    expect(inCashier($tenant, fn () => Branch::find($branch->id)->spk_sequence))->toBe(1);
    expect(inCashier($tenant, fn () => PaymentMethod::count()))->toBe(4);
});
test('constraints reject cross branch source and payment references and duplicate invoice', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $other = inCashier($tenant, fn () => Branch::factory()->create());
    expect(fn () => inCashier($tenant, fn () => Invoice::factory()->create(['branch_id' => $other->id, 'print_order_id' => $order->id])))->toThrow(QueryException::class);
    expect(fn () => inCashier($tenant, fn () => Invoice::factory()->create(['branch_id' => $branch->id, 'print_order_id' => $order->id])))->toThrow(QueryException::class);
    expect(fn () => inCashier($tenant, fn () => InvoicePayment::factory()->create(['branch_id' => $other->id, 'invoice_id' => $invoice->id])))->toThrow(QueryException::class);
});
test('wrong invoice payment references and print receipt are rejected with 404', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $other = inCashier($tenant, fn () => Invoice::factory()->create());
    $entry = inCashier($tenant, fn () => InvoicePayment::factory()->create(['invoice_id' => $other->id, 'branch_id' => $other->branch_id]));
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $this->post(route('invoice-payments.reverse', [$invoice, $entry]), ['request_key' => (string) Str::uuid(), 'reason' => 'Salah', 'record_was_incorrect' => true])->assertNotFound();
    $this->get(route('invoices.print', [$invoice, 'receipt' => $entry->id]))->assertNotFound();
});

test('two approved TGM sample items total 142000 and issue ignores changed master before invoicing', function (): void {
    [$user,$tenant,$branch,$order,$product] = cashierFixture('Owner');
    $order = inCashier($tenant, function () use ($user, $order, $product): PrintOrder {
        $lanyard = Product::factory()->create(['name' => 'Lanyard', 'unit_name' => 'Pcs']);
        ProductPrice::factory()->for($lanyard)->create(['variant' => '2 cm', 'finishing' => 'Stopper', 'qty_min' => '5', 'qty_max' => '5', 'unit_price' => '12000']);
        $draft = PrintOrder::factory()->create(['branch_id' => $order->branch_id, 'customer_id' => $order->customer_id]);
        $action = app(ManagePrintOrder::class);
        $draft = $action->item($user, $draft, ['request_key' => (string) Str::uuid(), 'version' => 1, 'product_id' => $product->id, 'qty' => '20', 'variant' => 'HVS 100 gr', 'print_side' => '2 sisi']);
        $draft = $action->item($user, $draft, ['request_key' => (string) Str::uuid(), 'version' => 2, 'product_id' => $lanyard->id, 'qty' => '5', 'variant' => '2 cm', 'finishing' => 'Stopper']);
        $draft = $action->transition($user, $draft, ['request_key' => (string) Str::uuid(), 'version' => 3, 'status' => 'confirmed']);
        $product->prices()->first()->update(['unit_price' => '99999']);
        $lanyard->prices()->first()->update(['unit_price' => '1']);

        return $draft;
    });
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    $invoice = cashierInvoice($tenant);
    $this->get(route('invoices.show', $invoice))->assertInertia(fn (Assert $page) => $page->where('invoice.total', '142000.00')->where('invoice.items_snapshot.0.snapshot.subtotal', '82000.00')->where('invoice.items_snapshot.1.snapshot.subtotal', '60000.00'));
});
test('cashier errors are not cached and Read Only can follow invoice from SPK', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $this->postJson(route('invoice-payments.store', $invoice), [])->assertUnprocessable()->assertHeader('Cache-Control', 'no-store, private');
    inCashier($tenant, fn () => $user->syncRoles(['Read Only']));
    $user->unsetRelation('roles')->unsetRelation('permissions');
    $this->get(route('print-orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('invoiceId', $invoice->id)->where('canIssue', false));
    $this->postJson(route('invoice-payments.store', $invoice), cashierPayment($tenant, '1'))->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');
});
test('retry uses resource and operation fingerprint rather than only request UUID', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $key = (string) Str::uuid();
    $this->post(route('invoices.store', $order), ['request_key' => $key]);
    $invoice = cashierInvoice($tenant);
    $this->post(route('invoice-payments.store', $invoice), [...cashierPayment($tenant, '1'), 'request_key' => $key])->assertSessionHasErrors('request_key');
    $this->post(route('invoices.void', $invoice), ['request_key' => $key, 'reason' => 'Batal'])->assertSessionHasErrors('request_key');
    $this->assertDatabaseCount('invoice_payments', 0);
    expect(inCashier($tenant, fn () => $invoice->refresh()->status))->toBe('issued');
});

test('payment batches require UUID and one to ten complete payment entries', function (string $invalid): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $input = cashierPayment($tenant, '1');
    $field = match ($invalid) {
        'uuid' => 'request_key','missing_time' => 'payments.0.paid_at','missing_method' => 'payments.0.payment_method_id','unexpected_key' => 'payments.0',default => 'payments'
    };
    if ($invalid === 'uuid') {
        $input['request_key'] = 'invalid';
    } elseif ($invalid === 'empty') {
        $input['payments'] = [];
    } elseif ($invalid === 'too_many') {
        $input['payments'] = array_fill(0, 11, $input['payments'][0]);
    } elseif ($invalid === 'missing_time') {
        unset($input['payments'][0]['paid_at']);
    } elseif ($invalid === 'missing_method') {
        unset($input['payments'][0]['payment_method_id']);
    } else {
        $input['payments'][0]['tenant_id'] = 999;
    }
    $this->post(route('invoice-payments.store', $invoice), $input)->assertSessionHasErrors($field);
    $this->assertDatabaseCount('invoice_payments', 0);
})->with(['uuid', 'empty', 'too_many', 'missing_time', 'missing_method', 'unexpected_key']);
test('void and correction require reasons and known tenant payment kinds', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Administrator');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()]);
    $invoice = cashierInvoice($tenant);
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '1'));
    $payment = inCashier($tenant, fn () => InvoicePayment::first());
    $this->post(route('invoice-payments.reverse', [$invoice, $payment]), ['request_key' => (string) Str::uuid(), 'reason' => '  ', 'record_was_incorrect' => true])->assertSessionHasErrors('reason');
    $this->post(route('invoices.void', $invoice), ['request_key' => (string) Str::uuid(), 'reason' => '  '])->assertSessionHasErrors('reason');
    $this->post(route('payment-methods.store'), ['kind' => 'gateway', 'name' => 'Salah'])->assertSessionHasErrors('kind');
    $this->post(route('invoice-payments.reverse', [$invoice, $payment]), ['request_key' => (string) Str::uuid(), 'reason' => 'Catatan salah', 'record_was_incorrect' => true])->assertSessionHasNoErrors();
    $this->post(route('invoices.void', $invoice), ['request_key' => (string) Str::uuid(), 'reason' => 'Tidak jadi tagih'])->assertSessionHasNoErrors();
    $this->assertDatabaseCount('invoice_payments', 2);
});
test('POS read access can be granted independently of SPK write permission', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    inCashier($tenant, function () use ($user, $tenant): void {
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Kasir Khusus', 'guard_name' => 'web']);
        $role->syncPermissions(['POS.view', 'invoice.issue', 'payment.record']);
        $user->syncRoles([$role]);
    });
    $user->unsetRelation('roles')->unsetRelation('permissions');
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('invoices.store', $order), ['request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
    $invoice = cashierInvoice($tenant);
    $this->post(route('invoice-payments.store', $invoice), cashierPayment($tenant, '10'))->assertSessionHasNoErrors();
    expect(inCashier($tenant, fn () => $user->can('spk.manage')))->toBeFalse();
});

test('cashier history requires HTTPS outside localhost and returns encryption protocol flag', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $path = '/transaksi/kasir';
    $this->get('http://erp-percetakan.test'.$path)->assertStatus(503)->assertHeader('Cache-Control', 'no-store, private');
    $this->get('https://erp-percetakan.test'.$path)->assertOk()->assertViewHas('page', fn (array $page): bool => ($page['encryptHistory'] ?? false) === true);
    $this->get('http://localhost'.$path)->assertOk()->assertViewHas('page', fn (array $page): bool => ($page['encryptHistory'] ?? false) === true);
});
test('tenant and branch switches clear history before another context is shown', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture('Owner');
    $otherTenant = app(CreateCompany::class)->handle($user, ['name' => 'Perusahaan Lain'])->refresh();
    inCashier($otherTenant, function (): void {
        License::firstOrFail()->update(['status' => 'active', 'activated_at' => now()]);
        TenantModuleEntitlement::whereHas('module', fn ($query) => $query->where('code', 'POS'))->firstOrFail()->update(['is_granted' => true]);
    });
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->post(route('companies.switch'), ['tenant_id' => $otherTenant->id])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk()->assertViewHas('page', fn (array $page): bool => ($page['clearHistory'] ?? false) === true);
    $otherBranch = inCashier($otherTenant, fn () => Branch::first());
    $this->post(route('branches.switch'), ['branch_id' => $otherBranch->id])->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertViewHas('page', fn (array $page): bool => ($page['clearHistory'] ?? false) === true);
    $this->get(route('invoices.create', $order))->assertNotFound();
});
test('logout invalidates session and clears invoice history on the next Inertia page', function (): void {
    [$user,$tenant,$branch,$order] = cashierFixture();
    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id]);
    $this->get(route('invoices.index'))->assertViewHas('page', fn (array $page): bool => ($page['encryptHistory'] ?? false) === true);
    $this->post(route('logout'))->assertRedirect('/')->assertHeader('Cache-Control', 'no-store, private');
    $this->assertGuest();
    $this->get(route('home'))->assertOk()->assertViewHas('page', fn (array $page): bool => ($page['clearHistory'] ?? false) === true);
    $this->get(route('invoices.index'))->assertRedirect(route('login'));
});
