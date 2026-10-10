<?php

use App\Actions\CreateCompany;
use App\Models\Branch;
use App\Models\License;
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->withoutVite();
});

function createTestCompany(User $owner, string $name = 'Percetakan Uji'): Tenant
{
    return app(CreateCompany::class)->handle($owner, ['name' => $name]);
}

function withinTestCompany(Tenant $tenant, Closure $action): mixed
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

function activateTestLicense(Tenant $tenant): void
{
    withinTestCompany($tenant, fn () => License::firstOrFail()->update(['status' => 'active', 'activated_at' => now()]));
}

test('guests are redirected to login before opening the dashboard', function (): void {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('registration creates a hashed user and sends email verification', function (): void {
    Notification::fake();

    $this->post('/register', ['name' => 'Pemilik', 'email' => 'pemilik@example.test', 'password' => 'Secure-password-123', 'password_confirmation' => 'Secure-password-123'])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'pemilik@example.test')->firstOrFail();
    $this->assertAuthenticatedAs($user);
    expect(Hash::check('Secure-password-123', $user->password))->toBeTrue();
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('users can log in using their existing account password', function (): void {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('wrong passwords do not authenticate users', function (): void {
    $user = User::factory()->create();

    $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login requests are throttled after repeated failures', function (): void {
    $user = User::factory()->create();
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertUnprocessable();
    }

    $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertTooManyRequests();
});

test('password recovery dispatches a reset notification', function (): void {
    $user = User::factory()->create();
    Notification::fake();

    $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('unverified users cannot create a company', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->post(route('companies.store'), ['name' => 'Toko'])->assertRedirect(route('verification.notice'));

    $this->assertDatabaseCount('tenants', 0);
});

test('users without a company are directed to company registration', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('companies.create'));
});

test('company registration creates its owner branch pending license and six modules', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('companies.store'), ['name' => 'Cetak Maju', 'phone' => '0812345678', 'address' => 'Bandung', 'is_active' => false, 'price' => 0, 'status' => 'active'])->assertRedirect(route('dashboard'));

    $tenant = Tenant::where('name', 'Cetak Maju')->firstOrFail();
    $this->assertDatabaseHas('tenant_user', ['tenant_id' => $tenant->id, 'user_id' => $user->id, 'is_active' => true]);
    $this->assertDatabaseHas('branches', ['tenant_id' => $tenant->id, 'code' => 'UTAMA']);
    $this->assertDatabaseHas('licenses', ['tenant_id' => $tenant->id, 'status' => 'pending', 'price' => 5000000]);
    $this->assertDatabaseCount('tenant_modules', 6);
    expect($tenant->uuid)->toBeUuid();
    expect($tenant->is_active)->toBeTrue();
    withinTestCompany($tenant, fn () => expect($user->getRoleNames()->all())->toBe(['Owner']));
});

test('missing company names are rejected without creating records', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('companies.store'), [])->assertSessionHasErrors(['name' => 'The name field is required.']);

    $this->assertDatabaseCount('tenants', 0);
});

test('company creation rolls back when the initial branch cannot be stored', function (): void {
    $user = User::factory()->create();
    Model::preventSilentlyDiscardingAttributes();
    Branch::creating(function (): void {
        throw new RuntimeException('Storage unavailable');
    });
    try {
        expect(fn () => createTestCompany($user))->toThrow(RuntimeException::class);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('tenant_user', 0);
    } finally {
        Branch::flushEventListeners();
        Model::clearBootedModels();
        Model::preventSilentlyDiscardingAttributes(false);
    }
});

test('tenant models return no records without a resolved company', function (): void {
    createTestCompany(User::factory()->create());

    expect(Branch::count())->toBe(0);
    expect(License::count())->toBe(0);
    expect(app(TenantContext::class)->id())->toBeNull();
});

test('users see only branches in their own company', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    $other = createTestCompany(User::factory()->create(), 'Perusahaan Rahasia');
    withinTestCompany($other, fn () => Branch::factory()->create(['name' => 'Cabang Rahasia']));

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('branches.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('foundation/branches')->has('branches', 1)->where('branches.0.name', 'Cabang Utama')->has('workspace.companies', 1));
});

test('foreign company branch updates return 404 and leave its data untouched', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    $other = createTestCompany(User::factory()->create());
    $branch = withinTestCompany($other, fn () => Branch::firstOrFail());

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->patch(route('branches.update', $branch), ['name' => 'Hijacked', 'code' => 'X'])->assertNotFound();

    $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'Cabang Utama', 'tenant_id' => $other->id]);
});

