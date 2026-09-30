<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Pages\Auth\Login;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return KctiPanelDefaults::apply($panel)
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->authGuard('admin')
            ->authPasswordBroker('admins')
            ->strictAuthorization()
            ->databaseNotifications()
            ->passwordReset()
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            // Sidebar: daily work first, setup last.
            ->navigationGroups(['Admissions', 'Exams', 'Franchise Network', 'Wallets', 'Fees', 'Attendance', 'Academics', 'Website', 'HQ Team'])
            ->pages([Dashboard::class]);
    }
}
