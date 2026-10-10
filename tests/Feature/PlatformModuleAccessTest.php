<?php

use App\Actions\CreateCompany;
use App\Models\License;
use App\Models\Module;
use App\Models\PlatformAdministrator;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\TenantModuleEntitlement;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function platformTenantContext(Tenant $tenant, Closure $callback): mixed
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

/** @return array{0: User, 1: Tenant} */
function platformCompany(): array
{
    $owner = User::factory()->create();
    $tenant = app(CreateCompany::class)->handle($owner, ['name' => 'Perusahaan Modul']);

    return [$owner, $tenant];
}

function platformDeveloper(): User
{
    $user = User::factory()->create();
    PlatformAdministrator::factory()->for($user)->create();

    return $user;
}

function platformPosAccess(Tenant $tenant): void
{
    platformTenantContext($tenant, function (): void {
        License::firstOrFail()->update(['status' => 'active', 'activated_at' => now()]);
        TenantModuleEntitlement::whereHas('module', fn ($query) => $query->where('code', 'POS'))->firstOrFail()->update(['is_granted' => true]);
    });
}

test('fresh onboarding provisions roles and eight denied grants without legacy toggles', function (): void {
    [$owner, $tenant] = platformCompany();
    $this->assertDatabaseCount('modules', 8);
    $this->assertDatabaseCount('tenant_modules', 0);
    $this->assertDatabaseCount('platform_administrators', 0);
    $this->assertDatabaseCount('tenant_module_entitlements', 8);
    $this->assertDatabaseMissing('tenant_module_entitlements', ['tenant_id' => $tenant->id, 'is_granted' => true]);
    platformTenantContext($tenant, fn () => expect($owner->can('POS.view'))->toBeTrue());
});

test('active license without a developer grant denies direct aliases and removes navigation', function (): void {
    [$owner, $tenant] = platformCompany();
    platformTenantContext($tenant, fn () => License::firstOrFail()->update(['status' => 'active', 'activated_at' => now()]));
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->get(route('transaksi.index'))->assertForbidden();
    $this->get(route('module-workspace.show', ['module' => 'POS']))->assertForbidden();
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page->has('workspace.navigation', 1));
});

test('developer renders both platform pages and preserves tenant membership boundaries', function (): void {
    [$owner, $tenant] = platformCompany();
    $developer = platformDeveloper();
    $this->actingAs($developer)->get(route('platform.index'))->assertOk()->assertInertia(fn ($page) => $page->component('platform/index')->has('companies.data', 1)->has('workspace.platform', 1));
    $this->get(route('platform.companies.show', $tenant->uuid))->assertOk()->assertInertia(fn ($page) => $page->component('platform/company-modules')->has('modules', 8)->has('entitlements', 8));
    $this->withSession(['tenant_id' => $tenant->id])->get(route('products.index'))->assertForbidden();
    expect($developer->tenants()->count())->toBe(0);
});

test('tenant roles cannot read or assign platform grants', function (string $role): void {
    [$owner, $tenant] = platformCompany();
    $staff = User::factory()->create();
    $tenant->users()->attach($staff, ['is_active' => true]);
    platformTenantContext($tenant, fn () => $staff->assignRole($role));
    $this->actingAs($staff)->withSession(['tenant_id' => $tenant->id]);
    $this->get(route('platform.index'))->assertForbidden();
    $this->get(route('platform.companies.show', $tenant->uuid))->assertForbidden();
    $this->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => ['POS'], 'access_status' => 'active'])->assertForbidden();
    $this->assertDatabaseMissing('tenant_module_entitlements', ['tenant_id' => $tenant->id, 'is_granted' => true]);
})->with(['Owner', 'Administrator', 'Kasir', 'Operator Produksi', 'Keuangan', 'Read Only']);

test('a forged tenant permission cannot override global platform authorization', function (): void {
    [$owner, $tenant] = platformCompany();
    platformTenantContext($tenant, function () use ($owner): void {
        $permission = Permission::findOrCreate('platform.manage', 'web');
        $owner->givePermissionTo($permission);
        expect($owner->can('platform.manage'))->toBeFalse();
    });
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => ['POS']])->assertForbidden();
    $this->assertDatabaseCount('platform_administrators', 0);
});

