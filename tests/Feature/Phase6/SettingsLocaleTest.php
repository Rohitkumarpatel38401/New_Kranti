<?php

use App\Filament\Admin\Pages\SiteSettings;
use App\Models\Admin;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('returns defaults, stores values, and caches them', function () {
    $settings = app(SettingsService::class);

    expect($settings->get('site.name'))->toBe('Kranti Computer Training Institute')
        ->and($settings->get('site.phone'))->toBeNull();

    $settings->set('site.phone', '9026043415');

    expect($settings->get('site.phone'))->toBe('9026043415')
        ->and(app(SettingsService::class)->get('site.phone'))->toBe('9026043415');
});

it('stores structured values', function () {
    app(SettingsService::class)->set('site.socials', ['facebook' => 'https://fb.example/kcti']);

    expect(app(SettingsService::class)->get('site.socials'))->toBe(['facebook' => 'https://fb.example/kcti']);
});

it('lets a super admin edit site settings', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->create()->assignRole('super_admin'), 'admin');

    Livewire::test(SiteSettings::class)
        ->fillForm(['site' => ['name' => 'KCTI Prayagraj', 'phone' => '9000000000', 'email' => 'info@kcti.test', 'announcement' => ['en' => 'New batch', 'hi' => 'नया बैच']]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(SettingsService::class)->get('site.name'))->toBe('KCTI Prayagraj')
        ->and(app(SettingsService::class)->get('site.announcement'))->toBe(['en' => 'New batch', 'hi' => 'नया बैच']);
});

it('keeps site settings away from admins without the permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(Admin::factory()->create()->assignRole('accounts'), 'admin')->get('/admin/site-settings')->assertForbidden();
});

it('switches language through the locale route and remembers it', function () {
    $this->from('/courses')->get(route('site.locale', 'hi'))->assertRedirect('/courses');

    expect(session('locale'))->toBe('hi');
    $this->get('/verify/NOTATOKEN');
    expect(app()->getLocale())->toBe('hi');
});

it('accepts ?lang= and ignores unknown locales', function () {
    $this->get('/verify/X?lang=hi');
    expect(app()->getLocale())->toBe('hi');

    $this->get(route('site.locale', 'fr'))->assertNotFound();
});
