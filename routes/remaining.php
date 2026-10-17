<?php

use App\Http\Controllers\GithubIntegrationController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanningAiController;
use App\Http\Controllers\TimeTrackingController;
use Illuminate\Support\Facades\Route;

Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
Route::get('/plan-impact', [PlanController::class, 'impact'])->name('plans.impact');
Route::get('/plans/{plan}', [PlanController::class, 'show'])->withoutScopedBindings()->whereUuid('plan')->name('plans.show');
Route::post('/plans/{plan}/{action}', [PlanController::class, 'action'])->withoutScopedBindings()->whereUuid('plan')->where('action', 'propose|approve|reject|apply')->name('plans.action');
Route::post('/timer/{task}', [TimeTrackingController::class, 'start'])->withoutScopedBindings()->whereUuid('task')->name('timer.start');

Route::get('/planning-ai/create', [PlanningAiController::class, 'create'])->name('planning-ai.create');
Route::post('/planning-ai', [PlanningAiController::class, 'store'])->middleware('throttle:10,1')->name('planning-ai.store');

Route::post('/plans/{plan}/schedule', [PlanController::class, 'schedule'])->withoutScopedBindings()->whereUuid('plan')->name('plans.schedule');
Route::get('/projects/{project}/github', [GithubIntegrationController::class, 'show'])->name('projects.github.show');
Route::post('/projects/{project}/github', [GithubIntegrationController::class, 'save'])->name('projects.github.save');
