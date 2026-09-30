<?php

use App\Enums\EnrollmentStatus;
use App\Filament\Institute\Widgets\InstituteStatsWidget;
use App\Filament\Institute\Widgets\MonthlyAdmissionsChart;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create();
    $this->other = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
});

function admit(Institute $institute, string $date, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
{
    return Enrollment::create([
        'institute_id' => $institute->id, 'student_id' => Student::factory()->for($institute)->create()->id,
        'course_id' => Course::factory()->create()->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'enrollment_no' => 'KCTI/2026/'.fake()->unique()->numerify('8#####'), 'admission_date' => $date,
        'course_fee' => 1000, 'discount' => 0, 'net_fee' => 1000, 'status' => $status,
    ]);
}

function asOwner(): void
{
    test()->actingAs(test()->owner, 'institute');
    app(CurrentInstitute::class)->set(test()->owner->institute_id);
    Filament::setCurrentPanel('institute');
}

it('counts students and active enrollments of this institute only', function () {
    admit($this->institute, now()->toDateString());
    admit($this->institute, now()->toDateString(), EnrollmentStatus::PendingApproval);
    Student::factory()->for($this->institute)->create();
    admit($this->other, now()->toDateString());
    admit($this->other, now()->toDateString());
    asOwner();

    $stats = (fn () => $this->getStats())->call(new InstituteStatsWidget);
    $byLabel = collect($stats)->mapWithKeys(fn ($s) => [(string) $s->getLabel() => (string) $s->getValue()]);

    expect($byLabel['Students'])->toBe('3')
        ->and($byLabel['Active enrollments'])->toBe('1');
});

it('charts this institute’s admissions for 6 months, zero-filled', function () {
    admit($this->institute, now()->toDateString());
    admit($this->institute, now()->subMonth()->toDateString());
    admit($this->other, now()->toDateString());
    asOwner();

    $data = (fn () => $this->getData())->call(new MonthlyAdmissionsChart);

    expect($data['labels'])->toHaveCount(6)
        ->and(array_sum($data['datasets'][0]['data']))->toBe(2)
        ->and(last($data['datasets'][0]['data']))->toBe(1);
});

it('renders the dashboard for an empty institute', function () {
    asOwner();

    $this->get('/institute')->assertOk();
    Livewire::test(InstituteStatsWidget::class)->assertOk();
});
