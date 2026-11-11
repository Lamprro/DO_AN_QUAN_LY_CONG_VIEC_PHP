<?php

// Personal/project calendar, project timeline and team calendar proposals.

use App\Http\Controllers\CalendarController;
use App\Http\Middleware\RequireFullFeatureSchema;
use Illuminate\Support\Facades\Route;

Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
Route::get('/calendar/create', [CalendarController::class, 'create'])->name('calendar.create');
Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store');
// {event} is not a relation name of Workspace, so scoping is checked in the controller.
Route::get('/calendar/{event}', [CalendarController::class, 'show'])->withoutScopedBindings()->whereUuid('event')->name('calendar.show');
Route::get('/calendar/{event}/edit', [CalendarController::class, 'edit'])->withoutScopedBindings()->whereUuid('event')->name('calendar.edit');
Route::patch('/calendar/{event}', [CalendarController::class, 'update'])->withoutScopedBindings()->whereUuid('event')->name('calendar.update');
Route::delete('/calendar/{event}', [CalendarController::class, 'destroy'])->withoutScopedBindings()->whereUuid('event')->name('calendar.destroy');
Route::get('/projects/{project}/timeline', [CalendarController::class, 'timeline'])->name('projects.timeline');

Route::middleware(RequireFullFeatureSchema::class)->group(function () {
    Route::get('/team-calendar', [CalendarController::class, 'team'])->name('calendar.team');
    Route::post('/team-calendar', [CalendarController::class, 'propose'])->name('calendar.propose');
    Route::post('/team-calendar/{proposal}/{action}', [CalendarController::class, 'respond'])->whereUuid('proposal')->whereIn('action', ['accept', 'decline', 'cancel'])->name('calendar.respond');
});
