<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Auth\Login;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return KctiPanelDefaults::apply($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            // ->viteTheme('resources/css/filament/admin/theme.css')
            // ->sidebarWidth('16rem')
            ->login(Login::class)
            ->authGuard('admin')
            ->authPasswordBroker('admins')
            ->strictAuthorization()
            ->passwordReset()
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            // Sidebar: daily work first, setup last.
            ->navigationGroups(['Admissions', 'Exams', 'Franchise Network', 'Wallets', 'Fees', 'Attendance', 'Academics', 'Website', 'HQ Team'])
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
