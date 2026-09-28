<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\InstitutePanelProvider;
use App\Providers\Filament\StudentPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    InstitutePanelProvider::class,
    StudentPanelProvider::class,
];
