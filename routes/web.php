<?php

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\GithubIntegrationController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TimeAdjustmentController;
use App\Http\Controllers\TimeTrackingController;
use App\Http\Controllers\WorkHoursController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use App\Http\Middleware\RequireVerifiedAccount;
use Illuminate\Support\Facades\Route;

require __DIR__.'/account_security.php';

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
    require __DIR__.'/stage05.php';
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
        require __DIR__.'/account_security.php';

        Route::get('/', [WorkspaceController::class, 'show'])->name('show');
        Route::get('/edit', [WorkspaceController::class, 'edit'])->name('edit');
        Route::patch('/', [WorkspaceController::class, 'update'])->name('update');
        Route::delete('/', [WorkspaceController::class, 'destroy'])->name('destroy');
        Route::post('/select', [WorkspaceController::class, 'select'])->name('select');
        Route::post('/leave', [WorkspaceController::class, 'leave'])->name('leave');
        Route::post('/transfer-owner', [WorkspaceController::class, 'transfer'])->name('transfer');
        require __DIR__.'/task_workflow.php';
        require __DIR__.'/calendar.php';
        require __DIR__.'/full_features.php';
        require __DIR__.'/stage06.php';
        require __DIR__.'/remaining.php';
        require __DIR__.'/task_utilities.php';
        Route::resource('projects', ProjectController::class);
        Route::post('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
        Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
        Route::prefix('/projects/{project}')->name('projects.')->group(function () {
            Route::get('/members', [ProjectMemberController::class, 'index'])->name('members.index');
            Route::post('/members', [ProjectMemberController::class, 'store'])->name('members.store');
            Route::patch('/members/{projectMember}', [ProjectMemberController::class, 'update'])->name('members.update');
            Route::delete('/members/{projectMember}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');
            Route::resource('tasks', TaskController::class);
            Route::post('/tasks/{task}/archive', [TaskController::class, 'archive'])->name('tasks.archive');
            Route::post('/tasks/{task}/restore', [TaskController::class, 'restore'])->name('tasks.restore');
        });
        Route::resource('tasks', TaskController::class);
        Route::post('/tasks/{task}/archive', [TaskController::class, 'archive'])->name('tasks.archive');
        Route::post('/tasks/{task}/restore', [TaskController::class, 'restore'])->name('tasks.restore');
        Route::resource('roles', RoleController::class)->except('show');
        Route::get('/members', [WorkspaceMemberController::class, 'index'])->name('members.index');
        Route::patch('/members/{member}', [WorkspaceMemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('members.destroy');
        Route::post('/invitations', [InvitationController::class, 'store'])->middleware('throttle:10,1')->name('invitations.store');
        Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->whereUuid('invitation')->name('invitations.destroy');
    });
});

Route::post('/hooks/github/{project}', [GithubIntegrationController::class, 'receive'])->whereUuid('project')->middleware('throttle:60,1')->name('github.webhook');
