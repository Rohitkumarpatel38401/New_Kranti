<?php

use Filament\Facades\Filament;

it('registers three panels with matching guards', function (string $id, string $guard, string $broker, string $path) {
    $panel = Filament::getPanel($id);

    expect($panel->getAuthGuard())->toBe($guard)
        ->and($panel->getAuthPasswordBroker())->toBe($broker)
        ->and($panel->getPath())->toBe($path)
        ->and($panel->getBrandName())->toBe('KCTI');
})->with([
    ['admin', 'admin', 'admins', 'admin'],
    ['institute', 'institute', 'institute_users', 'institute'],
    ['student', 'student', 'students', 'student'],
]);

it('shows login pages', function (string $path) {
    $this->get("/{$path}/login")->assertOk();
})->with(['admin', 'institute', 'student']);

it('redirects guests to the panel login', function (string $path) {
    $this->get("/{$path}")->assertRedirect("/{$path}/login");
})->with(['admin', 'institute', 'student']);
