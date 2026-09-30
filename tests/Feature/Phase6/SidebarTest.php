<?php

use App\Models\Notice;
use App\Services\SettingsService;

beforeEach(function () {
    app(SettingsService::class)->setMany(['site.phone' => '9026043415', 'site.whatsapp' => '98765 43210', 'site.email' => 'help@kcti.test']);
    Notice::factory()->create(['title' => ['en' => 'Tally batch from Monday'], 'published_at' => now()->subDay()]);
});

it('shows the help, news, why-us and quick-link sidebar on form pages', function (string $url) {
    $this->get($url)->assertOk()
        ->assertSee('kc-sidebar', false)
        ->assertSee('https://wa.me/919876543210', false)
        ->assertSee('tel:9026043415', false)
        ->assertSee('Tally batch from Monday')
        ->assertSee(route('site.students.verification'), false);
})->with([
    'admission' => fn () => route('site.students.registration'),
    'franchise apply' => fn () => route('site.franchise.apply'),
    'verify' => fn () => route('site.students.verification'),
]);

it('adds the admission steps on the admission page and the checklist on the franchise page', function () {
    $this->get(route('site.students.registration'))->assertSee(__('site.sidebar.steps_title'));
    $this->get(route('site.franchise.apply'))->assertSee(__('site.franchise.needs_title'));
});

it('does not repeat the contact card on the contact page but keeps news and links', function () {
    $this->get(route('site.contact'))->assertOk()
        ->assertSee('kc-sidebar', false)
        ->assertSee('Tally batch from Monday')
        ->assertDontSee(__('site.sidebar.help_title'));
});

it('falls back to the main phone for WhatsApp and shows the sidebar in Hindi', function () {
    app(SettingsService::class)->set('site.whatsapp', null);
    $this->get(route('site.students.registration'))->assertSee('https://wa.me/919026043415', false);

    $this->get(route('site.locale', 'hi'));
    $this->get(route('site.students.registration'))->assertSee(__('site.sidebar.help_title', [], 'hi'));
});
