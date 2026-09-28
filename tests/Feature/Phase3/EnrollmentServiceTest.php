<?php

use App\Enums\EnrollmentStatus;
use App\Enums\WalletSource;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\WalletTransaction;
use App\Services\EnrollmentService;
use App\Services\WalletService;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->institute = Institute::factory()->create();
    $this->user = InstituteUser::factory()->owner()->for($this->institute)->create();
    $this->admin = Admin::factory()->create();
    $this->course = Course::factory()->create(['hq_registration_fee' => 400]);
    $this->institute->courses()->attach($this->course, ['is_active' => true]);
    $this->session = AcademicSession::factory()->create(['is_current' => true]);
    $this->student = Student::factory()->for($this->institute)->create();
    $this->student->personal()->create(['dob' => '2004-03-09', 'gender' => 'male']);
    $this->wallets = app(WalletService::class);
    $this->wallets->credit($this->institute, '1000', WalletSource::Recharge, null, 'seed', $this->admin);
    $this->service = app(EnrollmentService::class);
});

function draft(array $overrides = []): Enrollment
{
    return test()->service->createDraft(
        $overrides['student'] ?? test()->student,
        $overrides['course'] ?? test()->course,
        $overrides['batch'] ?? null,
        test()->session,
        $overrides['course_fee'] ?? '8000',
        $overrides['discount'] ?? '500',
        $overrides['by'] ?? test()->user,
    );
}

it('creates a draft with the net fee', function () {
    $enrollment = draft();

    expect($enrollment->status)->toBe(EnrollmentStatus::Draft)
        ->and($enrollment->net_fee)->toBe('7500.00')
        ->and($enrollment->institute_id)->toBe($this->institute->id)
        ->and($enrollment->enrollment_no)->toBeNull()
        ->and($enrollment->histories)->toHaveCount(1);
});

it('refuses a course the institute may not teach, a foreign batch, or a discount above the fee', function () {
    $foreignBatch = Batch::factory()->create(['course_id' => $this->course->id]);

    expect(fn () => draft(['course' => Course::factory()->create()]))->toThrow(DomainRuleException::class, 'not authorized')
        ->and(fn () => draft(['batch' => $foreignBatch]))->toThrow(DomainRuleException::class, 'batch')
        ->and(fn () => draft(['discount' => '9000']))->toThrow(DomainRuleException::class, 'discount');
});

it('refuses a student of another institute', function () {
    $foreign = Student::factory()->for(Institute::factory())->create();

    expect(fn () => draft(['student' => $foreign]))->toThrow(DomainRuleException::class);
});

it('refuses a second open enrollment in the same course', function () {
    draft();

    expect(fn () => draft())->toThrow(DomainRuleException::class, 'already');
});

it('debits the HQ fee on submit and waits for approval', function () {
    $enrollment = draft();

    $this->service->submit($enrollment, $this->user);

    $enrollment->refresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::PendingApproval)
        ->and($enrollment->hq_fee_snapshot)->toBe('400.00')
        ->and($enrollment->debitTransaction->amount)->toBe('400.00')
        ->and($enrollment->submitted_at)->not->toBeNull()
        ->and($this->wallets->balance($this->institute))->toBe('600.00');
});

it('refuses submit with too little balance and changes nothing', function () {
    $this->course->update(['hq_registration_fee' => 5000]);
    $enrollment = draft();

    expect(fn () => $this->service->submit($enrollment, $this->user))->toThrow(DomainRuleException::class, 'Insufficient');

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Draft)
        ->and($this->wallets->balance($this->institute))->toBe('1000.00');
});

it('never debits the wallet-exempt main institute', function () {
    $main = Institute::factory()->main()->create();
    $mainUser = InstituteUser::factory()->owner()->for($main)->create();
    $student = Student::factory()->for($main)->create();
    $student->personal()->create(['dob' => '2001-01-01', 'gender' => 'female']);

    $enrollment = draft(['student' => $student, 'by' => $mainUser]);
    $this->service->submit($enrollment, $mainUser);

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::PendingApproval)
        ->and($enrollment->fresh()->debit_transaction_id)->toBeNull()
        ->and(WalletTransaction::where('institute_id', $main->id)->count())->toBe(0);
});

it('lets the main institute teach every active course', function () {
    $main = Institute::factory()->main()->create();

    expect($main->authorizedCourses()->pluck('courses.id')->all())->toContain($this->course->id);
});

it('approves with an enrollment number and opens the student login with the DOB password', function () {
    $enrollment = draft();
    $this->service->submit($enrollment, $this->user);

    $this->service->approve($enrollment, $this->admin);

    $enrollment->refresh();
    $student = $this->student->fresh();
    expect($enrollment->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->enrollment_no)->toBe('KCTI/'.now()->year.'/000001')
        ->and($enrollment->approved_by_admin_id)->toBe($this->admin->id)
        ->and($student->can_login)->toBeTrue()
        ->and($student->must_change_password)->toBeTrue()
        ->and(Hash::check('09032004', $student->password))->toBeTrue();
});

it('refunds exactly once on rejection', function () {
    $enrollment = draft();
    $this->service->submit($enrollment, $this->user);

    $this->service->reject($enrollment, 'Documents unclear', $this->admin);

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Rejected)
        ->and($enrollment->fresh()->rejection_reason)->toBe('Documents unclear')
        ->and($this->wallets->balance($this->institute))->toBe('1000.00')
        ->and(fn () => $this->service->reject($enrollment, 'again', $this->admin))->toThrow(DomainRuleException::class)
        ->and(fn () => $this->service->approve($enrollment, $this->admin))->toThrow(DomainRuleException::class);

    expect($this->wallets->balance($this->institute))->toBe('1000.00');
});

it('can reopen a rejected enrollment and debit again on resubmit', function () {
    $enrollment = draft();
    $this->service->submit($enrollment, $this->user);
    $this->service->reject($enrollment, 'Fix fee', $this->admin);

    $this->service->reopen($enrollment, $this->user);
    $this->service->submit($enrollment, $this->user);

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::PendingApproval)
        ->and($this->wallets->balance($this->institute))->toBe('600.00');
});

it('records every transition and refuses invalid ones', function () {
    $enrollment = draft();

    expect(fn () => $this->service->approve($enrollment, $this->admin))->toThrow(DomainRuleException::class)
        ->and(fn () => $this->service->reopen($enrollment, $this->user))->toThrow(DomainRuleException::class);

    $this->service->submit($enrollment, $this->user);
    $this->service->approve($enrollment, $this->admin);
    $this->service->drop($enrollment, 'Moved city', $this->user);

    expect($enrollment->fresh()->histories->pluck('to_status')->map->value->all())
        ->toBe(['draft', 'pending_approval', 'active', 'dropped']);
});

it('cancels a draft', function () {
    $enrollment = draft();

    $this->service->cancel($enrollment, $this->user);

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Cancelled);
    draft();
});
