<?php

use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Institutes\Pages\ListInstitutes;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Services\InstituteOwnerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
    $this->manager = Admin::factory()->create()->assignRole('manager');
    $this->service = app(InstituteOwnerService::class);
});

it('creates an owner login and sends a reset link instead of a password', function () {
    $institute = Institute::factory()->create();

    $owner = $this->service->createOwner($institute, 'Meena Singh', 'Meena@Example.com', '9855555555', $this->manager);

    expect($owner->is_owner)->toBeTrue()
        ->and($owner->email)->toBe('meena@example.com')
        ->and($owner->institute_id)->toBe($institute->id)
        ->and($owner->hasRole('owner'))->toBeTrue();
    Notification::assertSentTo($owner, ResetPassword::class);
});

it('refuses a second owner or a taken email', function () {
    $institute = Institute::factory()->create();
    InstituteUser::factory()->owner()->for($institute)->create();
    InstituteUser::factory()->create(['email' => 'taken@example.com']);

    expect(fn () => $this->service->createOwner($institute, 'X', 'x@example.com', null, $this->manager))
        ->toThrow(DomainRuleException::class, 'already has an owner')
        ->and(fn () => $this->service->createOwner(Institute::factory()->create(), 'Y', 'taken@example.com', null, $this->manager))
        ->toThrow(DomainRuleException::class, 'already');
});

it('sends a reset link to the existing owner', function () {
    $institute = Institute::factory()->create();
    $owner = InstituteUser::factory()->owner()->for($institute)->create();

    $this->service->sendResetLink($institute);

    Notification::assertSentTo($owner, ResetPassword::class);
});

it('refuses a reset link when there is no owner', function () {
    expect(fn () => $this->service->sendResetLink(Institute::factory()->create()))->toThrow(DomainRuleException::class);
});

it('offers create-owner only for institutes without an owner, and needs Update:Institute', function () {
    $without = Institute::factory()->create();
    $with = Institute::factory()->create();
    InstituteUser::factory()->owner()->for($with)->create();
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->manager, 'admin');

    Livewire::test(ListInstitutes::class)
        ->assertTableActionVisible('createOwner', $without)
        ->assertTableActionHidden('createOwner', $with)
        ->assertTableActionVisible('sendOwnerReset', $with)
        ->callTableAction('createOwner', $without, data: ['name' => 'New Owner', 'email' => 'new@owner.test', 'phone' => '9866666666'])
        ->assertHasNoTableActionErrors();

    expect($without->owner()->exists())->toBeTrue();
});

it('hides owner actions without Update:Institute', function () {
    $institute = Institute::factory()->create();
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->create()->assignRole('accounts'), 'admin');

    Livewire::test(ListInstitutes::class)->assertTableActionHidden('createOwner', $institute);
});
