<?php

use App\Enums\AddressType;
use App\Enums\AdmissionSource;
use App\Enums\GuardianRelation;
use App\Exceptions\DomainRuleException;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->institute = Institute::factory()->create(['code' => 'KCTI0007']);
    $this->user = InstituteUser::factory()->owner()->for($this->institute)->create();
    $this->service = app(StudentService::class);
});

function studentPayload(array $personal = [], ?array $guardians = null): array
{
    return [
        'student' => ['first_name' => 'Pooja', 'last_name' => 'Verma', 'phone' => '9877777777', 'email' => 'Pooja@Example.com', 'joined_on' => now()->toDateString()],
        'personal' => array_merge(['dob' => '2005-08-15', 'gender' => 'female', 'category' => 'obc', 'aadhaar' => '4567 8901 2345'], $personal),
        'guardians' => $guardians ?? [
            ['relation' => 'father', 'name' => 'Rakesh Verma', 'phone' => '9888888888', 'is_primary' => true],
            ['relation' => 'mother', 'name' => 'Sunita Verma'],
        ],
        'address' => ['line_1' => 'Katra', 'city' => 'Prayagraj', 'district' => 'Prayagraj', 'state' => 'Uttar Pradesh', 'pincode' => '211002'],
        'qualifications' => [['level' => 'twelfth', 'board_university' => 'UP Board', 'passing_year' => 2023, 'marks_type' => 'percentage', 'score' => 78.4]],
    ];
}

function createStudent(?array $payload = null, $by = null, ?Institute $institute = null): Student
{
    $p = $payload ?? studentPayload();

    return test()->service->create($p['student'], $p['personal'], $p['guardians'], $p['address'], $p['qualifications'], $by ?? test()->user, $institute);
}

it('creates a student with all related records', function () {
    $student = createStudent();

    expect($student->registration_no)->toBe('KCTI0007-'.now()->format('y').'-0001')
        ->and($student->institute_id)->toBe($this->institute->id)
        ->and($student->email)->toBe('pooja@example.com')
        ->and($student->admission_source)->toBe(AdmissionSource::Franchise)
        ->and($student->created_by_user_id)->toBe($this->user->id)
        ->and($student->personal->dob->toDateString())->toBe('2005-08-15')
        ->and($student->guardians)->toHaveCount(2)
        ->and($student->primaryGuardian()->relation)->toBe(GuardianRelation::Father)
        ->and($student->fatherName())->toBe('Rakesh Verma')
        ->and($student->address(AddressType::Permanent)->city)->toBe('Prayagraj')
        ->and($student->qualifications->first()->board_university)->toBe('UP Board');
});

it('numbers students per institute and year', function () {
    createStudent();
    $withoutIds = studentPayload(['aadhaar' => null]);
    $withoutIds['student']['email'] = null;
    $second = createStudent($withoutIds);
    $otherInstitute = Institute::factory()->create(['code' => 'KCTI0008']);
    $otherUser = InstituteUser::factory()->for($otherInstitute)->create();
    $third = createStudent($withoutIds, $otherUser);

    expect($second->registration_no)->toEndWith('-0002')
        ->and($third->registration_no)->toBe('KCTI0008-'.now()->format('y').'-0001');
});

it('encrypts Aadhaar at rest, hashes it for uniqueness, and masks it for display', function () {
    $student = createStudent();
    $raw = DB::table('student_personal_details')->where('student_id', $student->id)->first();

    expect($raw->aadhaar_encrypted)->not->toContain('456789012345')
        ->and($student->personal->aadhaar)->toBe('456789012345')
        ->and($student->personal->aadhaar_masked)->toBe('XXXX-XXXX-2345')
        ->and($raw->aadhaar_hash)->toHaveLength(64)
        ->and($raw->aadhaar_hash)->not->toBe(hash('sha256', '456789012345'));
});

it('refuses an Aadhaar already registered anywhere, with a clear message', function () {
    createStudent();
    $other = InstituteUser::factory()->for(Institute::factory())->create();

    expect(fn () => createStudent(null, $other))->toThrow(DomainRuleException::class, 'Aadhaar');
});

it('refuses an invalid Aadhaar number', function (string $aadhaar) {
    expect(fn () => createStudent(studentPayload(['aadhaar' => $aadhaar])))->toThrow(DomainRuleException::class, 'Aadhaar');
})->with(['12345', '0123 4567 8901', 'abcd efgh ijkl']);

it('requires exactly one primary guardian and one guardian per relation', function () {
    expect(fn () => createStudent(studentPayload([], [['relation' => 'father', 'name' => 'A']])))
        ->toThrow(DomainRuleException::class, 'primary')
        ->and(fn () => createStudent(studentPayload(['aadhaar' => null], [
            ['relation' => 'father', 'name' => 'A', 'is_primary' => true],
            ['relation' => 'father', 'name' => 'B'],
        ])))->toThrow(DomainRuleException::class, 'one father');
});

it('lets HQ create a student for a chosen institute as a walk-in', function () {
    $admin = Admin::factory()->create();
    $main = Institute::factory()->main()->create();

    $student = createStudent(null, $admin, $main);

    expect($student->institute_id)->toBe($main->id)
        ->and($student->admission_source)->toBe(AdmissionSource::WalkIn)
        ->and($student->created_by_user_id)->toBeNull();
});

it('requires HQ to choose an institute', function () {
    expect(fn () => createStudent(null, Admin::factory()->create()))->toThrow(DomainRuleException::class);
});

it('stores registration numbers uppercase', function () {
    $student = Student::factory()->create(['registration_no' => 'kcti0009-26-0001']);

    expect($student->registration_no)->toBe('KCTI0009-26-0001');
});
