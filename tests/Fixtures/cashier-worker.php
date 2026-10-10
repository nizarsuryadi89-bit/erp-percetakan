<?php

/** Independent subprocess fixture: only disposable SQLite paths are accepted. */
use App\Actions\CreateCompany;
use App\Actions\ManageInvoice;
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
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

$database = $argv[1] ?? '';
if (! is_file($database) || realpath(dirname($database)) !== realpath(sys_get_temp_dir()) || ! preg_match('/^erp-cashier-concurrency-[A-Za-z0-9]+$/', basename($database))) {
    throw new RuntimeException('Only the concurrency test temporary SQLite file is allowed.');
}
putenv('APP_ENV=testing');
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null, 'database.connections.sqlite.database' => $database, 'database.connections.sqlite.busy_timeout' => 10000, 'cache.default' => 'array', 'session.driver' => 'array', 'mail.default' => 'log']);
if (! app()->environment('testing')) {
    throw new RuntimeException('Concurrency workers require testing.');
}
$mode = $argv[2];
if ($mode === 'prepare') {
    Artisan::call('migrate', ['--no-interaction' => true]);
    $user = User::factory()->create();
    $tenant = app(CreateCompany::class)->handle($user, ['name' => 'Concurrency fixture'])->refresh();
} else {
    $payload = json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR);
    $tenant = Tenant::findOrFail($payload['tenant']);
    $user = User::findOrFail($payload['user']);
}
app(TenantContext::class)->set($tenant);
app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
if ($mode === 'prepare') {
    License::firstOrFail()->update(['status' => 'active', 'activated_at' => now()]);
    TenantModuleEntitlement::whereHas('module', fn ($q) => $q->where('code', 'POS'))->firstOrFail()->update(['is_granted' => true]);
    $branch = Branch::firstOrFail();
    $customer = Customer::factory()->create(['branch_id' => $branch->id]);
    $product = Product::factory()->create();
    ProductPrice::factory()->for($product)->create(['unit_price' => '4100']);

    $cashier = User::factory()->create();
    $tenant->users()->attach($cashier, ['is_active' => true]);
    $cashier->assignRole('Kasir');
    DB::table('branch_user')->insert(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'user_id' => $cashier->id]);
    $orders = app(ManagePrintOrder::class);
    $order = $orders->create($user, ['request_key' => (string) Str::uuid(), 'branch_id' => $branch->id, 'customer_id' => $customer->id, 'method' => 'walk_in']);
    $order = $orders->item($user, $order, ['request_key' => (string) Str::uuid(), 'version' => 1, 'product_id' => $product->id, 'qty' => '20']);
    $order = $orders->transition($user, $order, ['request_key' => (string) Str::uuid(), 'version' => 2, 'status' => 'confirmed']);
    echo json_encode(['tenant' => $tenant->id, 'user' => $user->id, 'cashier' => $cashier->id, 'branch' => $branch->id, 'order' => $order->id, 'method' => PaymentMethod::where('kind', 'cash')->first()->id, 'paid_at' => now($tenant->timezone)->subMinute()->format('Y-m-d\TH:i')], JSON_THROW_ON_ERROR);
} else {
    try {
        $action = app(ManageInvoice::class);
        if ($mode === 'issue') {
            $invoice = $action->issue($user, PrintOrder::findOrFail($payload['order']), ['request_key' => $payload['key']]);
        } elseif ($mode === 'pay') {
            $invoice = $action->pay($user, Invoice::findOrFail($payload['invoice']), ['request_key' => $payload['key'], 'payments' => [['payment_method_id' => $payload['method'], 'amount' => $payload['amount'], 'paid_at' => $payload['paid_at'], 'reference' => null, 'notes' => null]]]);
        } elseif ($mode === 'reverse') {
            $invoice = $action->reverse($user, Invoice::findOrFail($payload['invoice']), InvoicePayment::findOrFail($payload['payment']), ['request_key' => $payload['key'], 'reason' => 'Catatan fixture salah', 'record_was_incorrect' => true]);
        } elseif ($mode === 'cancel') {
            $order = app(ManagePrintOrder::class)->transition($user, PrintOrder::findOrFail($payload['order']), ['request_key' => $payload['key'], 'version' => 3, 'status' => 'cancelled', 'reason' => 'Fixture batal']);
            echo json_encode(['order' => $order->id, 'status' => $order->status], JSON_THROW_ON_ERROR);
            exit;
        }
        echo json_encode(['invoice' => $invoice->id, 'number' => $invoice->number, 'summary' => $invoice->summary()], JSON_THROW_ON_ERROR);
    } catch (ValidationException $exception) {
        echo json_encode(['rejected' => true, 'errors' => array_keys($exception->errors())], JSON_THROW_ON_ERROR);
    }
}