test('foreign company branches cannot be selected', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    $other = createTestCompany(User::factory()->create());
    $branch = withinTestCompany($other, fn () => Branch::firstOrFail());

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->post(route('branches.switch'), ['branch_id' => $branch->id])->assertNotFound();
});

test('company switching rejects nonmembers and preserves the original selection', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    $other = createTestCompany(User::factory()->create());

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->post(route('companies.switch'), ['tenant_id' => $other->id])->assertNotFound()->assertSessionHas('tenant_id', $tenant->id);
});

test('members can switch companies and reset the selected branch', function (): void {
    $user = User::factory()->create();
    $first = createTestCompany($user);
    $second = createTestCompany($user, 'Cabang Bisnis Kedua');

    $this->actingAs($user)->withSession(['tenant_id' => $first->id, 'branch_id' => 123])->post(route('companies.switch'), ['tenant_id' => $second->id])->assertRedirect(route('dashboard'))->assertSessionHas('tenant_id', $second->id)->assertSessionMissing('branch_id');
});

test('permissions cached in one company do not authorize another company', function (): void {
    $user = User::factory()->create();
    $first = createTestCompany($user);
    $second = createTestCompany(User::factory()->create());
    $second->users()->attach($user, ['is_active' => true]);
    withinTestCompany($second, fn () => $user->syncRoles(['Kasir']));
    withinTestCompany($first, fn () => $user->load('roles', 'permissions'));

    $this->actingAs($user)->withSession(['tenant_id' => $second->id])->get(route('tenant-users.index'))->assertForbidden();
});

test('inactive memberships cannot open business or settings pages', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    $tenant->users()->updateExistingPivot($user->id, ['is_active' => false]);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('dashboard'))->assertForbidden();
});

test('inactive companies cannot open the dashboard', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    $tenant->update(['is_active' => false]);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('dashboard'))->assertForbidden();
});

test('pending licenses cannot access paid modules through direct URLs', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('transaksi.index'))->assertForbidden();
});

test('active licenses allow authorized modules while expired support does not block usage', function (): void {
    $this->freezeTime();
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    withinTestCompany($tenant, fn () => License::firstOrFail()->update(['status' => 'active', 'activated_at' => now()->subDay(), 'support_ends_at' => now()->subDay()]));

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('transaksi.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->component('foundation/workspace')->where('module.name', 'Transaksi'));
});

test('expired suspended or future licenses cannot authorize modules', function (string $state): void {
    $this->freezeTime();
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    withinTestCompany($tenant, function () use ($state): void {
        License::firstOrFail()->update(['status' => $state === 'suspended' ? 'suspended' : 'active', 'activated_at' => $state === 'future' ? now()->addDay() : now()->subDay(), 'expires_at' => $state === 'expired' ? now()->subMinute() : null]);
    });

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('transaksi.index'))->assertForbidden();
})->with(['expired', 'suspended', 'future']);

test('disabled modules are hidden and rejected by direct access', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    activateTestLicense($tenant);
    withinTestCompany($tenant, fn () => TenantModule::where('code', 'produksi')->firstOrFail()->update(['is_enabled' => false]));

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('produksi.index'))->assertForbidden();
});

test('dashboard navigation omits disabled modules', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    activateTestLicense($tenant);
    withinTestCompany($tenant, fn () => TenantModule::where('code', 'produksi')->firstOrFail()->update(['is_enabled' => false]));

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('workspace.navigation', 6)->where('workspace.navigation.0.title', 'Dashboard')->where('workspace.navigation.4.code', 'keuangan'));
});

test('an active module cannot bypass a missing supporting module', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    activateTestLicense($tenant);
    withinTestCompany($tenant, fn () => TenantModule::where('code', 'bom')->firstOrFail()->update(['is_enabled' => false]));

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('produksi.index'))->assertForbidden();
});

test('module management prevents disabling a required dependency', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->patch(route('modules.update', 'bom'), ['is_enabled' => false])->assertSessionHasErrors(['is_enabled' => 'Nonaktifkan Produksi terlebih dahulu.']);

    $this->assertDatabaseHas('tenant_modules', ['tenant_id' => $tenant->id, 'code' => 'bom', 'is_enabled' => true]);
});

test('module management permits hiding a leaf module without deleting its entitlement', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->patch(route('modules.update', 'produksi'), ['is_enabled' => false])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('tenant_modules', ['tenant_id' => $tenant->id, 'code' => 'produksi', 'is_enabled' => false]);
    $this->assertDatabaseCount('tenant_modules', 6);
    $this->assertDatabaseHas('audit_logs', ['tenant_id' => $tenant->id, 'action' => 'modul.diperbarui']);
});

