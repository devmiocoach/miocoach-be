<?php

use App\Modules\Users\Http\Controllers\Api\V1\AvailabilityController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientAnamnesisController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientNoteController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientFileController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientTagController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachClientController;
use App\Modules\Users\Http\Controllers\Api\V1\CertificationController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachInvitationController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachListingController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachContactRequestController;
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
        Route::post('clients', [CoachController::class, 'store'])->middleware('throttle:30,1')->name('clients.store');
        Route::get('clients/{client}', [CoachClientController::class, 'show'])->middleware('throttle:60,1')->name('clients.show');
        Route::put('clients/{client}', [CoachClientController::class, 'update'])->middleware('throttle:30,1')->name('clients.update');
        Route::delete('clients/{client}', [CoachClientController::class, 'destroy'])->middleware('throttle:20,1')->name('clients.destroy');
        Route::post('clients/{client}/tags', [ClientTagController::class, 'store'])->middleware('throttle:60,1')->name('clients.tags.store');
        Route::delete('clients/{client}/tags/{tag}', [ClientTagController::class, 'destroy'])->middleware('throttle:60,1')->name('clients.tags.destroy');
        Route::post('clients/{client}/notes', [ClientNoteController::class, 'store'])->middleware('throttle:60,1')->name('clients.notes.store');
        Route::put('clients/{client}/anamnesis', [ClientAnamnesisController::class, 'update'])->middleware('throttle:20,1')->name('clients.anamnesis.update');
        Route::post('clients/{client}/files', [ClientFileController::class, 'store'])->middleware('throttle:20,1')->name('clients.files.store');
        Route::get('clients/{client}/files/{clientFile}/download', [ClientFileController::class, 'download'])->middleware('throttle:60,1')->name('clients.files.download');
        Route::put('publish', [CoachController::class, 'publish'])->middleware('throttle:10,1')->name('publish');
        Route::put('availability', [AvailabilityController::class, 'replace'])->middleware('throttle:20,1')->name('availability');
        Route::post('certifications', [CertificationController::class, 'store'])->middleware('throttle:20,1')->name('certifications.store');
        Route::delete('certifications/{certification}', [CertificationController::class, 'destroy'])->middleware('throttle:20,1')->name('certifications.destroy');
    });

    // Profilo client
    Route::prefix('clients/me')->name('clients.me.')->middleware('role:client')->group(function () {
        Route::patch('', [ClientController::class, 'update'])->middleware('throttle:30,1')->name('update');
    });

    // Contact requests (client -> coach)
    Route::prefix('coaches')->middleware('role:client')->group(function () {
        Route::post('{coach}/contact-request', [CoachContactRequestController::class, 'store'])
            ->middleware('throttle:10,60')
            ->name('coaches.contact-request.store');
    });

    // Inviti coach
    Route::prefix('coaches/invitations')->name('coaches.invitations.')->middleware('role:coach')->group(function () {
        Route::get('', [CoachInvitationController::class, 'index'])->middleware('throttle:60,1')->name('index');
        Route::post('', [CoachInvitationController::class, 'send'])->middleware('throttle:10,60')->name('send');
        Route::delete('{invitation}', [CoachInvitationController::class, 'revoke'])->middleware('throttle:20,1')->name('revoke');
    });
});

Route::prefix('api/v1')->middleware('api')->group(function () {
    Route::get('coaches', [CoachListingController::class, 'index'])
        ->middleware('throttle:120,1')
        ->name('coaches.index');
    Route::get('coaches/{coach}/public', [PublicCoachController::class, 'show'])
        ->middleware('throttle:120,1')
        ->name('coaches.public');
});
