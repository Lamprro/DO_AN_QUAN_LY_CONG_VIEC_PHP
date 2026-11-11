<?php

// Tasks exist in a project (/projects/{project}/tasks) or, in a personal workspace, without one (/tasks).

use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskTemplateController;
use App\Http\Controllers\TaskWorkflowController;
use App\Http\Controllers\TaskWorkflowController as Workflow;
use App\Http\Controllers\TimeTrackingController;
use App\Http\Middleware\RequireFullFeatureSchema;
use Illuminate\Support\Facades\Route;

// Registered before the resources so "tasks/tree" is not captured by "tasks/{task}".
foreach (['projects/{project}/tasks' => 'projects.tasks.', 'tasks' => 'tasks.'] as $path => $prefix) {
    Route::post($path.'/{task}/resolve-edit', [TaskController::class, 'resolveEdit'])->name($prefix.'resolve-edit');
    Route::get($path.'/tree', [Workflow::class, 'tree'])->name($prefix.'tree');
    Route::get($path.'/{task}/status', [Workflow::class, 'statusForm'])->name($prefix.'status.edit');
    Route::patch($path.'/{task}/status', [Workflow::class, 'status'])->name($prefix.'status.update');
    Route::post($path.'/{task}/dependencies', [Workflow::class, 'dependency'])->name($prefix.'dependencies.store');
    Route::delete($path.'/{task}/dependencies/{dependency}', [Workflow::class, 'removeDependency'])->name($prefix.'dependencies.destroy');
    Route::post($path.'/{task}/blockers', [Workflow::class, 'blocker'])->name($prefix.'blockers.store');
    Route::patch($path.'/{task}/blockers/{blocker}', [Workflow::class, 'blocker'])->name($prefix.'blockers.update');
    Route::post($path.'/{task}/comments', [Workflow::class, 'comment'])->name($prefix.'comments.store');
    Route::patch($path.'/{task}/comments/{activity}', [Workflow::class, 'comment'])->name($prefix.'comments.update');
    Route::delete($path.'/{task}/comments/{activity}', [Workflow::class, 'deleteComment'])->name($prefix.'comments.destroy');
    Route::post($path.'/{task}/attachments', [Workflow::class, 'upload'])->name($prefix.'attachments.store');
    Route::get($path.'/{task}/attachments/{activity}', [Workflow::class, 'download'])->name($prefix.'attachments.download');
    Route::delete($path.'/{task}/attachments/{activity}', [Workflow::class, 'removeFile'])->name($prefix.'attachments.destroy');
}

// Saved filters and bulk actions, for both scopes.
foreach (['' => '', 'projects/{project}' => 'projects.'] as $path => $prefix) {
    Route::prefix($path)->name($prefix)->withoutScopedBindings()->group(function () {
        Route::post('task-filters', [TaskController::class, 'storeFilter'])->name('task-filters.store');
        Route::get('task-filters/{saved}/open', [TaskController::class, 'openFilter'])->withoutScopedBindings()->whereUuid('saved')->name('task-filters.open');
        Route::delete('task-filters/{saved}', [TaskController::class, 'destroyFilter'])->withoutScopedBindings()->whereUuid('saved')->name('task-filters.destroy');
        Route::post('tasks/bulk', [TaskController::class, 'bulk'])->name('tasks.bulk');
    });
}

Route::prefix('/projects/{project}')->name('projects.')->group(function () {
    Route::resource('tasks', TaskController::class);
    Route::post('/tasks/{task}/archive', [TaskController::class, 'archive'])->name('tasks.archive');
    Route::post('/tasks/{task}/restore', [TaskController::class, 'restore'])->name('tasks.restore');
});
Route::resource('tasks', TaskController::class);
Route::post('/tasks/{task}/archive', [TaskController::class, 'archive'])->name('tasks.archive');
Route::post('/tasks/{task}/restore', [TaskController::class, 'restore'])->name('tasks.restore');

Route::post('/timer/{task}', [TimeTrackingController::class, 'start'])->withoutScopedBindings()->whereUuid('task')->name('timer.start');

// Features backed by the 2026_10_23 migration (503 page until it is applied).
Route::middleware(RequireFullFeatureSchema::class)->group(function () {
    Route::get('/task-templates', [TaskTemplateController::class, 'index'])->name('templates.index');
    Route::post('/task-templates', [TaskTemplateController::class, 'store'])->name('templates.store');
    Route::get('/task-templates/{template}', [TaskTemplateController::class, 'show'])->whereUuid('template')->name('templates.show');
    Route::put('/task-templates/{template}', [TaskTemplateController::class, 'update'])->whereUuid('template')->name('templates.update');
    Route::post('/task-templates/{template}/instantiate', [TaskTemplateController::class, 'instantiate'])->whereUuid('template')->name('templates.instantiate');
    Route::post('/task-templates/{template}/{action}', [TaskTemplateController::class, 'state'])->whereUuid('template')->whereIn('action', ['pause', 'resume', 'archive'])->name('templates.state');
    Route::post('/collaboration/tasks/{task}/watchers', [TaskWorkflowController::class, 'watch'])->whereUuid('task')->name('tasks.watchers.store');
    Route::delete('/collaboration/tasks/{task}/watchers/{user}', [TaskWorkflowController::class, 'unwatch'])->whereUuid('task')->whereUuid('user')->name('tasks.watchers.destroy');
});