test('module management cannot activate a module whose dependency is missing', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    withinTestCompany($tenant, fn () => TenantModule::whereIn('code', ['bom', 'produksi'])->get()->each(fn (TenantModule $module) => $module->update(['is_enabled' => false])));

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->patch(route('modules.update', 'produksi'), ['is_enabled' => true])->assertSessionHasErrors(['is_enabled' => 'Aktifkan BOM terlebih dahulu.']);

    $this->assertDatabaseHas('tenant_modules', ['tenant_id' => $tenant->id, 'code' => 'produksi', 'is_enabled' => false]);
});

test('cashiers operators finance and read only users cannot manage branches', function (string $role): void {
    $user = User::factory()->create();
    $tenant = createTestCompany(User::factory()->create());
    $tenant->users()->attach($user, ['is_active' => true]);
    withinTestCompany($tenant, fn () => $user->assignRole($role));

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('branches.index'))->assertForbidden();
})->with(['Kasir', 'Operator Produksi', 'Keuangan', 'Read Only']);

test('cashiers cannot access finance through a direct module URL', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany(User::factory()->create());
    activateTestLicense($tenant);
    $tenant->users()->attach($user, ['is_active' => true]);
    withinTestCompany($tenant, fn () => $user->assignRole('Kasir'));

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('keuangan.index'))->assertForbidden();
});

test('new branches are assigned to the server resolved company', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);
    $other = createTestCompany(User::factory()->create());

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->post(route('branches.store'), ['name' => 'Bandung', 'code' => 'BDG', 'tenant_id' => $other->id])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('branches', ['name' => 'Bandung', 'tenant_id' => $tenant->id]);
    $this->assertDatabaseMissing('branches', ['name' => 'Bandung', 'tenant_id' => $other->id]);
});

test('branch codes are unique within a company and can be reused by other companies', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->post(route('branches.store'), ['name' => 'Duplicate', 'code' => 'UTAMA'])->assertSessionHasErrors('code');

    $this->assertDatabaseCount('branches', 1);
});

test('administrators cannot attach an existing global account without its consent', function (): void {
    $owner = User::factory()->create();
    $tenant = createTestCompany($owner);
    $existing = User::factory()->create();

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('tenant-users.store'), ['name' => 'Other', 'email' => $existing->email, 'role' => 'Administrator', 'password' => 'Secure-password-123', 'password_confirmation' => 'Secure-password-123'])->assertSessionHasErrors('email');

    $this->assertDatabaseMissing('tenant_user', ['tenant_id' => $tenant->id, 'user_id' => $existing->id]);
});

