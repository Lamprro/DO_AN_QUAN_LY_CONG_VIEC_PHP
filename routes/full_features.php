<?php

use App\Http\Controllers\TaskCollaborationController;
use App\Http\Controllers\TaskTemplateController;
use App\Http\Controllers\TeamCalendarController;
use App\Http\Middleware\RequireFullFeatureSchema;
use Illuminate\Support\Facades\Route;

Route::middleware(RequireFullFeatureSchema::class)->group(function () {
    Route::get('/task-templates', [TaskTemplateController::class, 'index'])->name('templates.index');
    Route::post('/task-templates', [TaskTemplateController::class, 'store'])->name('templates.store');
    Route::get('/task-templates/{template}', [TaskTemplateController::class, 'show'])->whereUuid('template')->name('templates.show');
    Route::put('/task-templates/{template}', [TaskTemplateController::class, 'update'])->whereUuid('template')->name('templates.update');
    Route::post('/task-templates/{template}/instantiate', [TaskTemplateController::class, 'instantiate'])->whereUuid('template')->name('templates.instantiate');
    Route::post('/task-templates/{template}/{action}', [TaskTemplateController::class, 'state'])->whereUuid('template')->whereIn('action', ['pause', 'resume', 'archive'])->name('templates.state');

    Route::post('/collaboration/tasks/{task}/watchers', [TaskCollaborationController::class, 'store'])->whereUuid('task')->name('tasks.watchers.store');
    Route::delete('/collaboration/tasks/{task}/watchers/{user}', [TaskCollaborationController::class, 'destroy'])->whereUuid('task')->whereUuid('user')->name('tasks.watchers.destroy');
    Route::get('/team-calendar', [TeamCalendarController::class, 'index'])->name('calendar.team');
    Route::post('/team-calendar', [TeamCalendarController::class, 'store'])->name('calendar.propose');
    Route::post('/team-calendar/{proposal}/{action}', [TeamCalendarController::class, 'respond'])->whereUuid('proposal')->whereIn('action', ['accept', 'decline', 'cancel'])->name('calendar.respond');

});
