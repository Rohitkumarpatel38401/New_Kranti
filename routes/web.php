<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

// Website language switch (EN | हिं).
Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, App\Http\Middleware\SetLocale::SUPPORTED, true), 404);
    session(['locale' => $locale]);

    return redirect()->back(fallback: '/');
})->name('site.locale');

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

// Certificates and marksheets for the owning institute and HQ (policy-checked in the controller).
Route::middleware('auth:admin,institute')->prefix('documents')->name('documents.')->group(function () {
    Route::get('/certificates/{certificate}', [DocumentController::class, 'certificate'])->name('certificate');
    Route::get('/marksheets/{result}', [DocumentController::class, 'marksheet'])->name('marksheet');
    Route::get('/id-cards/{enrollment}', [DocumentController::class, 'idCard'])->name('id-card');
    Route::get('/admit-cards/{enrollment}', [DocumentController::class, 'admitCard'])->name('admit-card');
});

// Public certificate verification (QR codes point here). Lookups are rate-limited against guessing.
Route::get('/students/verification', [VerificationController::class, 'form'])->name('site.students.verification');
Route::post('/students/verification', [VerificationController::class, 'lookup'])
    ->middleware('throttle:10,1')
    ->name('site.students.verification.lookup');
Route::get('/verify/{token}', [VerificationController::class, 'show'])
    ->middleware('throttle:30,1')
    ->name('site.verify');
