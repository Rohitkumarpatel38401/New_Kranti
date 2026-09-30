<?php

namespace App\Providers\Filament;

use App\Filament\Student\Pages\Auth\Login;
use App\Filament\Student\Widgets\StudentOverviewWidget;
use App\Http\Middleware\EndStudentImpersonationOnLogout;
use App\Http\Middleware\EnsureStudentPasswordChanged;
use App\Http\Middleware\SetCurrentInstitute;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;

class StudentPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return KctiPanelDefaults::apply($panel)
            ->middleware([
                EndStudentImpersonationOnLogout::class,
            ])
            ->authMiddleware([
                SetCurrentInstitute::class,
                EnsureStudentPasswordChanged::class,
            ], isPersistent: true)
            ->id('student')
            ->path('student')
            ->login(Login::class)
            ->authGuard('student')
            ->authPasswordBroker('students')
            ->discoverResources(in: app_path('Filament/Student/Resources'), for: 'App\Filament\Student\Resources')
            ->discoverPages(in: app_path('Filament/Student/Pages'), for: 'App\Filament\Student\Pages')
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): View => view('filament.student-impersonation-banner'),
            )
            ->pages([Dashboard::class])
            ->widgets([StudentOverviewWidget::class]);
    }
}
