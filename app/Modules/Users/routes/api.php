<?php

use App\Modules\Users\Http\Controllers\Api\V1\AvailabilityController;
use App\Modules\Users\Http\Controllers\Api\V1\CertificationController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachInvitationController;
use App\Modules\Users\Http\Controllers\Api\V1\PublicCoachController;
use App\Modules\Users\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware(['jwt.auth', 'verified.email'])->group(function () {

    // Profilo utente autenticato
    Route::get('users/me', [UserController::class, 'me'])->name('users.me');
    Route::patch('users/me', [UserController::class, 'update'])->middleware('throttle:30,1')->name('users.update');
    Route::delete('users/me', [UserController::class, 'destroy'])->middleware('throttle:5,1')->name('users.destroy');

    // Profilo coach
    Route::prefix('coaches/me')->name('coaches.me.')->middleware('role:coach')->group(function () {
        Route::get('', [CoachController::class, 'show'])->middleware('throttle:60,1')->name('show');
        Route::patch('', [CoachController::class, 'update'])->middleware('throttle:30,1')->name('update');
        Route::get('clients', [CoachController::class, 'clients'])->middleware('throttle:60,1')->name('clients');
        Route::put('publish', [CoachController::class, 'publish'])->middleware('throttle:10,1')->name('publish');
        Route::put('availability', [AvailabilityController::class, 'replace'])->middleware('throttle:20,1')->name('availability');
        Route::post('certifications', [CertificationController::class, 'store'])->middleware('throttle:20,1')->name('certifications.store');
        Route::delete('certifications/{certification}', [CertificationController::class, 'destroy'])->middleware('throttle:20,1')->name('certifications.destroy');
    });

    // Profilo client
    Route::prefix('clients/me')->name('clients.me.')->middleware('role:client')->group(function () {
        Route::patch('', [ClientController::class, 'update'])->middleware('throttle:30,1')->name('update');
    });

    // Inviti coach
    Route::prefix('coaches/invitations')->name('coaches.invitations.')->middleware('role:coach')->group(function () {
        Route::get('', [CoachInvitationController::class, 'index'])->middleware('throttle:60,1')->name('index');
        Route::post('', [CoachInvitationController::class, 'send'])->middleware('throttle:10,60')->name('send');
        Route::delete('{invitation}', [CoachInvitationController::class, 'revoke'])->middleware('throttle:20,1')->name('revoke');
    });
});

Route::prefix('api/v1')->middleware('api')->group(function () {
    Route::get('coaches/{coach}/public', [PublicCoachController::class, 'show'])
        ->middleware('throttle:120,1')
        ->name('coaches.public');
});
