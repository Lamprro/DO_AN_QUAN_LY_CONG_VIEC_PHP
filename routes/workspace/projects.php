<?php

// Projects, project members and the GitHub integration of a project.

use App\Http\Controllers\GithubIntegrationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use Illuminate\Support\Facades\Route;

Route::resource('projects', ProjectController::class);
Route::post('/projects/{project}/archive', [ProjectController::class, 'archive'])->name('projects.archive');
Route::post('/projects/{project}/restore', [ProjectController::class, 'restore'])->name('projects.restore');
Route::prefix('/projects/{project}')->name('projects.')->group(function () {
    Route::get('/members', [ProjectMemberController::class, 'index'])->name('members.index');
    Route::post('/members', [ProjectMemberController::class, 'store'])->name('members.store');
    Route::patch('/members/{projectMember}', [ProjectMemberController::class, 'update'])->name('members.update');
    Route::delete('/members/{projectMember}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');
    Route::get('/github', [GithubIntegrationController::class, 'show'])->name('github.show');
    Route::post('/github', [GithubIntegrationController::class, 'save'])->name('github.save');
});
