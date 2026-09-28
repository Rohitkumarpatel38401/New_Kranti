<?php

use App\Filament\Admin\Resources\Institutes\Pages\ListInstitutes;
use App\Models\Admin;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Student;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->institute = Institute::factory()->create();
    $this->owner = InstituteUser::factory()->owner()->for($this->institute)->create();
    $this->admin = Admin::factory()->create()->assignRole('manager');
});

function startUrl(Institute $institute): string
{
    return URL::temporarySignedRoute('impersonation.start', now()->addMinutes(5), ['institute' => $institute]);
}

it('logs the admin in as the institute owner and keeps the admin session', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(startUrl($this->institute))
        ->assertRedirect('/institute');

    expect(auth('institute')->id())->toBe($this->owner->id)
        ->and(auth('admin')->id())->toBe($this->admin->id)
        ->and(session('impersonator_admin_id'))->toBe($this->admin->id);

    $this->get('/institute')->assertOk()->assertSee('Return to Admin');
});

it('records start and stop in the activity log', function () {
    $this->actingAs($this->admin, 'admin')->get(startUrl($this->institute));
    $this->get(route('impersonation.leave'))->assertRedirect('/admin/institutes');

    expect(auth('institute')->check())->toBeFalse()
        ->and(auth('admin')->id())->toBe($this->admin->id)
        ->and(Activity::pluck('event')->all())->toBe(['impersonation.started', 'impersonation.stopped']);
});

it('forbids admins without the permission', function () {
    $support = Admin::factory()->create()->assignRole('support');

    $this->actingAs($support, 'admin')->get(startUrl($this->institute))->assertForbidden();
    expect(auth('institute')->check())->toBeFalse();
});

it('rejects unsigned links', function () {
    $this->actingAs($this->admin, 'admin')
        ->get(route('impersonation.start', $this->institute))
        ->assertForbidden();
});

it('sends guests to the admin login', function () {
    $this->get(startUrl($this->institute))->assertRedirect('/admin/login');
});

it('refuses suspended institutes and institutes without an active owner', function () {
    $suspended = Institute::factory()->suspended()->create();
    InstituteUser::factory()->owner()->for($suspended)->create();
    $ownerless = Institute::factory()->create();

    $this->actingAs($this->admin, 'admin')->get(startUrl($suspended))->assertStatus(422);
    $this->actingAs($this->admin, 'admin')->get(startUrl($ownerless))->assertStatus(422);
});

it('forbids leave without impersonation', function () {
    $this->get(route('impersonation.leave'))->assertForbidden();
});

it('admin panel still sees all institutes while impersonating', function () {
    Student::factory()->for(Institute::factory())->create();
    Student::factory()->for($this->institute)->create();

    $this->actingAs($this->admin, 'admin')->get(startUrl($this->institute));

    $this->get('/admin/institutes')->assertOk()->assertSee(Institute::first()->name);
    expect(Student::count())->toBe(2);
});

it('shows the impersonate action only when allowed', function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin, 'admin');

    Livewire::test(ListInstitutes::class)
        ->assertCanSeeTableRecords([$this->institute])
        ->assertTableActionVisible('impersonate', $this->institute);

    // can list institutes but lacks impersonate_institute
    $this->actingAs(Admin::factory()->create()->givePermissionTo('ViewAny:Institute'), 'admin');

    Livewire::test(ListInstitutes::class)
        ->assertTableActionHidden('impersonate', $this->institute);
});

it('keeps the owner remembered on their own devices after impersonation ends', function () {
    $this->owner->forceFill(['remember_token' => 'owner-device-token'])->save();

    $this->actingAs($this->admin, 'admin')->get(startUrl($this->institute));
    $this->get(route('impersonation.leave'));

    expect($this->owner->fresh()->remember_token)->toBe('owner-device-token');
});

it('refuses a deactivated admin holding a still-valid link', function () {
    $url = startUrl($this->institute);
    $this->admin->update(['is_active' => false]);

    $this->actingAs($this->admin->fresh(), 'admin')->get($url)->assertForbidden();
    expect(auth('institute')->check())->toBeFalse();
});
