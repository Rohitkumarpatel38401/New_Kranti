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
            // ->viteTheme('resources/css/filament/admin/theme.css')
            // ->sidebarWidth('14rem')
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
                    ->group('Institute setup')
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.institute.auth.profile'))
                    ->sort(3),
            ])
            // Sidebar: daily work first, setup last.
            ->navigationGroups(['Admissions', 'Fees', 'Attendance', 'Exams', 'Wallet', 'Institute setup'])
            // Topbar: notification bell (polled) and a "Visit website" button beside the profile menu.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): View => view('filament.visit-website'),
            )
            ->pages([Dashboard::class]);
    }
}
