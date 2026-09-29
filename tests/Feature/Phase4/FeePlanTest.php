<?php

use App\Enums\EnrollmentStatus;
use App\Enums\InstallmentStatus;
use App\Exceptions\DomainRuleException;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\Student;
use App\Services\FeeService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2026-09-30');
    $this->institute = Institute::factory()->create();
    $this->student = Student::factory()->for($this->institute)->create();
    $this->enrollment = Enrollment::create([
        'institute_id' => $this->institute->id, 'student_id' => $this->student->id,
        'course_id' => Course::factory()->create()->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'admission_date' => '2026-09-01', 'course_fee' => 9000, 'discount' => 0, 'net_fee' => 9000,
        'status' => EnrollmentStatus::Active,
    ]);
    $this->fees = app(FeeService::class);
    $this->admin = Admin::factory()->create();
});

it('treats an enrollment without a plan as one installment due on admission', function () {
    $plan = $this->fees->plan($this->enrollment);

    expect($plan)->toHaveCount(1)
        ->and($plan[0]['amount'])->toBe('9000.00')
        ->and($plan[0]['due_on']->toDateString())->toBe('2026-09-01')
        ->and($plan[0]['status'])->toBe(InstallmentStatus::Overdue);
});

it('saves installments that add up to the net fee', function () {
    $this->fees->setPlan($this->enrollment, [
        ['due_on' => '2026-09-01', 'amount' => '3000'],
        ['due_on' => '2026-10-01', 'amount' => '3000'],
        ['due_on' => '2026-11-01', 'amount' => '3000'],
    ], $this->admin);

    $plan = $this->fees->plan($this->enrollment);
    expect($plan)->toHaveCount(3)
        ->and($plan->pluck('sequence')->all())->toBe([1, 2, 3])
        ->and($plan->pluck('status')->all())->toBe([InstallmentStatus::Overdue, InstallmentStatus::Upcoming, InstallmentStatus::Upcoming]);
});

it('refuses a plan that does not add up, has no rows, or goes back in time', function (array $rows) {
    expect(fn () => $this->fees->setPlan($this->enrollment, $rows, $this->admin))->toThrow(DomainRuleException::class);
})->with([
    'short' => [[['due_on' => '2026-09-01', 'amount' => '8999.99']]],
    'over' => [[['due_on' => '2026-09-01', 'amount' => '5000'], ['due_on' => '2026-10-01', 'amount' => '5000']]],
    'empty' => [[]],
    'backwards' => [[['due_on' => '2026-10-01', 'amount' => '4500'], ['due_on' => '2026-09-01', 'amount' => '4500']]],
    'zero row' => [[['due_on' => '2026-09-01', 'amount' => '9000'], ['due_on' => '2026-10-01', 'amount' => '0']]],
]);

it('summarises net, paid, and due', function () {
    expect($this->fees->summary($this->enrollment))->toBe(['net' => '9000.00', 'paid' => '0.00', 'due' => '9000.00']);
});
