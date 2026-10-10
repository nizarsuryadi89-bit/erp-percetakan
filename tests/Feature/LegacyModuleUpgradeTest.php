<?php

use App\Models\License;
use App\Models\Module;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

/** @return array{0: User, 1: Tenant} */
function oldModuleInstallation(array $disabled): array
{
    $migration = require database_path('migrations/2026_10_10_041453_create_platform_module_access_tables.php');
    $migration->down();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tenant->users()->attach($owner, ['is_active' => true]);
    $context = app(TenantContext::class);
    $context->set($tenant);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    try {
        foreach (['transaksi', 'bom', 'routing', 'produksi', 'keuangan', 'laba_rugi'] as $code) {
            TenantModule::factory()->create(['code' => $code, 'is_enabled' => ! in_array($code, $disabled, true)]);
        }
        License::factory()->create(['status' => 'active', 'activated_at' => now()]);
        $role = Role::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'guard_name' => 'web']);
        $permission = Permission::findOrCreate('transaksi.view', 'web');
        $role->givePermissionTo($permission);
        $owner->assignRole($role);
    } finally {
        $context->set(null);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        $owner->unsetRelation('roles')->unsetRelation('permissions');
    }

    return [$owner, $tenant];
}

test('legacy upgrade never grants disabled groups or unsupported dependencies', function (array $disabled, array $expected): void {
    [$owner, $tenant] = oldModuleInstallation($disabled);
    $licenseBefore = DB::table('licenses')->where('tenant_id', $tenant->id)->first();
    $legacyBefore = DB::table('tenant_modules')->where('tenant_id', $tenant->id)->orderBy('id')->get()->toJson();
    $migration = require database_path('migrations/2026_10_10_041453_create_platform_module_access_tables.php');
    $migration->up();
    $granted = DB::table('tenant_module_entitlements')->join('modules', 'module_id', '=', 'modules.id')->where('tenant_id', $tenant->id)->where('is_granted', true)->orderBy('sort_order')->pluck('code')->all();
    expect($granted)->toBe($expected);
    expect(DB::table('tenant_modules')->where('tenant_id', $tenant->id)->orderBy('id')->get()->toJson())->toBe($legacyBefore);
    expect(DB::table('licenses')->where('tenant_id', $tenant->id)->first())->toEqual($licenseBefore);
    $this->assertDatabaseCount('tenant_module_entitlements', 8);
    $this->assertDatabaseCount('platform_administrators', 0);
    $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'modul.legacy_dikonversi']);
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('workspace.navigation', count($expected) + 1));
    foreach (['POS' => 'transaksi.index', 'PRODUCTION' => 'produksi.index', 'FINANCE' => 'keuangan.index', 'ANALYTICS' => 'laba_rugi.index'] as $code => $route) {
        $response = $this->get(route($route));
        if (in_array($code, $expected, true)) {
            $response->assertOk();
        } else {
            $response->assertForbidden();
        }
    }
})->with([
    'all enabled' => [[], ['POS', 'PRODUCTION', 'FINANCE', 'ANALYTICS']],
    'BOM hidden' => [['bom'], ['POS', 'FINANCE']],
    'routing hidden' => [['routing'], ['POS', 'FINANCE']],
    'production hidden' => [['produksi'], ['POS', 'FINANCE']],
    'finance hidden' => [['keuangan'], ['POS', 'PRODUCTION']],
    'analytics hidden' => [['laba_rugi'], ['POS', 'PRODUCTION', 'FINANCE']],
    'POS hidden' => [['transaksi'], []],
    'all disabled' => [['transaksi', 'bom', 'routing', 'produksi', 'keuangan', 'laba_rugi'], []],
]);

test('pre-platform requests fail closed without querying missing module tables', function (): void {
    [$owner, $tenant] = oldModuleInstallation([]);
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('accessReady', false)->has('workspace.navigation', 1));
    $this->get(route('products.index'))->assertStatus(503);
    $this->get(route('transaksi.index'))->assertStatus(503);
    $this->get(route('module-workspace.show', ['module' => 'POS']))->assertStatus(503);
    $this->get(route('platform.index'))->assertForbidden();
    $this->post(route('companies.store'), ['name' => 'Cannot provision yet'])->assertStatus(503);
    $this->assertDatabaseCount('tenants', 1);
    expect(Schema::hasTable('modules'))->toBeFalse();
});

test('legacy canonical permissions are provisioned and recovery does not regrant hidden modules', function (): void {
    [$owner, $tenant] = oldModuleInstallation(['produksi']);
    $migration = require database_path('migrations/2026_10_10_041453_create_platform_module_access_tables.php');
    $migration->up();
    $this->artisan('erp:recover-access', ['tenant' => $tenant->uuid])->assertSuccessful();
    $this->artisan('erp:recover-access', ['tenant' => $tenant->uuid])->assertSuccessful();
    $this->assertDatabaseCount('roles', 6);
    $this->assertDatabaseCount('tenant_module_entitlements', 8);
    $this->assertDatabaseHas('tenant_module_entitlements', ['tenant_id' => $tenant->id, 'module_id' => Module::where('code', 'PRODUCTION')->value('id'), 'is_granted' => false, 'is_enabled' => false]);
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('products.index'))->assertOk();
    $this->get(route('produksi.index'))->assertForbidden();
    $this->get(route('platform.index'))->assertForbidden();
});