test('developer grants and revokes POS without deleting catalog data', function (): void {
    [$owner, $tenant] = platformCompany();
    $developer = platformDeveloper();
    platformTenantContext($tenant, fn () => Product::factory()->create());
    $this->actingAs($developer)->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => ['POS'], 'access_status' => 'active'])->assertSessionHasNoErrors();
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('products.index'))->assertOk();
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('workspace.navigation', 2)->where('workspace.navigation.1.code', 'POS'));
    $this->get(route('module-workspace.show', ['module' => 'PRODUCTION']))->assertForbidden();
    $this->actingAs($developer)->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => []])->assertSessionHasNoErrors();
    $this->actingAs($owner)->get(route('products.index'))->assertForbidden();
    $this->assertDatabaseCount('products', 1);
    $this->assertDatabaseCount('tenant_module_entitlements', 8);
    $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'actor_id' => $developer->id, 'action' => 'platform.modul_ditetapkan']);
});

test('canonical and legacy URLs use the same entitlement while retired toggles have no authority', function (): void {
    [$owner, $tenant] = platformCompany();
    platformPosAccess($tenant);
    platformTenantContext($tenant, fn () => TenantModule::factory()->create(['code' => 'transaksi', 'is_enabled' => false]));
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->get(route('transaksi.index'))->assertOk()->assertInertia(fn ($page) => $page->where('code', 'POS'));
    $this->get(route('module-workspace.show', ['module' => 'POS']))->assertOk()->assertInertia(fn ($page) => $page->where('code', 'POS'));
    $this->patch(route('modules.update', 'transaksi'), ['is_enabled' => false])->assertSessionHasNoErrors();
    $this->get(route('transaksi.index'))->assertForbidden();
    $this->get(route('products.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('workspace.navigation', 1));
});

test('tenants cannot grant themselves access or change license status through module controls', function (): void {
    [$owner, $tenant] = platformCompany();
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->patch(route('modules.update', 'POS'), ['is_enabled' => true, 'is_granted' => true, 'access_status' => 'active'])->assertForbidden();
    $this->assertDatabaseHas('licenses', ['tenant_id' => $tenant->id, 'status' => 'pending']);
    $this->assertDatabaseMissing('tenant_module_entitlements', ['tenant_id' => $tenant->id, 'is_granted' => true]);
});

test('expiry future starts and inactive modules deny endpoints and navigation consistently', function (string $state): void {
    $this->freezeTime();
    [$owner, $tenant] = platformCompany();
    platformPosAccess($tenant);
    platformTenantContext($tenant, function () use ($state): void {
        $entitlement = TenantModuleEntitlement::whereHas('module', fn ($query) => $query->where('code', 'POS'))->firstOrFail();
        match ($state) {
            'entitlement-expired' => $entitlement->update(['expires_at' => now()]),
            'entitlement-future' => $entitlement->update(['starts_at' => now()->addDay()]),
            'disabled' => $entitlement->update(['is_enabled' => false]),
            'ungranted' => $entitlement->update(['is_granted' => false]),
            'module-inactive' => Module::where('code', 'POS')->update(['is_active' => false]),
            'license-expired' => License::firstOrFail()->update(['expires_at' => now()]),
        };
    });
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->get(route('products.index'))->assertForbidden();
    $this->get(route('transaksi.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('workspace.navigation', 1));
})->with(['entitlement-expired', 'entitlement-future', 'disabled', 'ungranted', 'module-inactive', 'license-expired']);

test('the developer can set an individual module expiry in the tenant timezone', function (): void {
    $this->travelTo(Carbon::parse('2026-10-10 10:00:00', 'Asia/Jakarta'));
    [$owner, $tenant] = platformCompany();
    $developer = platformDeveloper();
    $this->actingAs($developer)->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => ['POS'], 'access_status' => 'active', 'module_expiries' => ['POS' => '2026-10-10']])->assertSessionHasNoErrors();
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('products.index'))->assertOk();
    $this->travelTo(Carbon::parse('2026-10-11 00:00:00', 'Asia/Jakarta'));
    $this->get(route('products.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('workspace.navigation', 1));
});

test('invalid inactive duplicate and dependency-incomplete grants roll back', function (array $codes): void {
    [$owner, $tenant] = platformCompany();
    $developer = platformDeveloper();
    $this->actingAs($developer)->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => $codes])->assertSessionHasErrors('module_codes');
    $this->assertDatabaseMissing('tenant_module_entitlements', ['tenant_id' => $tenant->id, 'is_granted' => true]);
    $this->assertDatabaseMissing('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'platform.modul_ditetapkan']);
})->with([
    'missing POS' => [['PRODUCTION']],
    'analytics missing manufacturing' => [['POS', 'FINANCE', 'ANALYTICS']],
]);

test('unknown duplicated and inactive registry codes are rejected', function (string $state): void {
    [$owner, $tenant] = platformCompany();
    $developer = platformDeveloper();
    if ($state === 'inactive') {
        Module::where('code', 'POS')->update(['is_active' => false]);
    }
    $codes = match ($state) {
        'unknown' => ['UNKNOWN'], 'duplicate' => ['POS', 'POS'], 'inactive' => ['POS']
    };
    $this->actingAs($developer)->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => $codes])->assertSessionHasErrors();
    $this->assertDatabaseMissing('tenant_module_entitlements', ['tenant_id' => $tenant->id, 'is_granted' => true]);
})->with(['unknown', 'duplicate', 'inactive']);

