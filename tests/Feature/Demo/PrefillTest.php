<?php

use App\Filament\Admin\Pages\Auth\Login as AdminLogin;
use App\Filament\Institute\Pages\Auth\Login as InstituteLogin;
use App\Filament\Student\Pages\Auth\Login as StudentLogin;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('prefills the demo credentials on local', function (string $panel, string $page, string $field, string $value) {
    app()['env'] = 'local';
    Filament::setCurrentPanel($panel);

    Livewire::test($page)->assertSet("data.{$field}", $value)->assertSet('data.password', 'admin123');
})->with([
    ['admin', AdminLogin::class, 'email', 'admin@example.com'],
    ['institute', InstituteLogin::class, 'email', 'institue@example.com'],
    ['student', StudentLogin::class, 'login', 'student@example.com'],
]);

it('never prefills outside local', function (string $panel, string $page, string $field) {
    Filament::setCurrentPanel($panel);

    Livewire::test($page)->assertSet("data.{$field}", null)->assertSet('data.password', null);
})->with([
    ['admin', AdminLogin::class, 'email'],
    ['institute', InstituteLogin::class, 'email'],
    ['student', StudentLogin::class, 'login'],
]);

it('does not prefill on local when turned off', function () {
    app()['env'] = 'local';
    config(['kcti.demo.prefill' => false]);
    Filament::setCurrentPanel('admin');

    Livewire::test(AdminLogin::class)->assertSet('data.email', null);
});
