<?php

use App\Services\SettingsService;

it('renders the home page inside the site layout with header, footer and assets', function () {
    $this->get(route('site.home'))
        ->assertOk()
        ->assertSee('css/site/kc-main.css', false)
        ->assertSee('css/site/site-theme.css', false)
        ->assertSee('js/site/kc-navbar.js', false)
        ->assertSee('kc-main-nav', false)
        ->assertSee('kc-footer', false)
        ->assertSee(route('site.courses.index'), false)
        ->assertSee(route('site.students.verification'), false);
});

it('links all three logins from the login menu', function () {
    $this->get(route('site.home'))
        ->assertSee(url('/student/login'), false)
        ->assertSee(url('/institute/login'), false)
        ->assertSee(url('/admin/login'), false);
});

it('shows contact details and the announcement from settings', function () {
    app(SettingsService::class)->setMany([
        'site.phone' => '9000011111',
        'site.email' => 'office@kcti.test',
        'site.announcement' => ['en' => 'New batch from Monday', 'hi' => 'सोमवार से नया बैच'],
        'site.coupon' => 'KCTI20',
    ]);

    $this->get(route('site.home'))
        ->assertSee('9000011111')
        ->assertSee('office@kcti.test')
        ->assertSee('New batch from Monday')
        ->assertSee('KCTI20');
});

it('hides the announcement bar when none is set', function () {
    $this->get(route('site.home'))->assertDontSee('kcAnnouncementBar', false);
});

it('switches the whole shell to Hindi', function () {
    $this->get(route('site.locale', 'hi'));

    $this->get(route('site.home'))
        ->assertSee('lang="hi"', false)
        ->assertSee('प्रमाणपत्र सत्यापन')
        ->assertSee('संपर्क करें');
});

it('offers the language switch and a theme toggle that is applied before paint', function () {
    $this->get(route('site.home'))
        ->assertSee(route('site.locale', 'hi'), false)
        ->assertSee('data-theme-toggle', false)
        ->assertSee("localStorage.getItem('kcti-theme')", false);
});
