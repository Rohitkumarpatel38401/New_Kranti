<?php

use App\Models\Admin;
use App\Models\InstituteUser;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;

function runInstall(array $options = []): void
{
    test()->artisan('kranti:install', [
        '--skip-migrate' => true, '--skip-assets' => true, '--no-interaction' => true, ...$options,
    ])->assertSuccessful();
}

it('uses the default admin credentials when none are given', function () {
    runInstall(['--no-demo' => true]);

    $admin = Admin::where('email', 'admin@example.com')->sole();
    expect($admin->name)->toBe('Kranti Admin')
        ->and(Hash::check('admin123', $admin->password))->toBeTrue()
        ->and($admin->hasRole('super_admin'))->toBeTrue();
});

it('still accepts explicit credentials', function () {
    runInstall(['--name' => 'Rohit', '--email' => 'owner@kcti.test', '--password' => 'secret-123', '--no-demo' => true]);

    expect(Admin::where('email', 'owner@kcti.test')->exists())->toBeTrue()
        ->and(Admin::where('email', 'admin@example.com')->exists())->toBeFalse();
});

it('does not load demo data outside local unless --demo is given', function () {
    // The test environment is not "local".
    runInstall();

    expect(InstituteUser::where('email', 'institue@example.com')->exists())->toBeFalse();
});

it('loads the demo institute and student with --demo', function () {
    runInstall(['--demo' => true]);

    $owner = InstituteUser::where('email', 'institue@example.com')->sole();
    $student = Student::where('email', 'student@example.com')->sole();

    expect($owner->is_owner)->toBeTrue()
        ->and(Hash::check('admin123', $owner->password))->toBeTrue()
        ->and(Hash::check('admin123', $student->password))->toBeTrue()
        ->and($student->portalOpen())->toBeTrue()
        ->and($student->must_change_password)->toBeFalse();
});

it('loads demo data automatically on local', function () {
    app()['env'] = 'local';

    runInstall();

    expect(InstituteUser::where('email', 'institue@example.com')->exists())->toBeTrue();
});
