<?php

use App\Models\Admin;
use App\Models\Institute;
use App\Models\Setting;
use App\Services\NumberSequenceService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Hash;

function install(array $overrides = []): void
{
    test()->artisan('kranti:install', array_merge([
        '--name' => 'Rohit',
        '--email' => 'owner@kcti.test',
        '--password' => 'secret-123',
        '--skip-migrate' => true,
        '--skip-assets' => true,
    ], $overrides))->assertSuccessful();
}

it('creates the main institute and super admin', function () {
    install();

    $main = Institute::mainInstitute();
    $admin = Admin::where('email', 'owner@kcti.test')->first();

    expect($main->code)->toBe('KCTI0001')
        ->and($main->name)->toBe('Kranti Computer Training Institute')
        ->and($main->wallet_exempt)->toBeTrue()
        ->and($main->isActive())->toBeTrue()
        ->and($admin->hasRole('super_admin'))->toBeTrue()
        ->and(Hash::check('secret-123', $admin->password))->toBeTrue();
});

it('is safe to run twice', function () {
    install();
    install(['--name' => 'Rohit Updated', '--password' => 'new-pass-456']);

    $admin = Admin::where('email', 'owner@kcti.test')->sole();

    expect(Institute::count())->toBe(1)
        ->and($admin->name)->toBe('Rohit Updated')
        ->and(Hash::check('new-pass-456', $admin->password))->toBeTrue()
        ->and($admin->roles()->count())->toBe(1);
});

it('makes the next franchise code KCTI0002', function () {
    install();

    expect(app(NumberSequenceService::class)->nextInstituteCode())->toBe('KCTI0002');
});

it('rejects an invalid email', function () {
    $this->artisan('kranti:install', [
        '--name' => 'X', '--email' => 'not-an-email', '--password' => 'secret-123',
        '--skip-migrate' => true, '--skip-assets' => true,
    ])->assertFailed();
});

it('rejects a short password', function () {
    $this->artisan('kranti:install', [
        '--name' => 'X', '--email' => 'x@kcti.test', '--password' => 'short',
        '--skip-migrate' => true, '--skip-assets' => true,
    ])->assertFailed();

    expect(Admin::count())->toBe(0);
});

it('stores the default website settings once and never overwrites edits', function () {
    install();

    expect(Setting::query()->where('group', 'site')->where('key', 'name')->value('value'))->toBe('Kranti Computer Training Institute');

    app(SettingsService::class)->set('site.name', 'KCTI Prayagraj');
    install();

    expect(app(SettingsService::class)->get('site.name'))->toBe('KCTI Prayagraj');
});
