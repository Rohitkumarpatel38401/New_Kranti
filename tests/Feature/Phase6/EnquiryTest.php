<?php

use App\Enums\AdmissionSource;
use App\Enums\EnquiryStatus;
use App\Enums\EnquiryType;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Enquiries\Pages\ListEnquiries;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Institute;
use App\Services\EnquiryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->main = Institute::factory()->main()->create();
    $this->support = Admin::factory()->create()->assignRole('support');
    $this->service = app(EnquiryService::class);
});

function enquiry(array $overrides = []): Enquiry
{
    return test()->service->submit([
        'type' => EnquiryType::Admission, 'name' => 'Ravi Kumar', 'phone' => '9876543210',
        'email' => 'ravi@example.com', 'message' => 'Want to join', ...$overrides,
    ]);
}

function conversion(): array
{
    return [
        'student' => ['first_name' => 'Ravi', 'last_name' => 'Kumar', 'phone' => '9876543210'],
        'personal' => ['dob' => '2005-05-05', 'gender' => 'male'],
        'guardians' => [['relation' => 'father', 'name' => 'Mohan Kumar', 'phone' => '9876500000', 'is_primary' => true]],
    ];
}

it('files a website enquiry against the main institute as new', function () {
    $course = Course::factory()->create();
    $e = enquiry(['course_id' => $course->id]);

    expect($e->institute_id)->toBe($this->main->id)
        ->and($e->status)->toBe(EnquiryStatus::New)
        ->and($e->type)->toBe(EnquiryType::Admission)
        ->and($e->course_id)->toBe($course->id);
});

it('moves an enquiry to contacted and then closed', function () {
    $e = enquiry();
    $this->service->markContacted($e, $this->support);
    expect($e->fresh()->status)->toBe(EnquiryStatus::Contacted)
        ->and($e->fresh()->handled_by_admin_id)->toBe($this->support->id);

    $this->service->close($e, $this->support);
    expect($e->fresh()->status)->toBe(EnquiryStatus::Closed);
    expect(fn () => $this->service->markContacted($e, $this->support))->toThrow(DomainRuleException::class);
});

it('converts an admission enquiry into a website student of the main institute, once', function () {
    $e = enquiry();
    ['student' => $s, 'personal' => $p, 'guardians' => $g] = conversion();

    $student = $this->service->convertToStudent($e, $s, $p, $g, $this->support);

    expect($student->institute_id)->toBe($this->main->id)
        ->and($student->admission_source)->toBe(AdmissionSource::Website)
        ->and($e->fresh()->status)->toBe(EnquiryStatus::Converted)
        ->and($e->fresh()->converted_student_id)->toBe($student->id);

    expect(fn () => $this->service->convertToStudent($e, $s, $p, $g, $this->support))->toThrow(DomainRuleException::class);
});

it('refuses conversion by an admin without manage_enquiries', function () {
    $e = enquiry();
    ['student' => $s, 'personal' => $p, 'guardians' => $g] = conversion();
    $academic = Admin::factory()->create()->assignRole('academic');

    expect(fn () => $this->service->convertToStudent($e, $s, $p, $g, $academic))->toThrow(DomainRuleException::class);
    expect($e->fresh()->status)->toBe(EnquiryStatus::New);
});

it('lists new enquiries for support and converts from the table', function () {
    $e = enquiry();
    $this->actingAs($this->support, 'admin');

    Livewire::test(ListEnquiries::class)
        ->assertCanSeeTableRecords([$e])
        ->callTableAction('convert', $e, data: [
            'first_name' => 'Ravi', 'last_name' => 'Kumar', 'phone' => '9876543210', 'email' => 'ravi@example.com',
            'dob' => '2005-05-05', 'gender' => 'male',
            'guardian_relation' => 'father', 'guardian_name' => 'Mohan Kumar', 'guardian_phone' => '9876500000',
        ])
        ->assertHasNoTableActionErrors();

    expect($e->fresh()->status)->toBe(EnquiryStatus::Converted);
});

it('hides enquiries from admins without manage_enquiries', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('academic'), 'admin');

    $this->get('/admin/enquiries')->assertForbidden();
});
