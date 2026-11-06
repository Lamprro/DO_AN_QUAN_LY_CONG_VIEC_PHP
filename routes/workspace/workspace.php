<?php

// Workspace settings, ownership, members, invitations and roles.

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\WorkspaceController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WorkspaceController::class, 'show'])->name('show');
Route::get('/edit', [WorkspaceController::class, 'edit'])->name('edit');
Route::patch('/', [WorkspaceController::class, 'update'])->name('update');
Route::delete('/', [WorkspaceController::class, 'destroy'])->name('destroy');
Route::post('/select', [WorkspaceController::class, 'select'])->name('select');
Route::post('/leave', [WorkspaceController::class, 'leave'])->name('leave');
Route::post('/transfer-owner', [WorkspaceController::class, 'transfer'])->name('transfer');

Route::get('/members', [WorkspaceMemberController::class, 'index'])->name('members.index');
Route::patch('/members/{member}', [WorkspaceMemberController::class, 'update'])->name('members.update');
Route::delete('/members/{member}', [WorkspaceMemberController::class, 'destroy'])->name('members.destroy');
Route::post('/invitations', [InvitationController::class, 'store'])->middleware('throttle:10,1')->name('invitations.store');
Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->whereUuid('invitation')->name('invitations.destroy');
Route::resource('roles', RoleController::class)->except('show');
