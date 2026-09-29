<?php

use App\Enums\EnrollmentStatus;
use App\Filament\Admin\Pages\Dues as AdminDues;
use App\Filament\Admin\Resources\FeePayments\Pages\ListFeePayments;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Institute;
use App\Models\Student;
use App\Services\FeeService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-09-30 10:00');
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->a = Institute::factory()->create(['name' => 'Alpha Centre']);
    $this->b = Institute::factory()->create(['name' => 'Beta Centre']);
    $this->fees = app(FeeService::class);
    $this->accounts = Admin::factory()->create()->assignRole('accounts');
    $this->payA = $this->fees->collect(hqEnrollment($this->a), '1000', 'cash', null, '2026-09-30', $this->accounts);
    $this->payB = $this->fees->collect(hqEnrollment($this->b), '2500', 'upi', 'UTR1', '2026-09-29', $this->accounts);
    $cancelled = $this->fees->collect(hqEnrollment($this->b), '700', 'cash', null, '2026-09-29', $this->accounts);
    $this->fees->cancel($cancelled, 'Mistake', $this->accounts);
});

function hqEnrollment(Institute $institute): Enrollment
{
    return Enrollment::create([
        'institute_id' => $institute->id, 'student_id' => Student::factory()->for($institute)->create()->id,
        'course_id' => Course::factory()->create()->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'admission_date' => '2026-09-01', 'course_fee' => 5000, 'discount' => 0, 'net_fee' => 5000, 'status' => EnrollmentStatus::Active,
    ]);
}

it('shows receipts of every institute with an institute filter', function () {
    $this->actingAs($this->accounts, 'admin');

    Livewire::test(ListFeePayments::class)
        ->assertCanSeeTableRecords([$this->payA, $this->payB])
        ->filterTable('institute_id', $this->a->id)
        ->assertCanSeeTableRecords([$this->payA])
        ->assertCanNotSeeTableRecords([$this->payB]);
});

it('totals only valid receipts', function () {
    $this->actingAs($this->accounts, 'admin');

    Livewire::test(ListFeePayments::class)->assertSee('₹3,500.00');
});

it('exports receipts', function () {
    $this->actingAs($this->accounts, 'admin');

    Livewire::test(ListFeePayments::class)->callTableAction('export')->assertHasNoTableActionErrors();

    expect(Export::sole()->total_rows)->toBe(3);
});

it('lists dues across institutes', function () {
    $this->actingAs($this->accounts, 'admin');

    Livewire::test(AdminDues::class)->assertSee('Alpha Centre')->assertSee('Beta Centre');
});

it('keeps support admins out of fee screens', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin')->get('/admin/fee-receipts')->assertForbidden();
});

it('keeps support admins out of the dues page', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin')->get('/admin/dues')->assertForbidden();
});
