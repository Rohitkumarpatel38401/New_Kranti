<?php

use App\Models\Admin;
use App\Models\InstituteUser;
use App\Policies\ShieldPolicy;
use Spatie\Permission\Models\Permission;

class ProbeInstitutePolicy extends ShieldPolicy
{
    protected static string $subject = 'Institute';
}

beforeEach(function () {
    Permission::findOrCreate('ViewAny:Institute', 'admin');
    Permission::findOrCreate('ViewAny:Institute', 'institute');
});

it('allows admins holding the Shield key', function () {
    $admin = Admin::factory()->create()->givePermissionTo('ViewAny:Institute');

    expect((new ProbeInstitutePolicy)->viewAny($admin))->toBeTrue();
});

it('denies admins without the key', function () {
    expect((new ProbeInstitutePolicy)->viewAny(Admin::factory()->create()))->toBeFalse();
});

it('denies institute users even with a same-named permission', function () {
    $user = InstituteUser::factory()->create()->givePermissionTo('ViewAny:Institute');

    expect((new ProbeInstitutePolicy)->viewAny($user))->toBeFalse();
});

it('never allows force delete, replicate, or reorder', function () {
    $admin = Admin::factory()->create();
    $policy = new ProbeInstitutePolicy;
    $model = new App\Models\Institute;

    expect($policy->forceDelete($admin, $model))->toBeFalse()
        ->and($policy->replicate($admin, $model))->toBeFalse()
        ->and($policy->reorder($admin))->toBeFalse();
});
