<?php

/** Independent subprocess fixture: only disposable SQLite paths are accepted. */
use App\Actions\CreateCompany;
use App\Actions\ManagePrintOrder;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\License;
use App\Models\PrintOrder;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Tenant;
use App\Models\TenantModuleEntitlement;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

$database = $argv[1] ?? '';
if (! is_file($database) || realpath(dirname($database)) !== realpath(sys_get_temp_dir()) || ! preg_match('/^erp-spk-concurrency-[A-Za-z0-9]+$/', basename($database))) {
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
    echo json_encode(['tenant' => $tenant->id, 'user' => $user->id, 'branch' => $branch->id, 'customer' => $customer->id, 'product' => $product->id], JSON_THROW_ON_ERROR);
} elseif ($mode === 'order') {
    $order = app(ManagePrintOrder::class)->create($user, ['request_key' => $payload['key'], 'branch_id' => $payload['branch'], 'customer_id' => $payload['customer'], 'method' => 'walk_in']);
    echo json_encode(['id' => $order->id, 'number' => $order->number], JSON_THROW_ON_ERROR);
} elseif ($mode === 'item') {
    $order = app(ManagePrintOrder::class)->item($user, PrintOrder::findOrFail($payload['order']), ['request_key' => $payload['key'], 'version' => 1, 'product_id' => $payload['product'], 'qty' => '20']);
    echo json_encode(['id' => $order->id, 'version' => $order->version, 'total' => $order->total], JSON_THROW_ON_ERROR);
}
