<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'store'])->middleware('throttle:account-recovery')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'edit'])->where('token', '[A-Za-z0-9]{64}')->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'update'])->middleware('throttle:account-reset')->name('password.reset.store');
});
Route::middleware(['auth', 'active.user'])->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed:relative', 'throttle:account-verification-link'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:account-verification-mail')->name('verification.send');
});
