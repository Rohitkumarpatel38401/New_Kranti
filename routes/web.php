<?php

use App\Http\Controllers\ImpersonationController;
use Illuminate\Support\Facades\Route;

// Replaced by the public website in Phase 6.
Route::get('/', fn () => view('welcome'));

Route::get('/impersonate/institutes/{institute}', [ImpersonationController::class, 'start'])
    ->middleware(['auth:admin', 'signed'])
    ->name('impersonation.start');

Route::get('/impersonation/leave', [ImpersonationController::class, 'leave'])
    ->name('impersonation.leave');
