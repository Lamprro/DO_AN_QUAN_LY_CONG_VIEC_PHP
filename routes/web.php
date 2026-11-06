<?php

/*
| Route map
|   web.php                 public pages, sign-in, account-level pages, workspace group
|   auth.php                password reset + e-mail verification
|   workspace/*.php         everything under /workspaces/{workspace} (membership checked by middleware)
|
| Authorization is NOT done here beyond authentication/membership:
| services check writes, read models / controllers check reads (see docs/ARCHITECTURE.md).
*/

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\GithubIntegrationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TimeAdjustmentController;
use App\Http\Controllers\TimeTrackingController;
use App\Http\Controllers\WorkHoursController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Middleware\RequireVerifiedAccount;
use Illuminate\Support\Facades\Route;

require __DIR__.'/auth.php';

Route::get('/', fn () => auth()->check() ? to_route('workspaces.index') : view('welcome'))->name('home');
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:account-registration')->name('register.store');
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:account-login')->name('login.store');
});
Route::get('/invitations/{token}', [InvitationController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->name('invitations.show');
Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'active.user', RequireVerifiedAccount::class])->group(function () {
    // Account-level pages (not tied to one workspace).
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read'])->whereUuid('notification')->name('notifications.read');
    Route::get('/work-hours', [WorkHoursController::class, 'edit'])->name('work-hours.edit');
    Route::put('/work-hours', [WorkHoursController::class, 'update'])->name('work-hours.update');
    Route::get('/search', [SearchController::class, 'index'])->name('search.index');
    Route::get('/timers/{entry}/adjust', [TimeAdjustmentController::class, 'edit'])->whereUuid('entry')->name('timers.adjust');
    Route::patch('/timers/{entry}/adjust', [TimeAdjustmentController::class, 'update'])->whereUuid('entry')->name('timers.adjust.update');
    Route::get('/timers', [TimeTrackingController::class, 'index'])->name('timers.index');
    Route::post('/timers/{entry}/stop', [TimeTrackingController::class, 'stop'])->whereUuid('entry')->name('timers.stop');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/password', [ProfileController::class, 'password'])->name('password.update');
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:10,1')->name('invitations.accept');
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::get('/workspaces/create', [WorkspaceController::class, 'create'])->name('workspaces.create');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

    Route::prefix('/workspaces/{workspace}')->name('workspaces.')->middleware('workspace.member')->scopeBindings()->group(function () {
        require __DIR__.'/workspace/workspace.php';
        require __DIR__.'/workspace/tasks.php';
        require __DIR__.'/workspace/projects.php';
        require __DIR__.'/workspace/calendar.php';
        require __DIR__.'/workspace/planning.php';
        require __DIR__.'/workspace/reports.php';
    });
});

Route::post('/hooks/github/{project}', [GithubIntegrationController::class, 'receive'])->whereUuid('project')->middleware('throttle:60,1')->name('github.webhook');
