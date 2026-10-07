<?php

use App\Modules\Users\Http\Controllers\Api\V1\AvailabilityController;
use App\Modules\Users\Http\Controllers\Api\V1\CertificationController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientAnamnesisController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientFileController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientNoteController;
use App\Modules\Users\Http\Controllers\Api\V1\ClientTagController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachClientController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachContactRequestController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachDashboardController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachInvitationController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachListingController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachOnboardingController;
use App\Modules\Users\Http\Controllers\Api\V1\CoachPackageController;
use App\Modules\Users\Http\Controllers\Api\V1\PublicAvailabilityController;
use App\Modules\Users\Http\Controllers\Api\V1\PublicCoachController;
use App\Modules\Users\Http\Controllers\Api\V1\ReviewController;
use App\Modules\Users\Http\Controllers\Api\V1\SpecializationController;
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
        Route::get('dashboard', CoachDashboardController::class)->middleware('throttle:60,1')->name('dashboard');
        Route::patch('', [CoachController::class, 'update'])->middleware('throttle:30,1')->name('update');
        Route::get('clients', [CoachController::class, 'clients'])->middleware('throttle:60,1')->name('clients');
        Route::post('clients', [CoachController::class, 'store'])->middleware('throttle:30,1')->name('clients.store');
        Route::get('clients/{client}', [CoachClientController::class, 'show'])->middleware('throttle:60,1')->name('clients.show');
        Route::put('clients/{client}', [CoachClientController::class, 'update'])->middleware('throttle:30,1')->name('clients.update');
        Route::delete('clients/{client}', [CoachClientController::class, 'destroy'])->middleware('throttle:20,1')->name('clients.destroy');
        Route::post('clients/{client}/tags', [ClientTagController::class, 'store'])->middleware('throttle:60,1')->name('clients.tags.store');
        Route::delete('clients/{client}/tags/{tag}', [ClientTagController::class, 'destroy'])->middleware('throttle:60,1')->name('clients.tags.destroy');
        Route::get('clients/{client}/notes', [ClientNoteController::class, 'index'])->middleware('throttle:60,1')->name('clients.notes.index');
        Route::post('clients/{client}/notes', [ClientNoteController::class, 'store'])->middleware('throttle:60,1')->name('clients.notes.store');
        Route::delete('clients/{client}/notes/{clientNote}', [ClientNoteController::class, 'destroy'])->middleware('throttle:60,1')->name('clients.notes.destroy');
        Route::get('clients/{client}/anamnesis', [ClientAnamnesisController::class, 'show'])->middleware('throttle:60,1')->name('clients.anamnesis.show');
        Route::put('clients/{client}/anamnesis', [ClientAnamnesisController::class, 'update'])->middleware('throttle:20,1')->name('clients.anamnesis.update');
        Route::get('clients/{client}/files', [ClientFileController::class, 'index'])->middleware('throttle:60,1')->name('clients.files.index');
        Route::post('clients/{client}/files', [ClientFileController::class, 'store'])->middleware('throttle:20,1')->name('clients.files.store');
        Route::get('clients/{client}/files/{clientFile}/download', [ClientFileController::class, 'download'])->middleware('throttle:60,1')->name('clients.files.download');
        Route::delete('clients/{client}/files/{clientFile}', [ClientFileController::class, 'destroy'])->middleware('throttle:20,1')->name('clients.files.destroy');
        Route::get('contact-requests', [CoachContactRequestController::class, 'index'])->middleware('throttle:60,1')->name('contact-requests.index');
        Route::put('contact-requests/{contactRequest}', [CoachContactRequestController::class, 'update'])->middleware('throttle:30,1')->name('contact-requests.update');
        Route::put('publish', [CoachController::class, 'publish'])->middleware('throttle:10,1')->name('publish');
        Route::put('availability', [AvailabilityController::class, 'replace'])->middleware('throttle:20,1')->name('availability');
        Route::post('certifications', [CertificationController::class, 'store'])->middleware('throttle:20,1')->name('certifications.store');
        Route::delete('certifications/{certification}', [CertificationController::class, 'destroy'])->middleware('throttle:20,1')->name('certifications.destroy');

        Route::get('packages', [CoachPackageController::class, 'index'])->middleware('throttle:60,1')->name('packages.index');
        Route::post('packages', [CoachPackageController::class, 'store'])->middleware('throttle:30,1')->name('packages.store');
        Route::put('packages/{package}', [CoachPackageController::class, 'update'])->middleware('throttle:30,1')->name('packages.update');
        Route::delete('packages/{package}', [CoachPackageController::class, 'destroy'])->middleware('throttle:20,1')->name('packages.destroy');

        Route::prefix('onboarding')->name('onboarding.')->group(function () {
            Route::get('', [CoachOnboardingController::class, 'show'])->middleware('throttle:60,1')->name('show');
            Route::put('step/1', [CoachOnboardingController::class, 'updateStep1'])->middleware('throttle:30,1')->name('step1');
            Route::put('step/2', [CoachOnboardingController::class, 'updateStep2'])->middleware('throttle:30,1')->name('step2');
            Route::put('step/3', [CoachOnboardingController::class, 'updateStep3'])->middleware('throttle:30,1')->name('step3');
            Route::put('step/4', [CoachOnboardingController::class, 'updateStep4'])->middleware('throttle:30,1')->name('step4');
            Route::put('step/5', [CoachOnboardingController::class, 'updateStep5'])->middleware('throttle:30,1')->name('step5');
            Route::put('step/6', [CoachOnboardingController::class, 'updateStep6'])->middleware('throttle:30,1')->name('step6');
            Route::put('step/7', [CoachOnboardingController::class, 'updateStep7'])->middleware('throttle:30,1')->name('step7');
            Route::put('step/8', [CoachOnboardingController::class, 'updateStep8'])->middleware('throttle:30,1')->name('step8');
            Route::post('publish', [CoachOnboardingController::class, 'publish'])->middleware('throttle:10,1')->name('publish');
            Route::post('photo', [CoachOnboardingController::class, 'uploadPhoto'])->middleware('throttle:20,1')->name('photo');
            Route::post('gallery', [CoachOnboardingController::class, 'uploadGalleryPhoto'])->middleware('throttle:20,1')->name('gallery.store');
            Route::delete('gallery', [CoachOnboardingController::class, 'deleteGalleryPhoto'])->middleware('throttle:20,1')->name('gallery.destroy');
            Route::post('video', [CoachOnboardingController::class, 'uploadVideo'])->middleware('throttle:10,1')->name('video');
            Route::post('certification', [CoachOnboardingController::class, 'uploadCertification'])->middleware('throttle:20,1')->name('certification');
            Route::post('insurance', [CoachOnboardingController::class, 'uploadInsurance'])->middleware('throttle:20,1')->name('insurance');
        });
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
    Route::get('coaches/{coach}/reviews', [ReviewController::class, 'index'])
        ->middleware('throttle:120,1')
        ->name('coaches.reviews.index');
    Route::get('coaches/{coach}/available-dates', [PublicAvailabilityController::class, 'availableDates'])
        ->middleware('throttle:120,1')
        ->name('coaches.available-dates');
    Route::get('coaches/{coach}/slots', [PublicAvailabilityController::class, 'slots'])
        ->middleware('throttle:120,1')
        ->name('coaches.slots');
    Route::get('specializations', [SpecializationController::class, 'index'])
        ->middleware('throttle:120,1')
        ->name('specializations.index');
});

Route::prefix('api/v1')->middleware(['jwt.auth', 'verified.email'])->group(function () {
    Route::post('coaches/{coach}/reviews', [ReviewController::class, 'store'])
        ->middleware('throttle:10,60')
        ->name('coaches.reviews.store');
});
