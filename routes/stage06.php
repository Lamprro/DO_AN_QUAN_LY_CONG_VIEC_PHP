<?php

use App\Http\Controllers\AiRunController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::post('/reports', [ReportController::class, 'store'])->name('reports.store');
Route::get('/reports/{snapshot}', [ReportController::class, 'show'])->withoutScopedBindings()->whereUuid('snapshot')->name('reports.show');
Route::get('/reports/{snapshot}/print', [ReportController::class, 'print'])->withoutScopedBindings()->whereUuid('snapshot')->name('reports.print');
Route::get('/reports/{snapshot}/export', [ReportController::class, 'export'])->withoutScopedBindings()->whereUuid('snapshot')->name('reports.export');
Route::get('/ai-runs', [AiRunController::class, 'index'])->name('ai-runs.index');
Route::get('/ai-runs/create', [AiRunController::class, 'create'])->name('ai-runs.create');
Route::post('/ai-runs', [AiRunController::class, 'store'])->middleware('throttle:10,1')->name('ai-runs.store');
Route::get('/ai-runs/{run}', [AiRunController::class, 'show'])->withoutScopedBindings()->whereUuid('run')->name('ai-runs.show');
Route::post('/ai-runs/{run}/cancel', [AiRunController::class, 'cancel'])->withoutScopedBindings()->whereUuid('run')->name('ai-runs.cancel');
Route::post('/ai-runs/{run}/retry', [AiRunController::class, 'retry'])->withoutScopedBindings()->whereUuid('run')->middleware('throttle:10,1')->name('ai-runs.retry');
