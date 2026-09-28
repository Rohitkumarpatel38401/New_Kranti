<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;

class InstitutePanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return KctiPanelDefaults::apply($panel)
            ->id('institute')
            ->path('institute')
            ->login()
            ->authGuard('institute')
            ->authPasswordBroker('institute_users')
            ->passwordReset()
            ->discoverResources(in: app_path('Filament/Institute/Resources'), for: 'App\Filament\Institute\Resources')
            ->discoverPages(in: app_path('Filament/Institute/Pages'), for: 'App\Filament\Institute\Pages')
            ->discoverWidgets(in: app_path('Filament/Institute/Widgets'), for: 'App\Filament\Institute\Widgets')
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class]);
    }
}
