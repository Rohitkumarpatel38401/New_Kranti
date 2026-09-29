<?php

use App\Enums\EnrollmentStatus;
use App\Enums\FeePaymentStatus;
use App\Filament\Institute\Pages\Dues;
use App\Filament\Institute\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Institute\Resources\FeePayments\Pages\ListFeePayments;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeePayment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\FeeService;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-09-30 10:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('institute');
    $this->institute = Institute::factory()->create(['code' => 'KCTI0003']);
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $this->accountant = InstituteUser::factory()->for($this->institute)->create()->assignRole('accountant');
    $this->teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');
    $this->enrollment = activeEnrollment($this->institute, 'Meera');
});

function activeEnrollment(Institute $institute, string $name, string $net = '6000'): Enrollment
{
    return Enrollment::create([
        'institute_id' => $institute->id, 'student_id' => Student::factory()->for($institute)->create(['first_name' => $name])->id,
        'course_id' => Course::factory()->create()->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'enrollment_no' => 'KCTI/2026/'.fake()->unique()->numerify('######'),
        'admission_date' => '2026-09-01', 'course_fee' => $net, 'discount' => 0, 'net_fee' => $net, 'status' => EnrollmentStatus::Active,
    ]);
}

function actAs(InstituteUser $user): void
{
    test()->actingAs($user, 'institute');
    app(CurrentInstitute::class)->set($user->institute_id);
}

it('collects a fee from the enrollment row and issues a receipt', function () {
    actAs($this->owner);

    Livewire::test(ListEnrollments::class)
        ->callTableAction('collectFee', $this->enrollment, data: ['amount' => 2000, 'mode' => 'upi', 'reference_no' => 'UPI123', 'paid_on' => '2026-09-30'])
        ->assertHasNoTableActionErrors()
        ->assertNotified();

    $payment = FeePayment::sole();
    expect($payment->receipt_no)->toBe('KCTI0003/R/26/00001')
        ->and($payment->amount)->toBe('2000.00');
});

it('saves a fee plan from the enrollment row', function () {
    actAs($this->owner);

    Livewire::test(ListEnrollments::class)
        ->callTableAction('feePlan', $this->enrollment, data: ['installments' => [
            ['due_on' => '2026-09-01', 'amount' => 3000],
            ['due_on' => '2026-10-01', 'amount' => 3000],
        ]])
        ->assertHasNoTableActionErrors();

    expect($this->enrollment->installments()->count())->toBe(2);
});

it('hides fee collection from teachers', function () {
    actAs($this->teacher);

    Livewire::test(ListEnrollments::class)->assertTableActionHidden('collectFee', $this->enrollment);
});

it('lets the accountant cancel a receipt with a reason', function () {
    $payment = app(FeeService::class)->collect($this->enrollment, '1000', 'cash', null, '2026-09-30', $this->owner);
    actAs($this->accountant);

    Livewire::test(ListFeePayments::class)
        ->assertCanSeeTableRecords([$payment])
        ->callTableAction('cancel', $payment, data: ['reason' => 'Entered twice'])
        ->assertHasNoTableActionErrors();

    expect($payment->fresh()->status)->toBe(FeePaymentStatus::Cancelled);
});

it('lists dues with overdue marking for the own institute only', function () {
    $other = Institute::factory()->create();
    activeEnrollment($other, 'Outsider');
    $paidUp = activeEnrollment($this->institute, 'Paidup', '1000');
    app(FeeService::class)->collect($paidUp, '1000', 'cash', null, '2026-09-30', $this->owner);
    $this->actingAs($this->owner, 'institute');

    $html = $this->get('/institute/dues')->assertOk()->assertSee('Meera')->assertSee('Overdue')
        ->assertDontSee('Outsider')->assertDontSee('Paidup')->getContent();

    expect(livewireUpdate($this, $html, Dues::class, ['tableSearch' => '']))->not->toContain('Outsider');
});
