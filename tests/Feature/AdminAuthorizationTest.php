<?php

use App\Models\Admin;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('forbids admins without manage_admins from the role editor', function (string $uri) {
    $support = Admin::factory()->create()->assignRole('support');
    $uri = str_replace(['{support}', '{super}'], [Role::findByName('support', 'admin')->id, Role::findByName('super_admin', 'admin')->id], $uri);

    $this->actingAs($support, 'admin')->get($uri)->assertForbidden();
})->with([
    '/admin/shield/roles',
    '/admin/shield/roles/create',
    '/admin/shield/roles/{support}/edit',
    '/admin/shield/roles/{super}/edit',
]);

it('lets admins with manage_admins use the role editor', function () {
    Role::findByName('support', 'admin')->givePermissionTo('manage_admins');
    $admin = Admin::factory()->create()->assignRole('support');

    $this->actingAs($admin, 'admin')->get('/admin/shield/roles')->assertOk();
});

it('forbids the institutes list without ViewAny:Institute', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin')
        ->get('/admin/institutes')
        ->assertForbidden();
});

it('lets managers list institutes', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('manager'), 'admin')
        ->get('/admin/institutes')
        ->assertOk();
});

it('fails loudly for admin resources without a policy', function () {
    expect(Filament::getPanel('admin')->isAuthorizationStrict())->toBeTrue();
});
