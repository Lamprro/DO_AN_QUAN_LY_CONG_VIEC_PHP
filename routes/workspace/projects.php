<?php

// Projects, project members and the GitHub integration of a project.

use App\Http\Controllers\GithubIntegrationController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::resource('projects', ProjectController::class);
Route::post('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
Route::prefix('/projects/{project}')->name('projects.')->group(function () {
    Route::get('/members', [ProjectController::class, 'members'])->name('members.index');
    Route::post('/members', [ProjectController::class, 'storeMember'])->name('members.store');
    Route::patch('/members/{projectMember}', [ProjectController::class, 'updateMember'])->name('members.update');
    Route::delete('/members/{projectMember}', [ProjectController::class, 'removeMember'])->name('members.destroy');
    Route::get('/github', [GithubIntegrationController::class, 'show'])->name('github.show');
    Route::post('/github', [GithubIntegrationController::class, 'save'])->name('github.save');
});
