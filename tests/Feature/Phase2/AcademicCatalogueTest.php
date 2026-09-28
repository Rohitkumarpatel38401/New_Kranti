<?php

use App\Enums\DurationUnit;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\AcademicSessions\Pages\CreateAcademicSession;
use App\Filament\Admin\Resources\Courses\Pages\CreateCourse;
use App\Filament\Admin\Resources\Courses\Pages\EditCourse;
use App\Filament\Admin\Resources\Courses\RelationManagers\SubjectsRelationManager;
use App\Filament\Admin\Resources\GradeScales\Pages\CreateGradeScale;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\GradeScale;
use App\Models\Subject;
use App\Services\AcademicSessionService;
use App\Services\GradeScaleService;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

function academicAdmin(): Admin
{
    $permissions = collect(['AcademicSession', 'CourseCategory', 'Course', 'Subject', 'GradeScale'])
        ->flatMap(fn ($model) => collect(['ViewAny', 'View', 'Create', 'Update', 'Delete'])->map(fn ($a) => "{$a}:{$model}"))
        ->each(fn ($name) => Permission::findOrCreate($name, 'admin'));

    return Admin::factory()->create()->givePermissionTo($permissions->all());
}

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

it('keeps exactly one current academic session', function () {
    $old = AcademicSession::factory()->create(['is_current' => true]);
    $new = AcademicSession::factory()->create(['is_current' => false]);

    app(AcademicSessionService::class)->makeCurrent($new);

    expect($old->fresh()->is_current)->toBeFalse()
        ->and($new->fresh()->is_current)->toBeTrue()
        ->and(AcademicSession::where('is_current', true)->count())->toBe(1);
});

it('unsets the previous current session when a new current one is created in the form', function () {
    $old = AcademicSession::factory()->create(['is_current' => true]);
    $this->actingAs(academicAdmin(), 'admin');

    Livewire::test(CreateAcademicSession::class)
        ->fillForm(['name' => '2027-28', 'code' => '2027-28', 'starts_on' => '2027-04-01', 'ends_on' => '2028-03-31', 'is_current' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect($old->fresh()->is_current)->toBeFalse()
        ->and(AcademicSession::where('code', '2027-28')->sole()->is_current)->toBeTrue();
});

it('rejects a session ending before it starts', function () {
    $this->actingAs(academicAdmin(), 'admin');

    Livewire::test(CreateAcademicSession::class)
        ->fillForm(['name' => 'Bad', 'code' => 'BAD', 'starts_on' => '2027-04-01', 'ends_on' => '2027-03-01'])
        ->call('create')
        ->assertHasFormErrors(['ends_on']);
});

it('refuses overlapping grade ranges but allows touching ones', function () {
    $service = app(GradeScaleService::class);
    GradeScale::factory()->create(['min_percent' => 40, 'max_percent' => 100, 'grade' => 'P']);

    $service->assertNoOverlap(0, 39.99);

    expect(fn () => $service->assertNoOverlap(30, 45))->toThrow(DomainRuleException::class)
        ->and(fn () => $service->assertNoOverlap(50, 60))->toThrow(DomainRuleException::class);
});

it('shows an overlap error in the grade scale form', function () {
    GradeScale::factory()->create(['min_percent' => 40, 'max_percent' => 100, 'grade' => 'P']);
    $this->actingAs(academicAdmin(), 'admin');

    Livewire::test(CreateGradeScale::class)
        ->fillForm(['grade' => 'X', 'min_percent' => 35, 'max_percent' => 50, 'remark' => ['en' => 'Overlap']])
        ->call('create')
        ->assertHasFormErrors(['min_percent']);
});

it('creates a bilingual course with an uppercase unique code', function () {
    $category = CourseCategory::factory()->create();
    $this->actingAs(academicAdmin(), 'admin');

    Livewire::test(CreateCourse::class)
        ->fillForm([
            'course_category_id' => $category->id,
            'code' => 'adca',
            'name' => ['en' => 'Advanced Diploma in Computer Applications', 'hi' => 'कंप्यूटर अनुप्रयोग में उन्नत डिप्लोमा'],
            'duration_value' => 12,
            'duration_unit' => DurationUnit::Months->value,
            'hq_registration_fee' => 500,
            'suggested_fee' => 12000,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $course = Course::sole();
    expect($course->code)->toBe('ADCA')
        ->and($course->slug)->toBe('advanced-diploma-in-computer-applications')
        ->and($course->localized('name', 'hi'))->toBe('कंप्यूटर अनुप्रयोग में उन्नत डिप्लोमा');

    Livewire::test(CreateCourse::class)
        ->fillForm(['course_category_id' => $category->id, 'code' => 'ADCA', 'name' => ['en' => 'Duplicate'], 'duration_value' => 1, 'duration_unit' => 'months', 'hq_registration_fee' => 0])
        ->call('create')
        ->assertHasFormErrors(['code' => 'unique']);
});

it('falls back to English course names in Hindi when no Hindi name is set', function () {
    app()->setLocale('hi');

    expect(Course::factory()->create(['name' => ['en' => 'Tally Prime']])->localized('name'))->toBe('Tally Prime');
});

it('adds subjects to a course without duplicates and checks pass marks', function () {
    $course = Course::factory()->create();
    $subject = Subject::factory()->create();
    $this->actingAs(academicAdmin(), 'admin');

    $manager = fn () => Livewire::test(SubjectsRelationManager::class, ['ownerRecord' => $course, 'pageClass' => EditCourse::class]);

    $manager()->callTableAction('create', data: ['subject_id' => $subject->id, 'term_no' => 1, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33])
        ->assertHasNoTableActionErrors();

    $manager()->callTableAction('create', data: ['subject_id' => $subject->id, 'term_no' => 1, 'theory_max' => 70, 'practical_max' => 30, 'pass_marks' => 33])
        ->assertHasTableActionErrors(['subject_id']);

    $manager()->callTableAction('create', data: ['subject_id' => Subject::factory()->create()->id, 'term_no' => 1, 'theory_max' => 50, 'practical_max' => 0, 'pass_marks' => 60])
        ->assertHasTableActionErrors(['pass_marks']);

    expect($course->courseSubjects()->count())->toBe(1);
});

// Separate tests: AuthenticateSession logs out when the admin changes within one session.
it('opens the catalogue for admins with its permissions', function () {
    $this->actingAs(academicAdmin(), 'admin')->get('/admin/courses')->assertOk();
});

it('forbids the catalogue to admins without its permissions', function () {
    $this->actingAs(Admin::factory()->create(), 'admin')->get('/admin/courses')->assertForbidden();
});
