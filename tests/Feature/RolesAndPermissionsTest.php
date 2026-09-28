<?php

use App\Models\Admin;
use App\Models\InstituteUser;
use App\Support\Permissions\AdminPermissions;
use App\Support\Permissions\InstitutePermissions;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('creates admin roles and permissions on the admin guard', function () {
    expect(Role::where('guard_name', 'admin')->pluck('name')->sort()->values()->all())
        ->toBe(collect(AdminPermissions::ROLES)->sort()->values()->all());

    foreach (AdminPermissions::CUSTOM as $name) {
        expect(Permission::where(['name' => $name, 'guard_name' => 'admin'])->exists())->toBeTrue();
    }
});

it('creates institute roles with mapped permissions on the institute guard', function () {
    foreach (InstitutePermissions::ROLE_PERMISSIONS as $role => $permissions) {
        $model = Role::findByName($role, 'institute');
        expect($model->permissions->pluck('name')->sort()->values()->all())
            ->toBe(collect($permissions)->sort()->values()->all());
    }

    expect(InstitutePermissions::ROLE_PERMISSIONS['owner'])->toEqualCanonicalizing(InstitutePermissions::CUSTOM);
});

it('is idempotent', function () {
    $roles = Role::count();
    $permissions = Permission::count();

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe($roles)->and(Permission::count())->toBe($permissions);
});

it('keeps admin role permissions granted through the UI when re-seeded', function () {
    Role::findByName('support', 'admin')->givePermissionTo('manage_admins');

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::findByName('support', 'admin')->hasPermissionTo('manage_admins'))->toBeTrue();
});

it('lets super admin pass any ability and limits other admins', function () {
    $super = Admin::factory()->create()->assignRole('super_admin');
    $accounts = Admin::factory()->create()->assignRole('accounts');

    expect($super->can('impersonate_institute'))->toBeTrue()
        ->and($super->can('anything_at_all'))->toBeTrue()
        ->and($accounts->can('impersonate_institute'))->toBeFalse()
        ->and($accounts->can('approve_wallet_recharge'))->toBeTrue();
});

it('gives the institute owner role every institute permission', function () {
    $owner = InstituteUser::factory()->owner()->create()->assignRole('owner');
    $teacher = InstituteUser::factory()->create()->assignRole('teacher');

    expect($owner->can('manage_staff'))->toBeTrue()
        ->and($teacher->can('take_attendance'))->toBeTrue()
        ->and($teacher->can('collect_fee'))->toBeFalse();
});

it('shows the role editor to super admins in the admin panel only', function () {
    $super = Admin::factory()->create()->assignRole('super_admin');

    $this->actingAs($super, 'admin')->get('/admin/shield/roles')
        ->assertOk()
        ->assertSee('Manager')
        ->assertDontSee('Receptionist'); // institute roles are code-defined, not edited here

    expect(collect(\Filament\Facades\Filament::getPanel('institute')->getPlugins())->keys()->all())
        ->not->toContain('filament-shield');
});

it('does not open institute roles in the admin role editor by URL', function () {
    $super = Admin::factory()->create()->assignRole('super_admin');
    $teacher = Role::findByName('teacher', 'institute');

    $this->actingAs($super, 'admin')->get("/admin/shield/roles/{$teacher->id}/edit")->assertNotFound();
});
