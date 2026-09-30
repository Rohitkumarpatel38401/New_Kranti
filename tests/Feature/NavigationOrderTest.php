<?php

use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

/** @return array<string, list<string>> group label => item labels, in sidebar order */
function sidebar(string $panel): array
{
    Filament::setCurrentPanel($panel);

    return collect(Filament::getNavigation())
        ->mapWithKeys(fn ($group) => [(string) ($group->getLabel() ?? '') => collect($group->getItems())->map(fn ($i) => (string) $i->getLabel())->values()->all()])
        ->all();
}

it('orders the HQ sidebar by daily use, grouped by category', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');
    $nav = sidebar('admin');

    expect(array_keys($nav))->toBe(['', 'Admissions', 'Exams', 'Franchise Network', 'Wallets', 'Fees', 'Attendance', 'Academics', 'Website', 'HQ Team'])
        ->and($nav['Admissions'])->toBe(['New admission', 'Enrollments', 'Students', 'Enquiries'])
        ->and($nav['Wallets'][0])->toBe('Recharge requests')
        ->and($nav['Academics'][0])->toBe('Courses')
        ->and(last($nav['Website']))->toBe('Site settings')
        ->and($nav['HQ Team'])->toBe(['Team members', 'Roles']);
});

it('orders the institute sidebar by daily use, grouped by category', function () {
    $institute = Institute::factory()->create();
    $owner = InstituteUser::factory()->owner()->for($institute)->create()->assignRole('owner');
    $this->actingAs($owner, 'institute');
    app(CurrentInstitute::class)->set($institute->id);
    $nav = sidebar('institute');

    expect(array_keys($nav))->toBe(['', 'Admissions', 'Fees', 'Attendance', 'Exams', 'Wallet', 'Institute setup'])
        ->and($nav['Admissions'])->toBe(['New admission', 'Students', 'Enrollments'])
        ->and($nav['Attendance'][0])->toBe('Take attendance')
        ->and($nav['Institute setup'])->toBe(['Batches', 'Staff', 'My profile']);
});

it('puts the student profile after courses and receipts', function () {
    $this->actingAs(Student::factory()->withActiveEnrollment()->create(), 'student');
    $nav = sidebar('student');

    expect($nav[''])->toBe(['Dashboard', 'My courses', 'My receipts', 'My profile']);
});
