<?php

use App\Enums\EnrollmentStatus;
use App\Filament\Institute\Pages\Dues;
use App\Filament\Institute\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Institute\Widgets\InstituteStatsWidget;
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
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $this->teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');
    $this->fees = app(FeeService::class);
    Filament::setCurrentPanel('institute');
});

function feeEnrollment(Institute $institute, string $name, string $admission = '2026-09-01', string $net = '6000'): Enrollment
{
    return Enrollment::create([
        'institute_id' => $institute->id, 'student_id' => Student::factory()->for($institute)->create(['first_name' => $name])->id,
        'course_id' => Course::factory()->create()->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'admission_date' => $admission, 'course_fee' => $net, 'discount' => 0, 'net_fee' => $net, 'status' => EnrollmentStatus::Active,
    ]);
}

function in_institute(InstituteUser $user): void
{
    test()->actingAs($user, 'institute');
    app(CurrentInstitute::class)->set($user->institute_id);
}

// Important 1
it('filters overdue exactly like the badge: unpaid past instalments and no-plan enrollments past admission', function () {
    $noPlan = feeEnrollment($this->institute, 'Noplan');
    $paidPast = feeEnrollment($this->institute, 'Paidpast');
    $this->fees->setPlan($paidPast, [['due_on' => '2026-09-05', 'amount' => '3000'], ['due_on' => '2026-12-01', 'amount' => '3000']], $this->owner);
    $this->fees->collect($paidPast, '3000', 'cash', null, '2026-09-05', $this->owner);
    $futureAdmission = feeEnrollment($this->institute, 'Future', '2026-10-15');
    in_institute($this->owner);

    Livewire::test(Dues::class)
        ->filterTable('overdue', true)
        ->assertCanSeeTableRecords([$noPlan])
        ->assertCanNotSeeTableRecords([$paidPast, $futureAdmission]);
});

// Important 2
it('does not poll the dashboard widget and totals dues with exact money', function () {
    feeEnrollment($this->institute, 'A', net: '1000.10');
    feeEnrollment($this->institute, 'B', net: '2000.20');
    in_institute($this->owner);

    expect((fn () => $this->getPollingInterval())->call(new InstituteStatsWidget))->toBeNull();
    Livewire::test(InstituteStatsWidget::class)->assertSee('₹3,000.30');
});

// Important 3
it('keeps fee receipts and receipt PDFs away from teachers', function () {
    $payment = $this->fees->collect(feeEnrollment($this->institute, 'X'), '100', 'cash', null, '2026-09-30', $this->owner);

    $this->actingAs($this->teacher, 'institute')->get('/institute/fee-receipts')->assertForbidden();
    $this->actingAs($this->teacher, 'institute')->get(route('receipts.show', $payment))->assertForbidden();
});

it('shows teachers no money figures on the dashboard', function () {
    in_institute($this->teacher);

    Livewire::test(InstituteStatsWidget::class)->assertDontSee('Fees collected')->assertDontSee('Wallet balance')->assertSee('attendance');
});

// Re-graded minor (money)
it('refuses more than two decimals and allows paise-sized dues when collecting', function () {
    $enrollment = feeEnrollment($this->institute, 'Paise', net: '100.50');
    $this->fees->collect($enrollment, '100', 'cash', null, '2026-09-30', $this->owner);
    in_institute($this->owner);

    Livewire::test(ListEnrollments::class)
        ->callTableAction('collectFee', $enrollment, data: ['amount' => '0.499', 'mode' => 'cash', 'paid_on' => '2026-09-30'])
        ->assertHasTableActionErrors(['amount']);

    Livewire::test(ListEnrollments::class)
        ->callTableAction('collectFee', $enrollment, data: ['amount' => '0.50', 'mode' => 'cash', 'paid_on' => '2026-09-30'])
        ->assertHasNoTableActionErrors();

    expect(FeePayment::where('enrollment_id', $enrollment->id)->sum('amount'))->toEqual(100.5);
});
