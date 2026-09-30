<?php

use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Enums\EnrollmentStatus;
use App\Enums\InstituteStatus;
use App\Enums\ResultStatus;
use App\Enums\WalletSource;
use App\Filament\Admin\Widgets\EnrollmentsBySourceChart;
use App\Filament\Admin\Widgets\HqStatsWidget;
use App\Filament\Admin\Widgets\PendingApprovalsWidget;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\ExamResult;
use App\Models\Institute;
use App\Models\Student;
use App\Services\WalletService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->main = Institute::factory()->main()->create();
    $this->franchise = Institute::factory()->create();
});

function dashEnrollment(Institute $institute, string $date, EnrollmentStatus $status = EnrollmentStatus::Active): Enrollment
{
    return Enrollment::create([
        'institute_id' => $institute->id, 'student_id' => Student::factory()->for($institute)->create()->id,
        'course_id' => Course::factory()->create()->id, 'academic_session_id' => AcademicSession::factory()->create()->id,
        'enrollment_no' => 'KCTI/2026/'.fake()->unique()->numerify('7#####'), 'admission_date' => $date,
        'course_fee' => 1000, 'discount' => 0, 'net_fee' => 1000, 'status' => $status,
    ]);
}

it('shows HQ numbers for this month', function () {
    Institute::factory()->create(['status' => InstituteStatus::Pending]);
    dashEnrollment($this->main, now()->toDateString());
    dashEnrollment($this->franchise, now()->toDateString());
    dashEnrollment($this->franchise, now()->subMonths(2)->toDateString());
    app(WalletService::class)->credit($this->franchise, '2500.00', WalletSource::Adjustment, null, 'test', Admin::factory()->create());

    $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');

    Livewire::test(HqStatsWidget::class)
        ->assertSee('Active institutes')->assertSee('1 pending')
        ->assertSee('Enrollments this month')
        ->assertSee('₹2,500.00')
        ->assertSee('Active students');
});

it('adds submitted results and new enquiries to the approval queue', function () {
    $e = dashEnrollment($this->franchise, now()->toDateString());
    ExamResult::create(['institute_id' => $this->franchise->id, 'enrollment_id' => $e->id, 'exam_date' => now()->toDateString(), 'status' => ResultStatus::Submitted]);
    Enquiry::create(['institute_id' => $this->main->id, 'type' => EnquiryType::Contact, 'name' => 'A', 'phone' => '9876543210', 'status' => EnquiryStatus::New]);

    $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');

    Livewire::test(PendingApprovalsWidget::class)->assertSee('Results to review')->assertSee('New enquiries');
});

it('charts 12 months of enrollments split into main and franchise, zero-filled', function () {
    dashEnrollment($this->main, now()->toDateString());
    dashEnrollment($this->franchise, now()->toDateString());
    dashEnrollment($this->franchise, now()->toDateString());
    dashEnrollment($this->franchise, now()->subYears(2)->toDateString());

    $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');
    $data = (fn () => $this->getData())->call(new EnrollmentsBySourceChart);

    expect($data['labels'])->toHaveCount(12)
        ->and($data['datasets'][0]['data'])->toHaveCount(12)
        ->and(last($data['datasets'][0]['data']))->toBe(1)
        ->and(last($data['datasets'][1]['data']))->toBe(2)
        ->and(array_sum($data['datasets'][1]['data']))->toBe(2);
});

it('hides enrollment figures from admins who cannot view enrollments', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin');

    expect(EnrollmentsBySourceChart::canView())->toBeFalse();
    Livewire::test(HqStatsWidget::class)->assertDontSee('Enrollments this month')->assertDontSee('Active institutes');
});

it('renders the HQ dashboard on an empty database', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin')->get('/admin')->assertOk();
    Livewire::test(HqStatsWidget::class)->assertOk();
});
