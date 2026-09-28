<?php

namespace App\Providers\Filament;

use App\Http\Middleware\ResetCurrentInstitute;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Branding and middleware shared by the admin, institute, and student panels.
 */
class KctiPanelDefaults
{
    public static function apply(Panel $panel): Panel
    {
        return $panel
            ->brandName('KCTI')
            ->font('Plus Jakarta Sans')
            ->colors([
                'primary' => Color::hex('#4f46e5'),
                'gray' => Color::Slate,
            ])
            ->darkMode(true)
            ->middleware([
                ResetCurrentInstitute::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
