<?php

// Personal/project calendar, project timeline and team calendar proposals.

use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\TeamCalendarController;
use App\Http\Controllers\TimelineController;
use App\Http\Middleware\RequireFullFeatureSchema;
use Illuminate\Support\Facades\Route;

Route::get('/calendar', [CalendarEventController::class, 'index'])->name('calendar.index');
Route::get('/calendar/create', [CalendarEventController::class, 'create'])->name('calendar.create');
Route::post('/calendar', [CalendarEventController::class, 'store'])->name('calendar.store');
// {event} is not a relation name of Workspace, so scoping is checked in the controller.
Route::get('/calendar/{event}', [CalendarEventController::class, 'show'])->withoutScopedBindings()->whereUuid('event')->name('calendar.show');
Route::get('/calendar/{event}/edit', [CalendarEventController::class, 'edit'])->withoutScopedBindings()->whereUuid('event')->name('calendar.edit');
Route::patch('/calendar/{event}', [CalendarEventController::class, 'update'])->withoutScopedBindings()->whereUuid('event')->name('calendar.update');
Route::delete('/calendar/{event}', [CalendarEventController::class, 'destroy'])->withoutScopedBindings()->whereUuid('event')->name('calendar.destroy');
Route::get('/projects/{project}/timeline', [TimelineController::class, 'index'])->name('projects.timeline');

Route::middleware(RequireFullFeatureSchema::class)->group(function () {
    Route::get('/team-calendar', [TeamCalendarController::class, 'index'])->name('calendar.team');
    Route::post('/team-calendar', [TeamCalendarController::class, 'store'])->name('calendar.propose');
    Route::post('/team-calendar/{proposal}/{action}', [TeamCalendarController::class, 'respond'])->whereUuid('proposal')->whereIn('action', ['accept', 'decline', 'cancel'])->name('calendar.respond');
});
