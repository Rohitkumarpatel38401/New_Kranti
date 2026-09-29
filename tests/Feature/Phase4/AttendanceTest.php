<?php

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Pages\TakeAttendance;
use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-09-30 11:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create();
    $this->teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');
    $this->receptionist = InstituteUser::factory()->for($this->institute)->create()->assignRole('receptionist');
    $this->course = Course::factory()->create();
    $this->batch = Batch::factory()->create(['institute_id' => $this->institute->id, 'course_id' => $this->course->id, 'name' => 'Morning']);
    $this->e1 = batchEnrollment($this->batch, 'Anil');
    $this->e2 = batchEnrollment($this->batch, 'Bina');
    $this->service = app(AttendanceService::class);
});

function batchEnrollment(Batch $batch, string $name, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
{
    return Enrollment::create([
        'institute_id' => $batch->institute_id,
        'student_id' => Student::factory()->create(['institute_id' => $batch->institute_id, 'first_name' => $name])->id,
        'course_id' => $batch->course_id, 'batch_id' => $batch->id,
        'academic_session_id' => AcademicSession::factory()->create()->id, 'admission_date' => '2026-09-01',
        'course_fee' => 100, 'discount' => 0, 'net_fee' => 100, 'status' => $status,
    ]);
}

it('saves one session per batch and date and updates it on re-save', function () {
    $this->service->save($this->batch, Carbon::today(), [$this->e1->id => 'present', $this->e2->id => 'absent'], $this->teacher);
    $this->service->save($this->batch, Carbon::today(), [$this->e1->id => 'late', $this->e2->id => 'present'], $this->teacher);

    expect(AttendanceSession::count())->toBe(1)
        ->and(AttendanceRecord::count())->toBe(2)
        ->and(AttendanceRecord::where('enrollment_id', $this->e1->id)->value('status'))->toBe(AttendanceStatus::Late)
        ->and(AttendanceSession::sole()->taken_by_user_id)->toBe($this->teacher->id);
});

it('refuses a future date', function () {
    expect(fn () => $this->service->save($this->batch, Carbon::tomorrow(), [$this->e1->id => 'present'], $this->teacher))
        ->toThrow(DomainRuleException::class, 'future');
});

it('refuses students who are not active in this batch', function () {
    $otherBatch = Batch::factory()->create(['institute_id' => $this->institute->id, 'course_id' => $this->course->id]);
    $elsewhere = batchEnrollment($otherBatch, 'Chetan');
    $dropped = batchEnrollment($this->batch, 'Dev', EnrollmentStatus::Dropped);

    expect(fn () => $this->service->save($this->batch, Carbon::today(), [$elsewhere->id => 'present'], $this->teacher))
        ->toThrow(DomainRuleException::class)
        ->and(fn () => $this->service->save($this->batch, Carbon::today(), [$dropped->id => 'present'], $this->teacher))
        ->toThrow(DomainRuleException::class);
});

it('refuses staff without take_attendance and another institute\'s batch', function () {
    $stranger = InstituteUser::factory()->for(Institute::factory())->create()->assignRole('teacher');

    expect(fn () => $this->service->save($this->batch, Carbon::today(), [$this->e1->id => 'present'], $this->receptionist))
        ->toThrow(DomainRuleException::class)
        ->and(fn () => $this->service->save($this->batch, Carbon::today(), [$this->e1->id => 'present'], $stranger))
        ->toThrow(DomainRuleException::class);
});

it('refuses an unknown status', function () {
    expect(fn () => $this->service->save($this->batch, Carbon::today(), [$this->e1->id => 'maybe'], $this->teacher))
        ->toThrow(DomainRuleException::class);
});

it('counts present and late as attended and leaves leave out of the total', function () {
    foreach (['2026-09-26' => 'present', '2026-09-27' => 'late', '2026-09-28' => 'absent', '2026-09-29' => 'leave'] as $date => $status) {
        $this->service->save($this->batch, Carbon::parse($date), [$this->e1->id => $status], $this->teacher);
    }

    expect($this->service->percentage($this->e1))->toBe(66.67)
        ->and($this->service->percentage($this->e2))->toBeNull();
});

it('marks attendance from the page grid', function () {
    $this->actingAs($this->teacher, 'institute');
    app(CurrentInstitute::class)->set($this->institute->id);
    Filament::setCurrentPanel('institute');

    Livewire::test(TakeAttendance::class)
        ->set('batchId', $this->batch->id)
        ->set('date', '2026-09-30')
        ->call('loadRoster')
        ->assertSee('Anil')->assertSee('Bina')
        ->call('markAll', 'present')
        ->set("marks.{$this->e2->id}", 'absent')
        ->call('save')
        ->assertNotified();

    expect(AttendanceRecord::where('enrollment_id', $this->e2->id)->value('status'))->toBe(AttendanceStatus::Absent);
});

it('keeps the page closed to staff without take_attendance', function () {
    $this->actingAs($this->receptionist, 'institute')->get('/institute/take-attendance')->assertForbidden();
});

it('never offers another institute\'s batches on the page', function () {
    Batch::factory()->create(['name' => 'Foreign Batch']);
    $this->actingAs($this->teacher, 'institute');

    $html = $this->get('/institute/take-attendance')->assertOk()->assertSee('Morning')->assertDontSee('Foreign Batch')->getContent();

    expect(livewireUpdate($this, $html, TakeAttendance::class, ['batchId' => $this->batch->id]))->not->toContain('Foreign Batch');
});