test('cycles cannot grant access or appear in navigation', function (): void {
    [$owner, $tenant] = platformCompany();
    platformPosAccess($tenant);
    $pos = Module::where('code', 'POS')->firstOrFail();
    $production = Module::where('code', 'PRODUCTION')->firstOrFail();
    $pos->dependencies()->attach($production);
    $this->actingAs(platformDeveloper())->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => ['POS', 'PRODUCTION']])->assertSessionHasErrors('module_codes');
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('transaksi.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('workspace.navigation', 1));
});

test('grants and module updates in one tenant never affect another', function (): void {
    [$firstOwner, $first] = platformCompany();
    [$secondOwner, $second] = platformCompany();
    platformPosAccess($first);
    $this->actingAs($secondOwner)->withSession(['tenant_id' => $second->id])->patch(route('modules.update', 'POS'), ['is_enabled' => true])->assertForbidden();
    $this->actingAs($firstOwner)->withSession(['tenant_id' => $first->id])->get(route('products.index'))->assertOk();
    $this->actingAs($secondOwner)->withSession(['tenant_id' => $second->id])->get(route('products.index'))->assertForbidden();
});

test('terminal grants and revocations are audited and never confer tenant membership', function (): void {
    $developer = User::factory()->create();
    $this->artisan('erp:platform-access', ['email' => $developer->email])->assertSuccessful();
    expect($developer->isPlatformAdministrator())->toBeTrue();
    $this->assertDatabaseHas('platform_audit_logs', ['target_user_id' => $developer->id, 'action' => 'platform.akses_diberikan']);
    $this->artisan('erp:platform-access', ['email' => $developer->email, '--revoke' => true])->assertSuccessful();
    expect($developer->isPlatformAdministrator())->toBeFalse();
    $this->assertDatabaseHas('platform_audit_logs', ['target_user_id' => $developer->id, 'action' => 'platform.akses_dicabut']);
    expect($developer->tenants()->count())->toBe(0);
});

test('unverified accounts cannot become platform administrators', function (): void {
    $user = User::factory()->unverified()->create();
    $this->artisan('erp:platform-access', ['email' => $user->email])->assertFailed();
    $this->assertDatabaseCount('platform_administrators', 0);
});

