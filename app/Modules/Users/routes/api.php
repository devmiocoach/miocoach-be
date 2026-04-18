<?php

use App\Modules\Users\Http\Controllers\Api\V1\CoachInvitationController;
use App\Modules\Users\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('auth:sanctum')->group(function () {

    // Profilo utente autenticato
    Route::get('users/me', [UserController::class, 'me'])->name('users.me');
    Route::patch('users/me', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/me', [UserController::class, 'destroy'])->name('users.destroy');

    // Inviti coach (solo utenti con ruolo coach)
    Route::prefix('coaches/invitations')->name('coaches.invitations.')->middleware('role:coach')->group(function () {
        Route::get('', [CoachInvitationController::class, 'index'])->name('index');
        Route::post('', [CoachInvitationController::class, 'send'])->middleware('throttle:10,60')->name('send');
        Route::delete('{invitation}', [CoachInvitationController::class, 'revoke'])->name('revoke');
    });
});
