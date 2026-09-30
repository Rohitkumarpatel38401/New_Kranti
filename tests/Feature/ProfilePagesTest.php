<?php

use App\Filament\Institute\Pages\Auth\EditProfile;
use App\Filament\Student\Pages\MyProfile;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('has no sign-out account card on any dashboard', function (string $panel) {
    expect(Filament::getPanel($panel)->getWidgets())->not->toContain(AccountWidget::class);
})->with(['admin', 'institute', 'student']);

it('shows the student profile and the password form on one page', function () {
    $student = Student::factory()->withActiveEnrollment()->create();
    $student->personal()->create(['dob' => '2002-02-02', 'gender' => 'male']);

    $this->actingAs($student, 'student')->get(MyProfile::getUrl(panel: 'student'))
        ->assertOk()->assertSee($student->registration_no)->assertSee('Change password')->assertSee('Current password');
});

it('shows the institute details and the account form on one full-page profile', function () {
    $institute = Institute::factory()->create(['name' => 'Ganga Computer Centre']);
    $owner = InstituteUser::factory()->owner()->for($institute)->create()->assignRole('owner');
    $this->actingAs($owner, 'institute');
    app(CurrentInstitute::class)->set($institute->id);
    Filament::setCurrentPanel('institute');

    expect(EditProfile::isSimple())->toBeFalse();
    $this->get(EditProfile::getUrl(panel: 'institute'))
        ->assertOk()->assertSee('Ganga Computer Centre')->assertSee($institute->code)->assertSee('Wallet balance');
    Livewire::test(EditProfile::class)->assertFormFieldVisible('password')->assertFormFieldVisible('name');
});

it('lists My profile in the institute sidebar', function () {
    $institute = Institute::factory()->create();
    $owner = InstituteUser::factory()->owner()->for($institute)->create()->assignRole('owner');
    $this->actingAs($owner, 'institute');
    app(CurrentInstitute::class)->set($institute->id);

    $this->get('/institute')->assertOk()->assertSee(EditProfile::getUrl(panel: 'institute'), false)->assertSee('My profile');
});
