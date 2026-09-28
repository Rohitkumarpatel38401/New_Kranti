<?php

use App\Enums\EnrollmentStatus;
use App\Enums\WalletSource;
use App\Filament\Admin\Resources\Enrollments\Pages\ListEnrollments;
use App\Filament\Admin\Resources\Students\Pages\ListStudents;
use App\Filament\Admin\Widgets\PendingApprovalsWidget;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\Student;
use App\Services\EnrollmentService;
use App\Services\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->manager = Admin::factory()->create()->assignRole('manager');
    $this->main = Institute::factory()->main()->create();
    $this->franchise = Institute::factory()->create(['name' => 'Shiv Franchise']);
    $this->course = Course::factory()->create(['hq_registration_fee' => 100]);
    $this->franchise->courses()->attach($this->course, ['is_active' => true]);
    $this->session = AcademicSession::factory()->create(['is_current' => true]);
    $this->walkIn = Student::factory()->for($this->main)->create(['first_name' => 'Direct', 'admission_source' => 'walk_in']);
    $this->franchiseStudent = Student::factory()->for($this->franchise)->create(['first_name' => 'Branch']);
    app(WalletService::class)->credit($this->franchise, '1000', WalletSource::Recharge, null, 'seed', $this->manager);
});

function pendingEnrollment(): Enrollment
{
    $service = app(EnrollmentService::class);
    $enrollment = $service->createDraft(test()->franchiseStudent, test()->course, null, test()->session, '5000', '0', test()->manager);
    $service->submit($enrollment, test()->manager);

    return $enrollment->fresh();
}

it('lists students of every institute with a Main / Franchise source badge', function () {
    $this->actingAs($this->manager, 'admin');

    Livewire::test(ListStudents::class)
        ->assertCanSeeTableRecords([$this->walkIn, $this->franchiseStudent])
        ->assertSee('Main')
        ->assertSee('Shiv Franchise')
        ->filterTable('institute_id', $this->franchise->id)
        ->assertCanSeeTableRecords([$this->franchiseStudent])
        ->assertCanNotSeeTableRecords([$this->walkIn])
        ->resetTableFilters()
        ->filterTable('network', 'main')
        ->assertCanSeeTableRecords([$this->walkIn])
        ->assertCanNotSeeTableRecords([$this->franchiseStudent]);
});

it('approves and rejects from the HQ enrollment queue', function () {
    $first = pendingEnrollment();
    $this->actingAs($this->manager, 'admin');

    Livewire::test(ListEnrollments::class)
        ->assertCanSeeTableRecords([$first])
        ->callTableAction('approve', $first)
        ->assertHasNoTableActionErrors();

    expect($first->fresh()->status)->toBe(EnrollmentStatus::Active)
        ->and($first->fresh()->enrollment_no)->not->toBeNull();
});

it('requires a reason to reject and refunds the wallet', function () {
    $enrollment = pendingEnrollment();
    $this->actingAs($this->manager, 'admin');

    Livewire::test(ListEnrollments::class)
        ->callTableAction('reject', $enrollment, data: ['reason' => ''])
        ->assertHasTableActionErrors(['reason' => 'required']);

    Livewire::test(ListEnrollments::class)
        ->callTableAction('reject', $enrollment, data: ['reason' => 'Photo missing'])
        ->assertHasNoTableActionErrors();

    expect($enrollment->fresh()->status)->toBe(EnrollmentStatus::Rejected)
        ->and(app(WalletService::class)->balance($this->franchise))->toBe('1000.00');
});

it('hides approval from admins without approve_enrollment', function () {
    $enrollment = pendingEnrollment();
    $this->actingAs(Admin::factory()->create()->givePermissionTo(['ViewAny:Enrollment']), 'admin');

    Livewire::test(ListEnrollments::class)->assertTableActionHidden('approve', $enrollment);
});

it('counts pending approvals on the dashboard', function () {
    pendingEnrollment();
    $this->actingAs($this->manager, 'admin');

    Livewire::test(PendingApprovalsWidget::class)->assertSee('1');
});

it('exports students', function () {
    $this->actingAs($this->manager, 'admin');

    Livewire::test(ListStudents::class)->callTableAction('export')->assertHasNoTableActionErrors();

    $export = Export::sole();
    expect($export->user->is($this->manager))->toBeTrue()
        ->and($export->total_rows)->toBe(2);
});
