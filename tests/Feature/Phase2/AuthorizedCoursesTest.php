<?php

use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\Institutes\Pages\ViewInstitute;
use App\Filament\Admin\Resources\Institutes\RelationManagers\CoursesRelationManager;
use App\Models\Admin;
use App\Models\Course;
use App\Models\Institute;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['View', 'Update'] as $action) {
        Permission::findOrCreate("{$action}:Institute", 'admin');
    }
    $this->actingAs(Admin::factory()->create()->givePermissionTo(['View:Institute', 'Update:Institute']), 'admin');
    Filament::setCurrentPanel('admin');
    $this->institute = Institute::factory()->create();
});

function coursesManager(Institute $institute)
{
    return Livewire::test(CoursesRelationManager::class, ['ownerRecord' => $institute, 'pageClass' => ViewInstitute::class]);
}

it('authorizes a course with a fee override and removes it', function () {
    $course = Course::factory()->create();

    coursesManager($this->institute)
        ->callTableAction('attach', data: ['recordId' => $course->id, 'hq_fee_override' => 350, 'is_active' => true])
        ->assertHasNoTableActionErrors();

    expect($this->institute->courses()->sole()->pivot->hq_fee_override)->toBe('350.00');

    coursesManager($this->institute)->callTableAction('detach', $course);

    expect($this->institute->courses()->count())->toBe(0);
});

it('does not offer an already authorized course again', function () {
    $course = Course::factory()->create();
    $this->institute->courses()->attach($course, ['is_active' => true]);

    coursesManager($this->institute)
        ->callTableAction('attach', data: ['recordId' => $course->id])
        ->assertHasTableActionErrors(['recordId']);

    expect($this->institute->courses()->count())->toBe(1);
});

it('lists only active authorizations of active courses', function () {
    [$active, $pausedPivot, $inactiveCourse] = Course::factory()->count(3)->create();
    $inactiveCourse->update(['is_active' => false]);
    $this->institute->courses()->attach($active, ['is_active' => true]);
    $this->institute->courses()->attach($pausedPivot, ['is_active' => false]);
    $this->institute->courses()->attach($inactiveCourse, ['is_active' => true]);

    expect($this->institute->authorizedCourses()->pluck('courses.id')->all())->toBe([$active->id]);
});

it('uses the override fee, then the course fee', function () {
    [$withOverride, $withoutOverride] = Course::factory()->count(2)->create(['hq_registration_fee' => 500]);
    $this->institute->courses()->attach($withOverride, ['is_active' => true, 'hq_fee_override' => 300]);
    $this->institute->courses()->attach($withoutOverride, ['is_active' => true]);

    expect($this->institute->hqFeeFor($withOverride))->toBe('300.00')
        ->and($this->institute->hqFeeFor($withoutOverride))->toBe('500.00');
});

it('refuses the fee for a course the institute may not teach', function () {
    expect(fn () => $this->institute->hqFeeFor(Course::factory()->create()))->toThrow(DomainRuleException::class);
});
