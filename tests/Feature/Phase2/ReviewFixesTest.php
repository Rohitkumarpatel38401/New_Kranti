<?php

use App\Enums\FranchiseApplicationStatus;
use App\Exceptions\DomainRuleException;
use App\Filament\Admin\Resources\AcademicSessions\Pages\EditAcademicSession;
use App\Filament\Admin\Resources\Admins\AdminResource;
use App\Filament\Admin\Resources\Subjects\Pages\ManageSubjects;
use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FranchiseApplication;
use App\Models\Institute;
use App\Models\Subject;
use App\Notifications\InstituteOwnerCredentials;
use App\Services\AdminTeamService;
use App\Services\FranchiseOnboardingService;
use App\Services\NumberSequenceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Role::findByName('support', 'admin')->givePermissionTo('manage_admins');
    $this->delegate = Admin::factory()->create()->assignRole('support');
    $this->super = Admin::factory()->create()->assignRole('super_admin');
});

// Important 1: manage_admins must not escalate to Super Admin.
it('refuses super_admin grants and super admin edits by non-super admins', function () {
    $service = app(AdminTeamService::class);

    expect(fn () => $service->update($this->delegate, [], ['super_admin'], $this->delegate))->toThrow(DomainRuleException::class)
        ->and(fn () => $service->create(['name' => 'X', 'email' => 'x@kcti.test', 'password' => 'secret-123'], ['super_admin'], $this->delegate))
        ->toThrow(DomainRuleException::class)
        ->and(fn () => $service->update($this->super, ['password' => 'hijacked-123'], null, $this->delegate))->toThrow(DomainRuleException::class)
        ->and(fn () => $service->delete($this->super, $this->delegate))->toThrow(DomainRuleException::class);

    expect($this->delegate->fresh()->hasRole('super_admin'))->toBeFalse();
});

it('lets a super admin grant super_admin', function () {
    app(AdminTeamService::class)->update($this->delegate, [], ['super_admin'], $this->super);

    expect($this->delegate->fresh()->hasRole('super_admin'))->toBeTrue();
});

it('hides the super_admin role option from non-super admins', function () {
    $this->actingAs($this->delegate, 'admin');
    expect(AdminResource::roleOptions())->not->toHaveKey('super_admin');

    $this->actingAs($this->super, 'admin');
    expect(AdminResource::roleOptions())->toHaveKey('super_admin');
});

// Important 2: catalogue entries in use cannot be deleted.
it('refuses to delete a subject used by a course', function () {
    $subject = Subject::factory()->create();
    Course::factory()->create()->courseSubjects()->create(['subject_id' => $subject->id, 'theory_max' => 100, 'pass_marks' => 33]);

    expect(fn () => $subject->delete())->toThrow(DomainRuleException::class);
    expect(Subject::find($subject->id))->not->toBeNull();
});

it('refuses to delete a category that has courses', function () {
    $course = Course::factory()->create();

    expect(fn () => $course->category->delete())->toThrow(DomainRuleException::class);
});

it('shows a notification instead of an error when deleting a subject in use from the table', function () {
    $subject = Subject::factory()->create();
    Course::factory()->create()->courseSubjects()->create(['subject_id' => $subject->id, 'theory_max' => 100, 'pass_marks' => 33]);
    $this->actingAs($this->super, 'admin');
    Filament::setCurrentPanel('admin');

    Livewire::test(ManageSubjects::class)
        ->callTableAction('delete', $subject)
        ->assertNotified();

    expect(Subject::find($subject->id))->not->toBeNull();
});

it('still deletes unused subjects and categories', function () {
    $subject = Subject::factory()->create();
    $category = CourseCategory::factory()->create();

    $subject->delete();
    $category->delete();

    expect(Subject::find($subject->id))->toBeNull()->and(CourseCategory::find($category->id))->toBeNull();
});

// Important 3: a mail failure must not surface as a server error after a successful conversion.
it('reports but survives a credentials email failure', function () {
    app(NumberSequenceService::class)->syncAtLeast('institute_code', 1);
    Notification::shouldReceive('send')->andThrow(new RuntimeException('SMTP down'));
    $application = FranchiseApplication::factory()->create(['email' => 'owner@new.test']);

    $result = app(FranchiseOnboardingService::class)->convertWithOutcome($application, $this->super);

    expect($result['institute'])->toBeInstanceOf(Institute::class)
        ->and($result['emailed'])->toBeFalse()
        ->and($application->fresh()->status)->toBe(FranchiseApplicationStatus::Approved);
});

it('reports a successful credentials email', function () {
    app(NumberSequenceService::class)->syncAtLeast('institute_code', 1);
    Notification::fake();
    $application = FranchiseApplication::factory()->create(['email' => 'owner2@new.test']);

    $result = app(FranchiseOnboardingService::class)->convertWithOutcome($application, $this->super);

    expect($result['emailed'])->toBeTrue();
    Notification::assertSentTimes(InstituteOwnerCredentials::class, 1);
});

// Re-graded minor: the current session cannot simply be switched off.
it('does not let the current session be unmarked', function () {
    $current = AcademicSession::factory()->create(['is_current' => true]);
    $this->actingAs($this->super, 'admin');
    Filament::setCurrentPanel('admin');

    Livewire::test(EditAcademicSession::class, ['record' => $current->getRouteKey()])
        ->assertFormFieldDisabled('is_current');
});
