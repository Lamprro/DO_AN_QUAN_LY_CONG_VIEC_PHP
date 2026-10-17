<?php

use App\Http\Controllers\TaskWorkflowController as Workflow;
use Illuminate\Support\Facades\Route;

foreach (['projects/{project}/tasks' => 'projects.tasks.', 'tasks' => 'tasks.'] as $path => $prefix) {
    Route::get($path.'/tree', [Workflow::class, 'tree'])->name($prefix.'tree');
    Route::patch($path.'/{task}/status', [Workflow::class, 'status'])->name($prefix.'status.update');
    Route::post($path.'/{task}/dependencies', [Workflow::class, 'dependency'])->name($prefix.'dependencies.store');
    Route::delete($path.'/{task}/dependencies/{dependency}', [Workflow::class, 'removeDependency'])->name($prefix.'dependencies.destroy');
    Route::post($path.'/{task}/blockers', [Workflow::class, 'blocker'])->name($prefix.'blockers.store');
    Route::patch($path.'/{task}/blockers/{blocker}', [Workflow::class, 'blocker'])->name($prefix.'blockers.update');
    Route::post($path.'/{task}/comments', [Workflow::class, 'comment'])->name($prefix.'comments.store');
    Route::patch($path.'/{task}/comments/{activity}', [Workflow::class, 'comment'])->name($prefix.'comments.update');
    Route::delete($path.'/{task}/comments/{activity}', [Workflow::class, 'comment'])->name($prefix.'comments.destroy');
    Route::post($path.'/{task}/attachments', [Workflow::class, 'upload'])->name($prefix.'attachments.store');
    Route::get($path.'/{task}/attachments/{activity}', [Workflow::class, 'download'])->name($prefix.'attachments.download');
    Route::delete($path.'/{task}/attachments/{activity}', [Workflow::class, 'removeFile'])->name($prefix.'attachments.destroy');
}
