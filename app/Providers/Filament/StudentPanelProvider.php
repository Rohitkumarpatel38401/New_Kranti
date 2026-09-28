<?php

namespace App\Providers\Filament;

use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;

class StudentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return KctiPanelDefaults::apply($panel)
            ->id('student')
            ->path('student')
            ->login()
            ->authGuard('student')
            ->authPasswordBroker('students')
            ->discoverResources(in: app_path('Filament/Student/Resources'), for: 'App\Filament\Student\Resources')
            ->discoverPages(in: app_path('Filament/Student/Pages'), for: 'App\Filament\Student\Pages')
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class]);
    }
}
