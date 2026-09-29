<?php

use App\Enums\EnrollmentStatus;
use App\Enums\WalletSource;
use App\Filament\Admin\Resources\AttendanceSessions\Pages\ListAttendanceSessions as AdminAttendance;
use App\Filament\Institute\Resources\AttendanceSessions\Pages\ListAttendanceSessions as InstituteAttendance;
use App\Filament\Institute\Widgets\InstituteStatsWidget;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\AttendanceSession;
use App\Models\Batch;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\FeeService;
use App\Services\WalletService;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-09-30 12:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $this->batch = Batch::factory()->create(['institute_id' => $this->institute->id, 'name' => 'Evening']);
    $this->enrollment = Enrollment::create([
        'institute_id' => $this->institute->id, 'student_id' => Student::factory()->for($this->institute)->create()->id,
        'course_id' => $this->batch->course_id, 'batch_id' => $this->batch->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'admission_date' => '2026-09-01', 'course_fee' => 5000, 'discount' => 0, 'net_fee' => 5000, 'status' => EnrollmentStatus::Active,
    ]);
    app(FeeService::class)->collect($this->enrollment, '1200', 'cash', null, '2026-09-30', $this->owner);
    app(AttendanceService::class)->save($this->batch, Carbon::today(), [$this->enrollment->id => 'present'], $this->owner);
});

function instituteContext(InstituteUser $user): void
{
    test()->actingAs($user, 'institute');
    app(CurrentInstitute::class)->set($user->institute_id);
    Filament::setCurrentPanel('institute');
}

it('shows the institute its own figures and a low-balance warning', function () {
    $other = Institute::factory()->create();
    app(WalletService::class)->credit($other, '99999', WalletSource::Recharge, null, 'x', Admin::factory()->create());
    app(WalletService::class)->credit($this->institute, '300', WalletSource::Recharge, null, 'x', Admin::factory()->create());
    instituteContext($this->owner);

    Livewire::test(InstituteStatsWidget::class)
        ->assertSee('₹300.00')
        ->assertSee('Low balance')
        ->assertSee('₹1,200.00')
        ->assertSee('₹3,800.00')
        ->assertDontSee('99,999');
});

it('lists own attendance history in the institute panel', function () {
    AttendanceSession::create(['institute_id' => Institute::factory()->create()->id, 'batch_id' => Batch::factory()->create(['name' => 'Foreign'])->id, 'date' => '2026-09-29']);
    instituteContext($this->owner);

    Livewire::test(InstituteAttendance::class)
        ->assertCanSeeTableRecords(AttendanceSession::where('institute_id', $this->institute->id)->get())
        ->assertSee('Evening')
        ->assertDontSee('Foreign');
});

it('lets HQ filter attendance by institute', function () {
    $other = Institute::factory()->create();
    $foreign = AttendanceSession::create(['institute_id' => $other->id, 'batch_id' => Batch::factory()->create(['institute_id' => $other->id])->id, 'date' => '2026-09-29']);
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');

    Livewire::test(AdminAttendance::class)
        ->assertCanSeeTableRecords([$foreign])
        ->filterTable('institute_id', $this->institute->id)
        ->assertCanNotSeeTableRecords([$foreign]);
});
