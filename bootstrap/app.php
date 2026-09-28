<?php

use App\Http\Middleware\ResetCurrentInstitute;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Also covers Livewire's update endpoint, which runs in the web group before panel persistent middleware.
        $middleware->web(prepend: [ResetCurrentInstitute::class]);

        // Non-Filament routes that require auth (e.g. impersonation) are admin-only.
        $middleware->redirectGuestsTo(fn () => '/admin/login');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
