<?php

use App\Enums\EnrollmentStatus;
use App\Enums\WalletSource;
use App\Filament\Institute\Resources\Enrollments\Pages\CreateEnrollment;
use App\Filament\Institute\Resources\Enrollments\Pages\ListEnrollments;
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
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $this->course = Course::factory()->create(['hq_registration_fee' => 300, 'suggested_fee' => 6000]);
    $this->institute->courses()->attach($this->course, ['is_active' => true]);
    $this->session = AcademicSession::factory()->create(['is_current' => true]);
    $this->student = Student::factory()->for($this->institute)->create(['first_name' => 'Kiran']);
    $this->student->personal()->create(['dob' => '2003-05-05', 'gender' => 'female']);
    Filament::setCurrentPanel('institute');
});

function actAsOwner(): void
{
    test()->actingAs(test()->owner, 'institute');
    app(CurrentInstitute::class)->set(test()->institute->id);
}

it('creates a draft enrollment from the form', function () {
    actAsOwner();

    Livewire::test(CreateEnrollment::class)
        ->fillForm(['student_id' => $this->student->id, 'course_id' => $this->course->id, 'academic_session_id' => $this->session->id, 'course_fee' => 6000, 'discount' => 1000])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Enrollment::sole()->net_fee)->toBe('5000.00')
        ->and(Enrollment::sole()->status)->toBe(EnrollmentStatus::Draft);
});

it('does not offer another institute student or an unauthorized course', function () {
    $foreign = Student::factory()->for(Institute::factory())->create();
    $unauthorized = Course::factory()->create();
    actAsOwner();

    Livewire::test(CreateEnrollment::class)
        ->fillForm(['student_id' => $foreign->id, 'course_id' => $unauthorized->id, 'academic_session_id' => $this->session->id, 'course_fee' => 100])
        ->call('create')
        ->assertHasFormErrors(['student_id', 'course_id']);
});

it('submits for approval from the table and debits the wallet', function () {
    app(WalletService::class)->credit($this->institute, '500', WalletSource::Recharge, null, 'seed', Admin::factory()->create());
    $enrollment = app(EnrollmentService::class)->createDraft($this->student, $this->course, null, $this->session, '6000', '0', $this->owner);
    actAsOwner();

    Livewire::test(ListEnrollments::class)->callTableAction('submit', $enrollment)->assertHasNoTableActionErrors();

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::PendingApproval)
        ->and(app(WalletService::class)->balance($this->institute))->toBe('200.00');
});

it('tells the institute to recharge when the balance is too low', function () {
    $enrollment = app(EnrollmentService::class)->createDraft($this->student, $this->course, null, $this->session, '6000', '0', $this->owner);
    actAsOwner();

    Livewire::test(ListEnrollments::class)->callTableAction('submit', $enrollment)->assertNotified();

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Draft);
});

it('hides submit from teachers', function () {
    $teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');
    $enrollment = app(EnrollmentService::class)->createDraft($this->student, $this->course, null, $this->session, '6000', '0', $this->owner);
    $this->actingAs($teacher, 'institute');
    app(CurrentInstitute::class)->set($this->institute->id);

    Livewire::test(ListEnrollments::class)->assertTableActionHidden('submit', $enrollment);
});

it('never shows another institute enrollments through the Livewire update endpoint', function () {
    $other = Institute::factory()->create();
    $otherStudent = Student::factory()->for($other)->create(['first_name' => 'Stranger']);
    $other->courses()->attach($this->course, ['is_active' => true]);
    app(EnrollmentService::class)->createDraft($otherStudent, $this->course, null, $this->session, '100', '0', Admin::factory()->create());
    app(EnrollmentService::class)->createDraft($this->student, $this->course, null, $this->session, '100', '0', $this->owner);
    $this->actingAs($this->owner, 'institute');

    $html = $this->get('/institute/enrollments')->assertOk()->assertSee('Kiran')->assertDontSee('Stranger')->getContent();

    expect(livewireUpdate($this, $html, ListEnrollments::class, ['tableSearch' => '']))->not->toContain('Stranger');
});