test('new staff accounts get the chosen role and a verification email', function (): void {
    $owner = User::factory()->create();
    $tenant = createTestCompany($owner);
    Notification::fake();

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->post(route('tenant-users.store'), ['name' => 'Kasir Baru', 'email' => 'kasir@example.test', 'role' => 'Kasir', 'password' => 'Secure-password-123', 'password_confirmation' => 'Secure-password-123'])->assertSessionHasNoErrors();

    $user = User::where('email', 'kasir@example.test')->firstOrFail();
    $this->assertDatabaseHas('tenant_user', ['tenant_id' => $tenant->id, 'user_id' => $user->id]);
    withinTestCompany($tenant, fn () => expect($user->getRoleNames()->all())->toBe(['Kasir']));
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('foreign company user updates return 404', function (): void {
    $owner = User::factory()->create();
    $tenant = createTestCompany($owner);
    $foreign = User::factory()->create();
    createTestCompany($foreign);

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->patch(route('tenant-users.update', $foreign), ['role' => 'Kasir', 'is_active' => false])->assertNotFound();
});

test('owner accounts cannot be disabled through tenant user management', function (): void {
    $owner = User::factory()->create();
    $tenant = createTestCompany($owner);

    $this->actingAs($owner)->withSession(['tenant_id' => $tenant->id])->patch(route('tenant-users.update', $owner), ['role' => 'Kasir', 'is_active' => false])->assertForbidden();

    $this->assertDatabaseHas('tenant_user', ['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'is_active' => true]);
});

test('tenant members cannot delete their global account and orphan company access', function (): void {
    $owner = User::factory()->create();
    createTestCompany($owner);

    $this->actingAs($owner)->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();

    $this->assertModelExists($owner);
});

test('license activation changes only the selected company and restores context', function (): void {
    $tenant = createTestCompany(User::factory()->create());
    $other = createTestCompany(User::factory()->create());

    $this->artisan('erp:activate-license', ['tenant' => $tenant->uuid])->assertSuccessful();

    $this->assertDatabaseHas('licenses', ['tenant_id' => $tenant->id, 'status' => 'active']);
    $this->assertDatabaseHas('licenses', ['tenant_id' => $other->id, 'status' => 'pending']);
    expect(app(TenantContext::class)->id())->toBeNull();
});

test('request completion clears tenant and permission context', function (): void {
    $user = User::factory()->create();
    $tenant = createTestCompany($user);

    $this->actingAs($user)->withSession(['tenant_id' => $tenant->id])->get(route('dashboard'))->assertOk();

    expect(app(TenantContext::class)->id())->toBeNull();
    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();
});

test('tenant scoping resolves fresh context after scoped services are reset', function (): void {
    $tenant = createTestCompany(User::factory()->create());
    app()->forgetScopedInstances();

    withinTestCompany($tenant, fn () => expect(Branch::count())->toBe(1));
});

test('foreign records cannot be moved or deleted by bypassing query scopes', function (): void {
    $first = createTestCompany(User::factory()->create());
    $second = createTestCompany(User::factory()->create());
    $foreign = withinTestCompany($second, fn () => Branch::firstOrFail());

    withinTestCompany($first, function () use ($first, $foreign): void {
        $foreign->tenant_id = $first->id;
        expect(fn () => $foreign->save())->toThrow(AuthorizationException::class);
        $foreign->refresh();
        expect(fn () => $foreign->delete())->toThrow(AuthorizationException::class);
    });

    $this->assertDatabaseHas('branches', ['id' => $foreign->id, 'tenant_id' => $second->id]);
});

test('role permissions match familiar ERP responsibilities', function (string $role, array $allowed): void {
    $user = User::factory()->create();
    $tenant = createTestCompany(User::factory()->create());
    $tenant->users()->attach($user, ['is_active' => true]);
    withinTestCompany($tenant, function () use ($user, $role, $allowed): void {
        $user->assignRole($role);
        foreach (['transaksi.view', 'bom.view', 'routing.view', 'produksi.view', 'keuangan.view', 'laba_rugi.view', 'perusahaan.manage', 'cabang.manage', 'pengguna.manage', 'modul.manage'] as $ability) {
            expect($user->can($ability))->toBe(in_array($ability, $allowed, true));
        }
    });
})->with([
    ['Owner', ['transaksi.view', 'bom.view', 'routing.view', 'produksi.view', 'keuangan.view', 'laba_rugi.view', 'perusahaan.manage', 'cabang.manage', 'pengguna.manage', 'modul.manage']],
    ['Administrator', ['transaksi.view', 'bom.view', 'routing.view', 'produksi.view', 'keuangan.view', 'laba_rugi.view', 'perusahaan.manage', 'cabang.manage', 'pengguna.manage', 'modul.manage']],
    ['Kasir', ['transaksi.view']],
    ['Operator Produksi', ['produksi.view', 'bom.view', 'routing.view']],
    ['Keuangan', ['keuangan.view', 'laba_rugi.view']],
    ['Read Only', ['transaksi.view', 'bom.view', 'routing.view', 'produksi.view', 'keuangan.view', 'laba_rugi.view']],
]);

test('database constraints refuse roles belonging to a different company', function (): void {
    $user = User::factory()->create();
    $first = createTestCompany($user);
    $second = createTestCompany(User::factory()->create());
    $foreignRole = Role::where('tenant_id', $second->id)->where('name', 'Administrator')->firstOrFail();

    withinTestCompany($first, fn () => expect(fn () => $user->assignRole($foreignRole))->toThrow(QueryException::class));

    $this->assertDatabaseMissing('model_has_roles', ['tenant_id' => $first->id, 'role_id' => $foreignRole->id, 'model_id' => $user->id]);
});

test('seeders preserve active licenses and hidden module settings when run again', function (): void {
    $tenant = createTestCompany(User::factory()->create());
    activateTestLicense($tenant);
    withinTestCompany($tenant, fn () => TenantModule::where('code', 'produksi')->firstOrFail()->update(['is_enabled' => false]));

    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseHas('licenses', ['tenant_id' => $tenant->id, 'status' => 'active']);
    $this->assertDatabaseHas('tenant_modules', ['tenant_id' => $tenant->id, 'code' => 'produksi', 'is_enabled' => false]);
    $this->assertDatabaseCount('branches', 1);
    $this->assertDatabaseCount('tenant_modules', 6);
});
