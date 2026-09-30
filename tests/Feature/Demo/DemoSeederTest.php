<?php

use App\Enums\CertificateStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FeePayment;
use App\Models\FranchiseApplication;
use App\Models\GalleryItem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Notice;
use App\Models\Slider;
use App\Models\Student;
use App\Models\Testimonial;
use App\Models\WalletRechargeRequest;
use App\Services\SettingsService;
use App\Services\WalletService;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(DemoSeeder::class);
});

it('seeds every module the site and panels need', function () {
    $demo = InstituteUser::where('email', 'institue@example.com')->sole()->institute;
    $student = Student::where('email', 'student@example.com')->sole();

    expect(Institute::mainInstitute())->not->toBeNull()
        ->and($demo->isActive())->toBeTrue()
        ->and(Course::query()->onWebsite()->count())->toBeGreaterThanOrEqual(8)
        ->and(Course::query()->where('is_featured', true)->count())->toBeGreaterThan(0)
        ->and(InstituteUser::where('institute_id', $demo->id)->count())->toBe(3)
        ->and($student->institute_id)->toBe($demo->id)
        ->and(Enrollment::where('student_id', $student->id)->where('status', EnrollmentStatus::Completed)->count())->toBe(1)
        ->and(Enrollment::where('student_id', $student->id)->where('status', EnrollmentStatus::Active)->count())->toBe(1)
        ->and(FeePayment::where('student_id', $student->id)->count())->toBeGreaterThanOrEqual(2)
        ->and(Certificate::where('status', CertificateStatus::Issued)->count())->toBe(1)
        ->and(Student::where('institute_id', Institute::mainInstitute()->id)->count())->toBe(3)
        ->and(Slider::count())->toBe(3)
        ->and(Notice::where('show_in_ticker', true)->count())->toBe(3)
        ->and(GalleryItem::count())->toBeGreaterThanOrEqual(4)
        ->and(Testimonial::count())->toBe(4)
        ->and(Enquiry::count())->toBe(2)
        ->and(FranchiseApplication::count())->toBe(1)
        ->and(WalletRechargeRequest::withoutGlobalScopes()->count())->toBe(1)
        ->and(app(SettingsService::class)->get('site.phone'))->not->toBeEmpty();

    Storage::disk('public')->assertExists(GalleryItem::first()->image_path);
    // ₹10,000 credited minus the HQ fees of the two demo enrollments.
    expect((float) app(WalletService::class)->balance($demo))->toBeLessThan(10000.0)->toBeGreaterThan(0.0);
});

it('can run twice without duplicating anything', function () {
    $counts = fn () => [Student::count(), Enrollment::count(), Course::count(), Slider::count(), Notice::count(), InstituteUser::count(), FeePayment::count(), Enquiry::count()];
    $before = $counts();

    $this->seed(DemoSeeder::class);

    expect($counts())->toBe($before);
});

it('fills the public site', function () {
    $this->get('/')->assertOk()->assertSee('DCA');
    $this->get(route('site.courses.index'))->assertOk()->assertSee('Tally');
});

it('lets the demo institute owner into the institute panel', function () {
    $this->actingAs(InstituteUser::where('email', 'institue@example.com')->sole(), 'institute')->get('/institute')->assertOk();
});

it('lets the demo student into the portal', function () {
    $this->actingAs(Student::where('email', 'student@example.com')->sole(), 'student')->get('/student')->assertOk();
});
