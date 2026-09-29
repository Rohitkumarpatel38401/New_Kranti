<?php

use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\ReceiptController;
use Illuminate\Support\Facades\Route;

// Replaced by the public website in Phase 6.
Route::get('/', fn () => view('welcome'));

Route::get('/impersonate/institutes/{institute}', [ImpersonationController::class, 'start'])
    ->middleware(['auth:admin', 'signed'])
    ->name('impersonation.start');

Route::get('/impersonation/leave', [ImpersonationController::class, 'leave'])
    ->name('impersonation.leave');

// Receipts print for the owning institute and for HQ (policy-checked in the controller).
Route::get('/receipts/{payment}', [ReceiptController::class, 'show'])
    ->middleware('auth:admin,institute')
    ->name('receipts.show');
