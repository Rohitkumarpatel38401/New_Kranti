<?php

namespace App\Providers\Filament;

use App\Filament\Institute\Pages\Auth\EditProfile;
use App\Filament\Institute\Pages\Auth\Login;
use App\Http\Middleware\EndImpersonationOnLogout;
use App\Http\Middleware\SetCurrentInstitute;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

class InstitutePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return KctiPanelDefaults::apply($panel)
            ->middleware([
                EndImpersonationOnLogout::class,
            ])
            ->authMiddleware([
                SetCurrentInstitute::class,
            ], isPersistent: true)
            ->id('institute')
            ->path('institute')
            ->login(Login::class)
            ->authGuard('institute')
            ->authPasswordBroker('institute_users')
            ->passwordReset()
            ->profile(EditProfile::class, isSimple: false)
            ->discoverResources(in: app_path('Filament/Institute/Resources'), for: 'App\Filament\Institute\Resources')
            ->discoverPages(in: app_path('Filament/Institute/Pages'), for: 'App\Filament\Institute\Pages')
            ->discoverWidgets(in: app_path('Filament/Institute/Widgets'), for: 'App\Filament\Institute\Widgets')
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('filament.impersonation-banner'),
            )
            ->navigationItems([
                NavigationItem::make('My profile')
                    ->url(fn (): string => EditProfile::getUrl())
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.institute.auth.profile'))
                    ->sort(99),
            ])
            ->pages([Dashboard::class]);
    }
}
