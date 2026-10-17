<?php

use App\Http\Controllers\CalendarEventController;
use App\Http\Controllers\TimelineController;
use Illuminate\Support\Facades\Route;

Route::get('/calendar', [CalendarEventController::class, 'index'])->name('calendar.index');
Route::get('/calendar/create', [CalendarEventController::class, 'create'])->name('calendar.create');
Route::post('/calendar', [CalendarEventController::class, 'store'])->name('calendar.store');
// Explicit scope checks in controller: the route parameter is "event", not the model relation name.
Route::get('/calendar/{event}', [CalendarEventController::class, 'show'])->withoutScopedBindings()->whereUuid('event')->name('calendar.show');
Route::get('/calendar/{event}/edit', [CalendarEventController::class, 'edit'])->withoutScopedBindings()->whereUuid('event')->name('calendar.edit');
Route::patch('/calendar/{event}', [CalendarEventController::class, 'update'])->withoutScopedBindings()->whereUuid('event')->name('calendar.update');
Route::delete('/calendar/{event}', [CalendarEventController::class, 'destroy'])->withoutScopedBindings()->whereUuid('event')->name('calendar.destroy');
Route::get('/projects/{project}/timeline', [TimelineController::class, 'index'])->name('projects.timeline');
