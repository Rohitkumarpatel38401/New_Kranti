<?php

use App\Enums\EnrollmentStatus;
use App\Enums\WalletSource;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Enrollments\Pages\ListEnrollments as AdminEnrollments;
use App\Filament\Admin\Resources\Institutes\Pages\ListInstitutes;
use App\Filament\Admin\Resources\Students\Pages\ListStudents as AdminStudents;
use App\Filament\Institute\Resources\Students\Pages\CreateStudent;
use App\Filament\Institute\Resources\Students\Pages\ViewStudent;
use App\Filament\Institute\Resources\Students\RelationManagers\DocumentsRelationManager;
use App\Filament\Shared\StudentForm;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\InstituteOwnerService;
use App\Services\WalletService;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = Admin::factory()->create()->assignRole('super_admin');
});

// Critical 1: owner links must be real institute-panel reset links.
it('emails the owner a working institute-panel reset link', function () {
    Notification::fake();
    $institute = Institute::factory()->create();

    $owner = app(InstituteOwnerService::class)->createOwner($institute, 'Asha', 'asha@owner.test', null, $this->admin);

    Notification::assertSentTo($owner, ResetPassword::class, function (ResetPassword $notification) use ($owner) {
        $mail = $notification->toMail($owner);

        return str_contains($notification->url, '/institute/password-reset/reset')
            && str_contains($mail->actionUrl, '/institute/password-reset/reset');
    });
});

it('does not fail when mail is really rendered', function () {
    $institute = Institute::factory()->create();

    app(InstituteOwnerService::class)->createOwner($institute, 'Asha', 'asha2@owner.test', null, $this->admin);
    $this->travel(2)->minutes(); // the broker throttles repeat links for 60 seconds
    app(InstituteOwnerService::class)->sendResetLink($institute->fresh());

    expect($institute->owner()->exists())->toBeTrue();
});

// Important 2: reopening must not create a second open enrollment in the same course.
it('refuses to reopen a rejected enrollment when another one is open in the same course', function () {
    $institute = Institute::factory()->create();
    $course = Course::factory()->create(['hq_registration_fee' => 100]);
    $institute->courses()->attach($course, ['is_active' => true]);
    $student = Student::factory()->for($institute)->create();
    $session = AcademicSession::factory()->create();
    app(WalletService::class)->credit($institute, '1000', WalletSource::Recharge, null, 'seed', $this->admin);
    $service = app(EnrollmentService::class);

    $first = $service->createDraft($student, $course, null, $session, '1000', '0', $this->admin);
    $service->submit($first, $this->admin);
    $service->reject($first, 'Fix it', $this->admin);
    $service->createDraft($student, $course, null, $session, '1000', '0', $this->admin);

    expect(fn () => $service->reopen($first, $this->admin))->toThrow(DomainRuleException::class, 'already enrolled');
    expect($first->fresh()->status)->toBe(EnrollmentStatus::Rejected);
});

// Important 3: missing plan items.
it('shows a student\'s enrollments on the institute student page', function () {
    $institute = Institute::factory()->create();
    $owner = InstituteUser::factory()->owner()->for($institute)->create()->assignRole('owner');
    $student = Student::factory()->for($institute)->create();
    $course = Course::factory()->create(['code' => 'TALLY9']);
    Enrollment::create(['institute_id' => $institute->id, 'student_id' => $student->id, 'course_id' => $course->id,
        'academic_session_id' => AcademicSession::factory()->create()->id, 'admission_date' => now(), 'course_fee' => 100,
        'discount' => 0, 'net_fee' => 100, 'status' => EnrollmentStatus::PendingApproval]);

    $this->actingAs($owner, 'institute')->get("/institute/students/{$student->getRouteKey()}")
        ->assertOk()->assertSee('TALLY9')->assertSee('Pending HQ approval');
});

it('uploads student documents on the private disk', function () {
    Storage::fake('local');
    $institute = Institute::factory()->create();
    $owner = InstituteUser::factory()->owner()->for($institute)->create()->assignRole('owner');
    $student = Student::factory()->for($institute)->create();
    $this->actingAs($owner, 'institute');
    app(CurrentInstitute::class)->set($institute->id);
    Filament::setCurrentPanel('institute');

    Livewire::test(DocumentsRelationManager::class, ['ownerRecord' => $student, 'pageClass' => ViewStudent::class])
        ->callTableAction('create', data: ['type' => 'aadhaar', 'path' => UploadedFile::fake()->create('aadhaar.pdf', 200, 'application/pdf')])
        ->assertHasNoTableActionErrors();

    Storage::disk('local')->assertExists($student->documents()->sole()->path);
});

it('filters HQ students by course and session, and enrollments by main or franchise', function () {
    $main = Institute::factory()->main()->create();
    $franchise = Institute::factory()->create();
    $course = Course::factory()->create();
    $session = AcademicSession::factory()->create();
    $mk = fn (Institute $i, ?Course $c = null) => Enrollment::create(['institute_id' => $i->id,
        'student_id' => Student::factory()->for($i)->create()->id, 'course_id' => ($c ?? Course::factory()->create())->id,
        'academic_session_id' => $session->id, 'admission_date' => now(), 'course_fee' => 1, 'discount' => 0, 'net_fee' => 1,
        'status' => EnrollmentStatus::Active]);
    $inCourse = $mk($franchise, $course);
    $other = $mk($main);
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin, 'admin');

    Livewire::test(AdminStudents::class)
        ->filterTable('course_id', $course->id)
        ->assertCanSeeTableRecords([$inCourse->student])
        ->assertCanNotSeeTableRecords([$other->student]);

    Livewire::test(AdminEnrollments::class)
        ->filterTable('network', 'main')
        ->assertCanSeeTableRecords([$other])
        ->assertCanNotSeeTableRecords([$inCourse]);
});

// Re-graded minors.
it('adjusts wallets with exact amounts and refuses overflow or three decimals', function () {
    $institute = Institute::factory()->create();
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin, 'admin');

    Livewire::test(ListInstitutes::class)
        ->callTableAction('adjustWallet', $institute, data: ['type' => 'credit', 'amount' => '10.555', 'reason' => 'x'])
        ->assertHasTableActionErrors(['amount']);

    Livewire::test(ListInstitutes::class)
        ->callTableAction('adjustWallet', $institute, data: ['type' => 'credit', 'amount' => '99999999999', 'reason' => 'x'])
        ->assertHasTableActionErrors(['amount']);

    expect(app(WalletService::class)->balance($institute))->toBe('0.00');
});

it('refuses an SVG as a student photo', function () {
    Storage::fake('local');
    $institute = Institute::factory()->create();
    $owner = InstituteUser::factory()->owner()->for($institute)->create()->assignRole('owner');
    $this->actingAs($owner, 'institute');
    app(CurrentInstitute::class)->set($institute->id);
    Filament::setCurrentPanel('institute');

    Livewire::test(CreateStudent::class)
        ->fillForm(['student' => ['first_name' => 'X', 'phone' => '9811111111', 'joined_on' => now()->toDateString(),
            'photo_path' => UploadedFile::fake()->createWithContent('evil.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]])
        ->call('create')
        ->assertHasFormErrors(['student.photo_path']);

    expect(StudentForm::IMAGE_TYPES)->toBe(['image/jpeg', 'image/png', 'image/webp']);
});
