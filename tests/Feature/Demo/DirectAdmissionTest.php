<?php

use App\Enums\EnrollmentStatus;
use App\Enums\WalletSource;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Pages\NewAdmission as AdminNewAdmission;
use App\Filament\Institute\Pages\NewAdmission as InstituteNewAdmission;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\WalletService;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, AcademicDefaultsSeeder::class]);
    $this->admin = Admin::factory()->create()->assignRole('manager');
    $this->course = Course::factory()->create(['hq_registration_fee' => 500]);
    $this->franchise = Institute::factory()->create();
    $this->franchise->courses()->attach($this->course->id, ['is_active' => true]);
    $this->owner = InstituteUser::factory()->owner()->for($this->franchise)->create()->assignRole('owner');
    $this->session = AcademicSession::current();
});

function admissionArgs(string $phone = '9876512345'): array
{
    return [
        ['first_name' => 'Kiran', 'last_name' => 'Devi', 'phone' => $phone],
        ['dob' => '2005-01-01', 'gender' => 'female'],
        [['relation' => 'father', 'name' => 'Ram Lal', 'is_primary' => true]],
        [],
    ];
}

it('lets HQ admit a student into a franchise as active, charging the wallet', function () {
    app(WalletService::class)->credit($this->franchise, '2000.00', WalletSource::Adjustment, null, 'x', $this->admin);
    [$s, $p, $g, $a] = admissionArgs();

    $enrollment = app(EnrollmentService::class)->admitDirectly($s, $p, $g, $a, $this->course, null, $this->session, '6000', '0', $this->admin, $this->franchise);

    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->enrollment_no)->not->toBeNull()
        ->and($enrollment->student->institute_id)->toBe($this->franchise->id)
        ->and($enrollment->student->can_login)->toBeTrue()
        ->and(app(WalletService::class)->balance($this->franchise))->toBe('1500.00');
});

it('lets an institute admit in one step, pending HQ approval', function () {
    app(WalletService::class)->credit($this->franchise, '2000.00', WalletSource::Adjustment, null, 'x', $this->admin);
    [$s, $p, $g, $a] = admissionArgs();

    $enrollment = app(EnrollmentService::class)->admitDirectly($s, $p, $g, $a, $this->course, null, $this->session, '6000', '500', $this->owner);

    expect($enrollment->status)->toBe(EnrollmentStatus::PendingApproval)
        ->and($enrollment->student->institute_id)->toBe($this->franchise->id)
        ->and(app(WalletService::class)->balance($this->franchise))->toBe('1500.00');
});

it('saves nothing when the franchise wallet is short', function () {
    [$s, $p, $g, $a] = admissionArgs();

    expect(fn () => app(EnrollmentService::class)->admitDirectly($s, $p, $g, $a, $this->course, null, $this->session, '6000', '0', $this->admin, $this->franchise))
        ->toThrow(DomainRuleException::class);
    expect(Student::withoutGlobalScopes()->count())->toBe(0)
        ->and(Enrollment::withoutGlobalScopes()->count())->toBe(0);
});

it('admits from the HQ New admission page', function () {
    Filament::setCurrentPanel('admin');
    app(WalletService::class)->credit($this->franchise, '2000.00', WalletSource::Adjustment, null, 'x', $this->admin);
    $this->actingAs($this->admin, 'admin');

    Livewire::test(AdminNewAdmission::class)
        ->fillForm([
            'institute_id' => $this->franchise->id,
            'student' => ['first_name' => 'Kiran', 'phone' => '9876512345', 'joined_on' => now()->toDateString()],
            'personal' => ['dob' => '2005-01-01', 'gender' => 'female'],
            'guardians' => [['relation' => 'father', 'name' => 'Ram Lal', 'is_primary' => true]],
            'course_id' => $this->course->id, 'academic_session_id' => $this->session->id,
            'admission_date' => now()->toDateString(), 'course_fee' => '6000', 'discount' => '0',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Enrollment::withoutGlobalScopes()->sole()->status)->toBe(EnrollmentStatus::Active);
});

it('admits from the institute New admission page', function () {
    Filament::setCurrentPanel('institute');
    app(WalletService::class)->credit($this->franchise, '2000.00', WalletSource::Adjustment, null, 'x', $this->admin);
    $this->actingAs($this->owner, 'institute');
    app(CurrentInstitute::class)->set($this->franchise->id);

    Livewire::test(InstituteNewAdmission::class)
        ->fillForm([
            'student' => ['first_name' => 'Kiran', 'phone' => '9876512345', 'joined_on' => now()->toDateString()],
            'personal' => ['dob' => '2005-01-01', 'gender' => 'female'],
            'guardians' => [['relation' => 'father', 'name' => 'Ram Lal', 'is_primary' => true]],
            'course_id' => $this->course->id, 'academic_session_id' => $this->session->id,
            'admission_date' => now()->toDateString(), 'course_fee' => '6000', 'discount' => '0',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Enrollment::withoutGlobalScopes()->sole()->status)->toBe(EnrollmentStatus::PendingApproval);
});

it('keeps the HQ New admission page from admins who cannot approve enrollments', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin');

    $this->get(AdminNewAdmission::getUrl(panel: 'admin'))->assertForbidden();
});
