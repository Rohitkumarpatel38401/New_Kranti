<?php

use App\Enums\EnrollmentStatus;
use App\Enums\FeePaymentStatus;
use App\Enums\InstallmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeePayment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\FeeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-30 10:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create(['code' => 'KCTI0007']);
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $this->accountant = InstituteUser::factory()->for($this->institute)->create()->assignRole('accountant');
    $this->teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');
    $this->enrollment = enrollmentFor($this->institute);
    $this->fees = app(FeeService::class);
});

function enrollmentFor(Institute $institute, EnrollmentStatus $status = EnrollmentStatus::Active, string $net = '9000'): Enrollment
{
    return Enrollment::create([
        'institute_id' => $institute->id, 'student_id' => Student::factory()->for($institute)->create()->id,
        'course_id' => Course::factory()->create()->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'enrollment_no' => 'KCTI/2026/'.fake()->unique()->numerify('######'),
        'admission_date' => '2026-09-01', 'course_fee' => $net, 'discount' => 0, 'net_fee' => $net, 'status' => $status,
    ]);
}

function pay(string $amount, $by = null, ?Enrollment $enrollment = null): FeePayment
{
    return test()->fees->collect($enrollment ?? test()->enrollment, $amount, 'cash', null, '2026-09-30', $by ?? test()->owner);
}

it('issues numbered receipts per institute and year', function () {
    $first = pay('1000');
    $second = pay('500');

    expect($first->receipt_no)->toBe('KCTI0007/R/26/00001')
        ->and($second->receipt_no)->toBe('KCTI0007/R/26/00002')
        ->and($first->received_by_user_id)->toBe($this->owner->id)
        ->and($first->student_id)->toBe($this->enrollment->student_id)
        ->and($this->fees->summary($this->enrollment))->toBe(['net' => '9000.00', 'paid' => '1500.00', 'due' => '7500.00']);
});

it('applies payments to installments in order', function () {
    $this->fees->setPlan($this->enrollment, [
        ['due_on' => '2026-09-01', 'amount' => '3000'],
        ['due_on' => '2026-10-15', 'amount' => '3000'],
        ['due_on' => '2026-11-15', 'amount' => '3000'],
    ], $this->owner);
    pay('4000');

    expect($this->fees->plan($this->enrollment)->pluck('status')->all())
        ->toBe([InstallmentStatus::Paid, InstallmentStatus::Partial, InstallmentStatus::Upcoming]);
});

it('refuses more than the due, zero, or bad amounts', function (string $amount) {
    expect(fn () => pay($amount))->toThrow(DomainRuleException::class);
})->with(['9000.01', '0', '-1', '1.005']);

it('refuses payments on enrollments that are not active or completed', function (EnrollmentStatus $status) {
    expect(fn () => pay('100', null, enrollmentFor($this->institute, $status)))->toThrow(DomainRuleException::class);
})->with([EnrollmentStatus::Draft, EnrollmentStatus::PendingApproval, EnrollmentStatus::Rejected, EnrollmentStatus::Dropped, EnrollmentStatus::Cancelled]);

it('refuses staff without collect_fee and people from another institute', function () {
    $stranger = InstituteUser::factory()->owner()->for(Institute::factory())->create()->assignRole('owner');

    expect(fn () => pay('100', $this->teacher))->toThrow(DomainRuleException::class)
        ->and(fn () => pay('100', $stranger))->toThrow(DomainRuleException::class);
});

it('lets HQ record a payment', function () {
    $payment = pay('100', Admin::factory()->create());

    expect($payment->received_by_admin_id)->not->toBeNull();
});

it('cancels a receipt once, with a reason, restoring the due', function () {
    $payment = pay('2000');

    expect(fn () => $this->fees->cancel($payment, ' ', $this->accountant))->toThrow(DomainRuleException::class)
        ->and(fn () => $this->fees->cancel($payment, 'Wrong student', $this->teacher))->toThrow(DomainRuleException::class);

    $this->fees->cancel($payment, 'Wrong student', $this->accountant);

    expect($payment->fresh()->status)->toBe(FeePaymentStatus::Cancelled)
        ->and($payment->fresh()->cancel_reason)->toBe('Wrong student')
        ->and($this->fees->summary($this->enrollment)['due'])->toBe('9000.00')
        ->and(fn () => $this->fees->cancel($payment, 'again', $this->accountant))->toThrow(DomainRuleException::class);
});

it('never deletes receipts', function () {
    expect(fn () => pay('100')->delete())->toThrow(LogicException::class);
});

it('downloads the receipt PDF for the institute and HQ, and 404s for another institute', function () {
    $payment = pay('1000');
    $stranger = InstituteUser::factory()->owner()->for(Institute::factory())->create()->assignRole('owner');

    $this->actingAs($this->owner, 'institute')->get(route('receipts.show', $payment))
        ->assertOk()->assertHeader('content-type', 'application/pdf');

    $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin')->get(route('receipts.show', $payment))->assertOk();
});

it('forbids another institute from downloading a receipt', function () {
    $payment = pay('1000');
    $stranger = InstituteUser::factory()->owner()->for(Institute::factory())->create()->assignRole('owner');

    $this->actingAs($stranger, 'institute')->get(route('receipts.show', $payment))->assertForbidden();
});

it('forbids guests from receipts', function () {
    $this->get(route('receipts.show', pay('1000')))->assertRedirect();
});

it('marks a cancelled receipt as cancelled in the printable view', function () {
    $payment = pay('1000');
    $this->fees->cancel($payment, 'Duplicate', $this->accountant);

    $html = view('pdf.receipt', ['payment' => $payment->fresh(), 'summary' => $this->fees->summary($this->enrollment)])->render();

    expect($html)->toContain('CANCELLED')->toContain('KCTI0007/R/26/00001');
});
