<?php

use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Admins\Pages\CreateAdmin;
use App\Filament\Admin\Resources\Admins\Pages\EditAdmin;
use App\Models\Admin;
use App\Services\AdminTeamService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->super = Admin::factory()->create()->assignRole('super_admin');
    $this->service = app(AdminTeamService::class);
    Filament::setCurrentPanel('admin');
});

it('creates an HQ admin with a role through the form', function () {
    $this->actingAs($this->super, 'admin');

    Livewire::test(CreateAdmin::class)
        ->fillForm(['name' => 'Accounts Person', 'email' => 'accounts@kcti.test', 'password' => 'secret-123', 'roles' => ['accounts'], 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $admin = Admin::where('email', 'accounts@kcti.test')->sole();
    expect($admin->hasRole('accounts'))->toBeTrue()
        ->and(Hash::check('secret-123', $admin->password))->toBeTrue();
});

it('keeps the password when left empty on edit', function () {
    $other = Admin::factory()->create()->assignRole('support');
    $this->actingAs($this->super, 'admin');

    Livewire::test(EditAdmin::class, ['record' => $other->getRouteKey()])
        ->fillForm(['name' => 'Renamed', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($other->fresh()->name)->toBe('Renamed')
        ->and(Hash::check('password', $other->fresh()->password))->toBeTrue();
});

it('offers only admin-guard roles', function () {
    $this->actingAs($this->super, 'admin');

    Livewire::test(CreateAdmin::class)
        ->fillForm(['name' => 'X', 'email' => 'x@kcti.test', 'password' => 'secret-123', 'roles' => ['teacher']])
        ->call('create')
        ->assertHasFormErrors(['roles.0']);

    expect(Admin::where('email', 'x@kcti.test')->exists())->toBeFalse();
});

it('forbids the team pages without manage_admins', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('accounts'), 'admin')
        ->get('/admin/admins')
        ->assertForbidden();
});

it('protects the last active super admin', function () {
    // Acting as an inactive super admin passes the "only super admins touch super admins" rule,
    // so only the last-active-super-admin rule can refuse these.
    $by = Admin::factory()->inactive()->create()->assignRole('super_admin');

    expect(fn () => $this->service->update($this->super, ['is_active' => false], null, $by))
        ->toThrow(DomainRuleException::class, 'At least one active Super Admin')
        ->and(fn () => $this->service->update($this->super, [], ['manager'], $by))
        ->toThrow(DomainRuleException::class, 'At least one active Super Admin')
        ->and(fn () => $this->service->delete($this->super, $by))
        ->toThrow(DomainRuleException::class, 'At least one active Super Admin');

    expect($this->super->fresh()->is_active)->toBeTrue()
        ->and($this->super->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('allows demoting a super admin when another active one remains', function () {
    $second = Admin::factory()->create()->assignRole('super_admin');

    $this->service->update($this->super, [], ['manager'], $second);

    expect($this->super->fresh()->hasRole('super_admin'))->toBeFalse();
});

it('refuses deactivating or deleting yourself', function () {
    $manager = Admin::factory()->create()->assignRole('manager');

    expect(fn () => $this->service->update($manager, ['is_active' => false], null, $manager))->toThrow(DomainRuleException::class)
        ->and(fn () => $this->service->delete($manager, $manager))->toThrow(DomainRuleException::class);
});

it('never deletes the super_admin role', function () {
    expect(fn () => Role::findByName('super_admin', 'admin')->delete())->toThrow(DomainRuleException::class);
    expect(Role::where('name', 'super_admin')->exists())->toBeTrue();
});
