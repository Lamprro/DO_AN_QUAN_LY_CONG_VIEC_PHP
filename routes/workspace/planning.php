<?php

// Plan versions (draft -> proposed -> approved -> applied) and planning AI requests.

use App\Http\Controllers\AiRunController;
use App\Http\Controllers\PlanController;
use Illuminate\Support\Facades\Route;

Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
Route::get('/plan-impact', [PlanController::class, 'impact'])->name('plans.impact');
Route::get('/plans/{plan}', [PlanController::class, 'show'])->withoutScopedBindings()->whereUuid('plan')->name('plans.show');
Route::get('/plans/{plan}/preview', [PlanController::class, 'preview'])->withoutScopedBindings()->whereUuid('plan')->name('plans.preview');
Route::post('/plans/{plan}/schedule', [PlanController::class, 'schedule'])->withoutScopedBindings()->whereUuid('plan')->name('plans.schedule');
Route::post('/plans/{plan}/{action}', [PlanController::class, 'action'])->withoutScopedBindings()->whereUuid('plan')->where('action', 'propose|approve|reject|apply')->name('plans.action');

Route::get('/planning-ai/create', [AiRunController::class, 'createPlanning'])->name('planning-ai.create');
Route::post('/planning-ai', [AiRunController::class, 'storePlanning'])->middleware('throttle:10,1')->name('planning-ai.store');
