<?php

use App\Filament\Institute\Resources\Batches\Pages\CreateBatch;
use App\Filament\Institute\Resources\Students\Pages\CreateStudent;
use App\Filament\Institute\Resources\Students\Pages\EditStudent;
use App\Filament\Institute\Resources\Students\Pages\ListStudents;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create(['code' => 'KCTI0005']);
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    Filament::setCurrentPanel('institute');
});

function actInInstitute(InstituteUser $user): void
{
    test()->actingAs($user, 'institute');
    app(CurrentInstitute::class)->set($user->institute_id);
}

function wizardData(): array
{
    return [
        'student' => ['first_name' => 'Arjun', 'last_name' => 'Maurya', 'phone' => '9811122233', 'email' => 'arjun@example.com', 'joined_on' => now()->toDateString()],
        'personal' => ['dob' => '2004-01-20', 'gender' => 'male', 'category' => 'gen', 'aadhaar' => '234567890123'],
        'guardians' => [['relation' => 'father', 'name' => 'Ram Maurya', 'phone' => '9822233344', 'is_primary' => true]],
        'address' => ['line_1' => 'Naini', 'city' => 'Prayagraj', 'district' => 'Prayagraj', 'state' => 'Uttar Pradesh', 'pincode' => '211008'],
        'qualifications' => [['level' => 'twelfth', 'board_university' => 'CBSE', 'passing_year' => 2022, 'marks_type' => 'percentage', 'score' => 81]],
    ];
}

it('admits a student through the wizard', function () {
    actInInstitute($this->owner);

    Livewire::test(CreateStudent::class)
        ->fillForm(wizardData())
        ->call('create')
        ->assertHasNoFormErrors();

    $student = Student::sole();
    expect($student->registration_no)->toStartWith('KCTI0005-')
        ->and($student->institute_id)->toBe($this->institute->id)
        ->and($student->personal->aadhaar_masked)->toBe('XXXX-XXXX-0123')
        ->and($student->fatherName())->toBe('Ram Maurya')
        ->and($student->qualifications)->toHaveCount(1);
});

it('keeps the stored Aadhaar when the field is left empty on edit', function () {
    actInInstitute($this->owner);
    Livewire::test(CreateStudent::class)->fillForm(wizardData())->call('create');
    $student = Student::sole();

    Livewire::test(EditStudent::class, ['record' => $student->getRouteKey()])
        ->fillForm(['student.first_name' => 'Arjun Kumar', 'personal.aadhaar' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($student->fresh()->first_name)->toBe('Arjun Kumar')
        ->and($student->fresh()->personal->aadhaar)->toBe('234567890123');
});

it('shows a clear error for an Aadhaar already registered', function () {
    actInInstitute($this->owner);
    Livewire::test(CreateStudent::class)->fillForm(wizardData())->call('create');

    $again = wizardData();
    $again['student']['email'] = 'other@example.com';
    Livewire::test(CreateStudent::class)->fillForm($again)->call('create')->assertNotified();

    expect(Student::count())->toBe(1);
});

it('shows the student profile with a masked Aadhaar', function () {
    actInInstitute($this->owner);
    Livewire::test(CreateStudent::class)->fillForm(wizardData())->call('create');
    $student = Student::sole();

    $this->get("/institute/students/{$student->getRouteKey()}")
        ->assertOk()
        ->assertSee('XXXX-XXXX-0123')
        ->assertDontSee('234567890123')
        ->assertSee('Ram Maurya');
});

it('returns 404 for another institute student', function () {
    $foreign = Student::factory()->for(Institute::factory())->create();
    $this->actingAs($this->owner, 'institute');

    $this->get("/institute/students/{$foreign->getRouteKey()}")->assertNotFound();
});

it('never lists another institute students through the Livewire update endpoint', function () {
    Student::factory()->for(Institute::factory())->create(['first_name' => 'Outsider']);
    Student::factory()->for($this->institute)->create(['first_name' => 'Insider']);
    $this->actingAs($this->owner, 'institute');

    $html = $this->get('/institute/students')->assertOk()->assertSee('Insider')->assertDontSee('Outsider')->getContent();

    expect(livewireUpdate($this, $html, ListStudents::class, ['tableSearch' => 'sider']))->not->toContain('Outsider');
});

it('stops teachers from admitting students', function () {
    $teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');

    $this->actingAs($teacher, 'institute')->get('/institute/students/create')->assertForbidden();
});

it('limits batch courses to authorized courses and teachers to own staff', function () {
    $allowed = Course::factory()->create(['code' => 'DCA']);
    $notAllowed = Course::factory()->create(['code' => 'ADCA']);
    $this->institute->courses()->attach($allowed, ['is_active' => true]);
    $teacher = InstituteUser::factory()->for($this->institute)->create(['name' => 'Our Teacher'])->assignRole('teacher');
    InstituteUser::factory()->for(Institute::factory())->create(['name' => 'Their Teacher']);
    $session = AcademicSession::factory()->create(['is_current' => true]);
    actInInstitute($this->owner);

    Livewire::test(CreateBatch::class)
        ->fillForm(['course_id' => $notAllowed->id, 'academic_session_id' => $session->id, 'name' => 'Morning', 'code' => 'M1'])
        ->call('create')
        ->assertHasFormErrors(['course_id']);

    Livewire::test(CreateBatch::class)
        ->fillForm([
            'course_id' => $allowed->id, 'academic_session_id' => $session->id, 'name' => 'Morning DCA', 'code' => 'dca-m1',
            'start_time' => '08:00', 'end_time' => '10:00', 'capacity' => 30, 'teacher_user_id' => $teacher->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $batch = Batch::sole();
    expect($batch->institute_id)->toBe($this->institute->id)
        ->and($batch->code)->toBe('DCA-M1');
});

it('refuses a teacher from another institute on a batch', function () {
    $allowed = Course::factory()->create();
    $this->institute->courses()->attach($allowed, ['is_active' => true]);
    $foreignTeacher = InstituteUser::factory()->for(Institute::factory())->create();
    $session = AcademicSession::factory()->create();
    actInInstitute($this->owner);

    Livewire::test(CreateBatch::class)
        ->fillForm(['course_id' => $allowed->id, 'academic_session_id' => $session->id, 'name' => 'X', 'code' => 'X1', 'teacher_user_id' => $foreignTeacher->id])
        ->call('create')
        ->assertHasFormErrors(['teacher_user_id']);
});