test('revoked platform administrators lose both the page and its navigation entry', function (): void {
    [$owner, $tenant] = platformCompany();
    PlatformAdministrator::factory()->for($owner, 'user')->create(['is_active' => false]);
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id]);
    $this->get(route('platform.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('workspace.platform', 0));
});

test('tenant role permission limits granted modules in both navigation and HTTP', function (): void {
    [$owner, $tenant] = platformCompany();
    $cashier = User::factory()->create();
    $tenant->users()->attach($cashier, ['is_active' => true]);
    platformTenantContext($tenant, fn () => $cashier->assignRole('Kasir'));
    $this->actingAs(platformDeveloper())->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => ['POS', 'FINANCE'], 'access_status' => 'active'])->assertSessionHasNoErrors();
    $this->actingAs($cashier)->withSession(['tenant_id' => $tenant->id]);
    $this->get(route('products.index'))->assertOk();
    $this->get(route('keuangan.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('workspace.navigation', 2)->where('workspace.navigation.1.code', 'POS'));
});

test('regranting a module preserves the tenant hidden flag and its existing expiry', function (): void {
    $this->freezeTime();
    [$owner, $tenant] = platformCompany();
    $expiry = now()->addDay();
    platformTenantContext($tenant, fn () => TenantModuleEntitlement::whereHas('module', fn ($query) => $query->where('code', 'POS'))->firstOrFail()->update(['is_enabled' => false, 'expires_at' => $expiry]));
    $this->actingAs(platformDeveloper())->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => ['POS'], 'access_status' => 'active'])->assertSessionHasNoErrors();
    $this->assertDatabaseHas('tenant_module_entitlements', ['tenant_id' => $tenant->id, 'module_id' => Module::where('code', 'POS')->value('id'), 'is_granted' => true, 'is_enabled' => false, 'expires_at' => $expiry->toDateTimeString()]);
    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->get(route('products.index'))->assertForbidden();
    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('workspace.navigation', 1));
});

test('invalid module expiry input leaves grants and license unchanged', function (array $expiries, string $error): void {
    [$owner, $tenant] = platformCompany();
    $this->actingAs(platformDeveloper())->patch(route('platform.companies.update', $tenant->uuid), ['module_codes' => ['POS'], 'access_status' => 'active', 'module_expiries' => $expiries])->assertSessionHasErrors($error);
    $this->assertDatabaseMissing('tenant_module_entitlements', ['tenant_id' => $tenant->id, 'is_granted' => true]);
    $this->assertDatabaseHas('licenses', ['tenant_id' => $tenant->id, 'status' => 'pending']);
    $this->assertDatabaseMissing('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'platform.modul_ditetapkan']);
})->with([
    'unselected module' => [['FINANCE' => '2026-10-11'], 'module_expiries'],
    'invalid date' => [['POS' => '2026-02-30'], 'module_expiries.POS'],
]);

test('recovery is repeatable preserves grants and clears tenant and permission context', function (): void {
    [$owner, $tenant] = platformCompany();
    platformPosAccess($tenant);
    platformTenantContext($tenant, fn () => TenantModuleEntitlement::whereHas('module', fn ($query) => $query->where('code', 'POS'))->firstOrFail()->update(['is_enabled' => false, 'expires_at' => now()->addDay()]));
    $this->artisan('erp:recover-access', ['tenant' => $tenant->uuid, '--dry-run' => true])->assertSuccessful();
    $this->assertDatabaseMissing('audit_logs', ['action' => 'akses.dipulihkan']);
    $this->artisan('erp:recover-access', ['tenant' => $tenant->uuid])->assertSuccessful();
    $this->artisan('erp:recover-access', ['tenant' => $tenant->uuid])->assertSuccessful();
    $this->assertDatabaseCount('roles', 6);
    $this->assertDatabaseCount('tenant_module_entitlements', 8);
    $this->assertDatabaseHas('tenant_module_entitlements', ['tenant_id' => $tenant->id, 'module_id' => Module::where('code', 'POS')->value('id'), 'is_granted' => true, 'is_enabled' => false]);
    expect(app(TenantContext::class)->id())->toBeNull();
    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});
