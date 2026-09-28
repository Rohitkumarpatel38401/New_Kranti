<?php

use App\Models\AcademicSession;
use App\Models\Admin;
use App\Models\GradeScale;
use App\Services\GradeScaleService;
use Database\Seeders\AcademicDefaultsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

it('seeds a current session and a complete, non-overlapping grade scale once', function () {
    $this->seed(AcademicDefaultsSeeder::class);
    $this->seed(AcademicDefaultsSeeder::class);

    expect(AcademicSession::where('is_current', true)->count())->toBe(1)
        ->and(GradeScale::count())->toBe(6)
        ->and(GradeScale::forPercentage(95)->grade)->toBe('A+')
        ->and(GradeScale::forPercentage(33)->grade)->toBe('D')
        ->and(GradeScale::forPercentage(32.99)->grade)->toBe('F')
        ->and(GradeScale::forPercentage(0)->grade)->toBe('F')
        ->and(GradeScale::where('grade', 'F')->first()->localized('remark', 'hi'))->toBe('अनुत्तीर्ण');

    GradeScale::all()->each(fn (GradeScale $g) => app(GradeScaleService::class)
        ->assertNoOverlap((float) $g->min_percent, (float) $g->max_percent, $g->id));
});

it('does not replace an existing current session', function () {
    $existing = AcademicSession::factory()->create(['is_current' => true]);

    $this->seed(AcademicDefaultsSeeder::class);

    expect(AcademicSession::where('is_current', true)->sole()->is($existing))->toBeTrue();
});

it('lets each HQ role open the screens it owns', function (string $role, string $allowed, string $denied) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = Admin::factory()->create()->assignRole($role);

    $this->actingAs($admin, 'admin')->get($allowed)->assertOk();
})->with([
    ['manager', '/admin/institutes', '/admin/courses'],
    ['manager', '/admin/franchise-applications', '/admin/courses'],
    ['academic', '/admin/courses', '/admin/institutes'],
    ['academic', '/admin/grade-scales', '/admin/institutes'],
]);

it('keeps each HQ role out of screens it does not own', function (string $role, string $denied) {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = Admin::factory()->create()->assignRole($role);

    $this->actingAs($admin, 'admin')->get($denied)->assertForbidden();
})->with([
    ['manager', '/admin/courses'],
    ['academic', '/admin/institutes'],
    ['support', '/admin/institutes'],
    ['accounts', '/admin/admins'],
]);

it('seeds academic defaults from the installer', function () {
    $this->artisan('kranti:install', [
        '--name' => 'Rohit', '--email' => 'owner@kcti.test', '--password' => 'secret-123',
        '--skip-migrate' => true, '--skip-assets' => true,
    ])->assertSuccessful();

    expect(AcademicSession::where('is_current', true)->exists())->toBeTrue()
        ->and(GradeScale::count())->toBe(6);
});
