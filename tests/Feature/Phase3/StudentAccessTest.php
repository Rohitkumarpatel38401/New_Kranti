<?php

use App\Enums\EnrollmentStatus;
use App\Enums\InstituteStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Courses\Pages\ListCourses;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FranchiseApplication;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\AcademicSessionService;
use App\Services\FranchiseOnboardingService;
use App\Services\NumberSequenceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

function studentWithEnrollment(EnrollmentStatus $status, ?Institute $institute = null): Student
{
    $student = Student::factory()->canLogin()->for($institute ?? Institute::factory())->create();
    Enrollment::create([
        'institute_id' => $student->institute_id, 'student_id' => $student->id,
        'course_id' => Course::factory()->create()->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'admission_date' => now()->toDateString(), 'course_fee' => 100, 'discount' => 0, 'net_fee' => 100, 'status' => $status,
    ]);

    return $student;
}

it('lets students with an active or completed enrollment into the portal', function (EnrollmentStatus $status) {
    $this->actingAs(studentWithEnrollment($status), 'student')->get('/student')->assertOk();
})->with([EnrollmentStatus::Active, EnrollmentStatus::Completed]);

it('keeps students whose enrollment is not approved out of the portal', function (EnrollmentStatus $status) {
    $this->actingAs(studentWithEnrollment($status), 'student')->get('/student')->assertForbidden();
})->with([EnrollmentStatus::Draft, EnrollmentStatus::PendingApproval, EnrollmentStatus::Rejected]);

it('keeps students of a suspended institute out even with an active enrollment', function () {
    $student = studentWithEnrollment(EnrollmentStatus::Active, Institute::factory()->create(['status' => InstituteStatus::Suspended]));

    $this->actingAs($student, 'student')->get('/student')->assertForbidden();
});

it('makes a session current under a lock so only one stays current', function () {
    $a = AcademicSession::factory()->create(['is_current' => true]);
    $b = AcademicSession::factory()->create();

    app(AcademicSessionService::class)->makeCurrent($b);

    expect(AcademicSession::where('is_current', true)->pluck('id')->all())->toBe([$b->id]);
});

it('turns a concurrent owner-email clash during conversion into a clean message', function () {
    app(NumberSequenceService::class)->syncAtLeast('institute_code', 1);
    $this->seed(RolesAndPermissionsSeeder::class);
    $application = FranchiseApplication::factory()->create(['email' => 'race@owner.test']);

    // Simulate the other request winning between our check and our insert.
    InstituteUser::creating(function (InstituteUser $user) {
        if ($user->email === 'race@owner.test' && ! InstituteUser::withTrashed()->where('email', 'race@owner.test')->exists()) {
            InstituteUser::withoutEvents(fn () => InstituteUser::factory()->create(['email' => 'race@owner.test']));
        }
    });

    expect(fn () => app(FranchiseOnboardingService::class)->convert($application, Admin::factory()->create()))
        ->toThrow(DomainRuleException::class, 'already has an institute login');
});

it('finds courses by their Hindi name', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $course = Course::factory()->create(['name' => ['en' => 'Typing', 'hi' => 'टाइपिंग']]);
    $other = Course::factory()->create(['name' => ['en' => 'Tally', 'hi' => 'टैली']]);
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');

    Livewire::test(ListCourses::class)
        ->searchTable('टाइपिंग')
        ->assertCanSeeTableRecords([$course])
        ->assertCanNotSeeTableRecords([$other]);
});
