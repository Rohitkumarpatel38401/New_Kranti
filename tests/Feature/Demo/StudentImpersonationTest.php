<?php

use App\Enums\EnrollmentStatus;
use App\Filament\Admin\Resources\Students\Pages\ListStudents;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\StudentImpersonationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create();
    $this->student = Student::factory()->withActiveEnrollment()->for($this->institute)->create();
});

function loginAsUrl(Student $student, string $as): string
{
    return URL::temporarySignedRoute('student-impersonation.start', now()->addMinutes(5), ['student' => $student, 'as' => $as]);
}

it('lets a manager log in as a student and return to the admin panel', function () {
    $manager = Admin::factory()->create()->assignRole('manager');
    $this->actingAs($manager, 'admin');

    $this->get(loginAsUrl($this->student, 'admin'))->assertRedirect('/student');
    expect(auth('student')->id())->toBe($this->student->id)
        ->and(Activity::where('event', 'student-impersonation.started')->exists())->toBeTrue();

    $this->get('/student')->assertOk()->assertSee('Return');

    $this->get(route('student-impersonation.leave'))->assertRedirect('/admin/students');
    expect(auth('student')->check())->toBeFalse()
        ->and(auth('admin')->id())->toBe($manager->id);
});

it('refuses admins without impersonate_student', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('support'), 'admin');

    $this->get(loginAsUrl($this->student, 'admin'))->assertForbidden();
    expect(auth('student')->check())->toBeFalse();
});

it('lets an institute owner log in as their own student only', function () {
    $owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    $stranger = Student::factory()->withActiveEnrollment()->create();
    $this->actingAs($owner, 'institute');

    $this->get(loginAsUrl($stranger, 'institute'))->assertForbidden();
    $this->get(loginAsUrl($this->student, 'institute'))->assertRedirect('/student');
    $this->get(route('student-impersonation.leave'))->assertRedirect('/institute/students');
});

it('refuses institute staff without the permission', function () {
    $teacher = InstituteUser::factory()->for($this->institute)->create()->assignRole('teacher');
    $this->actingAs($teacher, 'institute');

    $this->get(loginAsUrl($this->student, 'institute'))->assertForbidden();
});

it('refuses a student whose portal is closed, and unsigned links', function () {
    $this->actingAs(Admin::factory()->create()->assignRole('manager'), 'admin');
    $closed = Student::factory()->for($this->institute)->create();

    $this->get(loginAsUrl($closed, 'admin'))->assertStatus(422);
    $this->get(route('student-impersonation.start', ['student' => $this->student, 'as' => 'admin']))->assertForbidden();
});

it('skips the forced password change while impersonating', function () {
    $this->student->forceFill(['must_change_password' => true])->save();
    $this->actingAs(Admin::factory()->create()->assignRole('manager'), 'admin');

    $this->get(loginAsUrl($this->student, 'admin'));
    $this->get('/student')->assertOk();
    expect(app(StudentImpersonationService::class)->isImpersonating())->toBeTrue()
        ->and($this->student->enrollments()->first()->status)->toBe(EnrollmentStatus::Active);
});

it('shows the Login as student button only to people who may use it', function () {
    Filament::setCurrentPanel('admin');

    $this->actingAs(Admin::factory()->create()->assignRole('manager'), 'admin');
    Livewire::test(ListStudents::class)
        ->assertTableActionVisible('loginAsStudent', $this->student);
});

it('hides the Login as student button from admins without the permission', function () {
    Filament::setCurrentPanel('admin');
    $admin = Admin::factory()->create()->assignRole('accounts');
    $admin->givePermissionTo(['ViewAny:Student', 'View:Student']);

    $this->actingAs($admin, 'admin');
    Livewire::test(ListStudents::class)
        ->assertTableActionHidden('loginAsStudent', $this->student);
});

// Review Important 1: signing out of the portal while viewing must not log the impersonator out
it('ends the impersonation instead of logging everyone out on portal sign-out', function () {
    $manager = Admin::factory()->create()->assignRole('manager');
    $this->actingAs($manager, 'admin');
    $this->get(loginAsUrl($this->student, 'admin'));

    $this->post(route('filament.student.auth.logout'))->assertRedirect(route('student-impersonation.leave'));
    expect(auth('admin')->id())->toBe($manager->id);
});

it('shows Login as a row button that opens the portal in the same tab, for HQ and the institute owner', function () {
    \Filament\Facades\Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->create()->assignRole('manager'), 'admin');
    \Livewire\Livewire::test(\App\Filament\Admin\Resources\Students\Pages\ListStudents::class)
        ->assertTableActionHasLabel('loginAsStudent', 'Login', $this->student)
        ->assertTableActionShouldNotOpenUrlInNewTab('loginAsStudent', $this->student);
});

it('shows the institute owner a Login row button for their student', function () {
    $owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    \Filament\Facades\Filament::setCurrentPanel('institute');
    $this->actingAs($owner, 'institute');
    app(\App\Support\Tenancy\CurrentInstitute::class)->set($this->institute->id);

    \Livewire\Livewire::test(\App\Filament\Institute\Resources\Students\Pages\ListStudents::class)
        ->assertTableActionVisible('loginAsStudent', $this->student)
        ->assertTableActionHasLabel('loginAsStudent', 'Login', $this->student)
        ->assertTableActionShouldNotOpenUrlInNewTab('loginAsStudent', $this->student);
});
