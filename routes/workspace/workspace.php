<?php

// Workspace settings, ownership, members, invitations and roles.

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WorkspaceController::class, 'show'])->name('show');
Route::get('/edit', [WorkspaceController::class, 'edit'])->name('edit');
Route::patch('/', [WorkspaceController::class, 'update'])->name('update');
Route::delete('/', [WorkspaceController::class, 'destroy'])->name('destroy');
Route::post('/select', [WorkspaceController::class, 'select'])->name('select');
Route::post('/leave', [WorkspaceController::class, 'leave'])->name('leave');
Route::post('/transfer-owner', [WorkspaceController::class, 'transfer'])->name('transfer');

Route::get('/members', [WorkspaceController::class, 'members'])->name('members.index');
Route::patch('/members/{member}', [WorkspaceController::class, 'updateMember'])->name('members.update');
Route::delete('/members/{member}', [WorkspaceController::class, 'removeMember'])->name('members.destroy');
Route::post('/invitations', [InvitationController::class, 'store'])->middleware('throttle:10,1')->name('invitations.store');
Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->whereUuid('invitation')->name('invitations.destroy');
Route::get('/roles', [WorkspaceController::class, 'roles'])->name('roles.index');
Route::get('/roles/create', [WorkspaceController::class, 'createRole'])->name('roles.create');
Route::post('/roles', [WorkspaceController::class, 'storeRole'])->name('roles.store');
Route::get('/roles/{role}/edit', [WorkspaceController::class, 'editRole'])->name('roles.edit');
Route::match(['PUT', 'PATCH'], '/roles/{role}', [WorkspaceController::class, 'updateRole'])->name('roles.update');
Route::delete('/roles/{role}', [WorkspaceController::class, 'destroyRole'])->name('roles.destroy');
