<?php

use App\Enums\AddressType;
use App\Enums\FranchiseApplicationStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\FranchiseApplications\Pages\ListFranchiseApplications;
use App\Models\Admin;
use App\Models\FranchiseApplication;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Notifications\InstituteOwnerCredentials;
use App\Services\FranchiseOnboardingService;
use App\Services\NumberSequenceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    app(NumberSequenceService::class)->syncAtLeast('institute_code', 1);
    $this->service = app(FranchiseOnboardingService::class);
    $this->admin = Admin::factory()->create()->assignRole('manager');
    Notification::fake();
});

function submitApplication(array $overrides = []): FranchiseApplication
{
    return test()->service->submit(array_merge([
        'applicant_name' => 'Sunita Devi',
        'email' => 'sunita@example.com',
        'phone' => '9833333333',
        'proposed_center_name' => 'Sunita Computer Point',
        'computers_count' => 10,
        'area_sqft' => 600,
        'experience_years' => 3,
        'message' => 'Want to open a centre.',
    ], $overrides), [
        'line_1' => 'Station Road', 'city' => 'Jaunpur', 'district' => 'Jaunpur', 'state' => 'Uttar Pradesh', 'pincode' => '222001',
    ]);
}

it('numbers new applications', function () {
    $application = submitApplication();

    expect($application->application_no)->toBe('KCTI/FA/'.now()->year.'/00001')
        ->and($application->status)->toBe(FranchiseApplicationStatus::New)
        ->and($application->address(AddressType::Office)->city)->toBe('Jaunpur');
});

it('converts an application into an institute with an owner login', function () {
    $application = submitApplication();

    $institute = $this->service->convert($application, $this->admin);

    $owner = InstituteUser::where('email', 'sunita@example.com')->sole();
    $application->refresh();

    expect($institute->code)->toBe('KCTI0002')
        ->and($institute->name)->toBe('Sunita Computer Point')
        ->and($institute->profile->owner_name)->toBe('Sunita Devi')
        ->and($institute->profile->computers_count)->toBe(10)
        ->and($institute->address(AddressType::Office)->city)->toBe('Jaunpur')
        ->and($owner->is_owner)->toBeTrue()
        ->and($owner->institute_id)->toBe($institute->id)
        ->and($owner->hasRole('owner'))->toBeTrue()
        ->and($application->status)->toBe(FranchiseApplicationStatus::Approved)
        ->and($application->converted_institute_id)->toBe($institute->id)
        ->and($application->reviewed_by_admin_id)->toBe($this->admin->id);

    Notification::assertSentTo($owner, InstituteOwnerCredentials::class,
        fn (InstituteOwnerCredentials $n) => Hash::check($n->password, $owner->password));
});

it('converts only once', function () {
    $application = submitApplication();
    $this->service->convert($application, $this->admin);

    expect(fn () => $this->service->convert($application, $this->admin))->toThrow(DomainRuleException::class)
        ->and(Institute::count())->toBe(1)
        ->and(InstituteUser::count())->toBe(1);
});

it('refuses to convert a rejected application', function () {
    $application = submitApplication();
    $this->service->reject($application, 'Area too small', $this->admin);

    expect(fn () => $this->service->convert($application, $this->admin))->toThrow(DomainRuleException::class);
});

it('creates nothing when the owner email already has a login', function () {
    InstituteUser::factory()->create(['email' => 'sunita@example.com']);
    $application = submitApplication();

    expect(fn () => $this->service->convert($application, $this->admin))->toThrow(DomainRuleException::class, 'already');
    expect(Institute::where('name', 'Sunita Computer Point')->exists())->toBeFalse()
        ->and($application->fresh()->status)->toBe(FranchiseApplicationStatus::New);
});

it('requires remarks to reject', function () {
    expect(fn () => $this->service->reject(submitApplication(), ' ', $this->admin))->toThrow(DomainRuleException::class);
});

it('converts from the admin table with overrides', function () {
    Permission::findOrCreate('ViewAny:FranchiseApplication', 'admin');
    $this->admin->givePermissionTo('ViewAny:FranchiseApplication');
    $application = submitApplication();
    $this->actingAs($this->admin, 'admin');
    Filament::setCurrentPanel('admin');

    Livewire::test(ListFranchiseApplications::class)
        ->assertCanSeeTableRecords([$application])
        ->callTableAction('convert', $application, data: ['institute_name' => 'Sunita Computer Academy', 'owner_email' => 'owner@sunita.test'])
        ->assertHasNoTableActionErrors();

    expect(Institute::where('name', 'Sunita Computer Academy')->exists())->toBeTrue()
        ->and(InstituteUser::where('email', 'owner@sunita.test')->exists())->toBeTrue();
});

it('hides convert without approve_franchise', function () {
    Permission::findOrCreate('ViewAny:FranchiseApplication', 'admin');
    $viewer = Admin::factory()->create()->givePermissionTo('ViewAny:FranchiseApplication');
    $application = submitApplication();
    $this->actingAs($viewer, 'admin');
    Filament::setCurrentPanel('admin');

    Livewire::test(ListFranchiseApplications::class)->assertTableActionHidden('convert', $application);
});
