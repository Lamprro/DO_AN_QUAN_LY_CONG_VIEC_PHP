<?php

use App\Http\Controllers\TaskUtilityController;
use App\Http\Controllers\UtilityObservationController;
use Illuminate\Support\Facades\Route;

foreach (['' => '', 'projects/{project}' => 'projects.'] as $path => $prefix) {
    Route::prefix($path)->name($prefix)->withoutScopedBindings()->group(function () {
        Route::post('task-filters', [TaskUtilityController::class, 'store'])->name('task-filters.store');
        Route::get('task-filters/{saved}/open', [TaskUtilityController::class, 'open'])->withoutScopedBindings()->whereUuid('saved')->name('task-filters.open');
        Route::delete('task-filters/{saved}', [TaskUtilityController::class, 'destroy'])->withoutScopedBindings()->whereUuid('saved')->name('task-filters.destroy');
        Route::post('tasks/bulk', [TaskUtilityController::class, 'bulk'])->name('tasks.bulk');
    });
}
Route::get('reminders', [UtilityObservationController::class, 'reminders'])->name('reminders');
Route::post('reminders/scan', [UtilityObservationController::class, 'scan'])->middleware('throttle:3,1')->name('reminders.scan');
Route::get('operations', [UtilityObservationController::class, 'operations'])->name('operations');
