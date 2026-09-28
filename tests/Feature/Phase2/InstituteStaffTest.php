<?php

use App\Exceptions\DomainRuleException;
use App\Filament\Institute\Pages\Auth\EditProfile;
use App\Filament\Institute\Resources\Staff\Pages\CreateStaff;
use App\Filament\Institute\Resources\Staff\Pages\ListStaff;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Services\StaffService;
use App\Support\Tenancy\CurrentInstitute;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create()->assignRole('owner');
    Filament::setCurrentPanel('institute');
});

function actAsInstituteUser(InstituteUser $user): void
{
    test()->actingAs($user, 'institute');
    app(CurrentInstitute::class)->set($user->institute_id);
}

it('lets the owner add a teacher', function () {
    actAsInstituteUser($this->owner);

    Livewire::test(CreateStaff::class)
        ->fillForm(['name' => 'Anil Teacher', 'email' => 'anil@example.com', 'phone' => '9844444444', 'password' => 'secret-123', 'role' => 'teacher', 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $teacher = InstituteUser::where('email', 'anil@example.com')->sole();
    expect($teacher->institute_id)->toBe($this->institute->id)
        ->and($teacher->is_owner)->toBeFalse()
        ->and($teacher->hasRole('teacher'))->toBeTrue();
});

it('refuses the owner role and admin-guard roles', function (string $role) {
    actAsInstituteUser($this->owner);

    Livewire::test(CreateStaff::class)
        ->fillForm(['name' => 'X', 'email' => 'x@example.com', 'password' => 'secret-123', 'role' => $role])
        ->call('create')
        ->assertHasFormErrors(['role']);

    expect(fn () => app(StaffService::class)->create(['name' => 'Y', 'email' => 'y@example.com', 'password' => 'secret-123'], $role, $this->owner))
        ->toThrow(DomainRuleException::class);
})->with(['owner', 'super_admin', 'manager']);

it('does not let staff without manage_staff add people', function () {
    $receptionist = InstituteUser::factory()->for($this->institute)->create()->assignRole('receptionist');
    actAsInstituteUser($receptionist);

    $this->get('/institute/staff/create')->assertForbidden();
});

it('never deletes or deactivates the owner or yourself', function () {
    $service = app(StaffService::class);
    $manager = InstituteUser::factory()->for($this->institute)->create()->assignRole('owner');

    expect(fn () => $service->delete($this->owner, $manager))->toThrow(DomainRuleException::class)
        ->and(fn () => $service->update($this->owner, ['is_active' => false], null, $manager))->toThrow(DomainRuleException::class)
        ->and(fn () => $service->delete($manager, $manager))->toThrow(DomainRuleException::class)
        ->and($this->owner->fresh()->is_active)->toBeTrue();
});

it('never shows another institute staff through the Livewire table', function () {
    $mine = InstituteUser::factory()->for($this->institute)->create(['name' => 'Mine Staff']);
    $other = Institute::factory()->create();
    $theirs = InstituteUser::factory()->for($other)->create(['name' => 'Theirs Staff']);
    InstituteUser::factory()->count(12)->for($other)->create();
    actAsInstituteUser($this->owner);

    Livewire::test(ListStaff::class)
        ->assertCanSeeTableRecords([$mine, $this->owner])
        ->assertCanNotSeeTableRecords([$theirs])
        ->searchTable('Theirs')
        ->assertCanNotSeeTableRecords([$theirs])
        ->searchTable('')
        ->set('tableRecordsPerPage', 5)
        ->call('gotoPage', 2, 'page')
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('keeps the institute scope on real Livewire update requests', function () {
    $other = Institute::factory()->create();
    $theirs = InstituteUser::factory()->for($other)->create(['name' => 'Foreign Person']);
    $this->actingAs($this->owner, 'institute');

    // First request renders the page; the component then re-renders through Livewire's update endpoint.
    $html = $this->get('/institute/staff')->assertOk()->assertDontSee('Foreign Person')->getContent();
    preg_match_all('/wire:snapshot="([^"]+)"/', $html, $matches);
    $snapshot = collect($matches[1])
        ->map(fn (string $raw) => html_entity_decode($raw))
        ->first(fn (string $json) => (json_decode($json, true)['memo']['name'] ?? '') === ListStaff::class);
    expect($snapshot)->not->toBeNull();

    $update = $this->withHeaders(['X-Livewire' => 'true'])->postJson(EndpointResolver::updatePath(), [
        '_token' => csrf_token(),
        'components' => [['snapshot' => $snapshot, 'updates' => ['tableSearch' => 'Foreign'], 'calls' => []]],
    ]);

    $update->assertOk();
    expect($update->getContent())->not->toContain('Foreign Person');
});

it('hides password change on the profile page while impersonating', function () {
    $admin = Admin::factory()->create()->assignRole('manager');
    $this->actingAs($admin, 'admin')->get(URL::temporarySignedRoute('impersonation.start', now()->addMinutes(5), ['institute' => $this->institute]));

    Livewire::test(EditProfile::class)
        ->assertFormFieldHidden('password')
        ->assertFormFieldDisabled('email');
});

it('shows password change on the profile page normally', function () {
    actAsInstituteUser($this->owner);

    Livewire::test(EditProfile::class)->assertFormFieldVisible('password');
});

it('ends impersonation instead of signing the admin out', function () {
    $admin = Admin::factory()->create()->assignRole('manager');
    $this->actingAs($admin, 'admin')->get(URL::temporarySignedRoute('impersonation.start', now()->addMinutes(5), ['institute' => $this->institute]));

    $this->post('/institute/logout')->assertRedirect(route('impersonation.leave'));
    $this->get(route('impersonation.leave'));

    expect(auth('admin')->id())->toBe($admin->id)
        ->and(auth('institute')->check())->toBeFalse();
});
