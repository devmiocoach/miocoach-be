<?php

use App\Modules\Users\Http\Controllers\CoachAgendaBookingController;
use App\Modules\Users\Http\Controllers\CoachAgendaController;
use App\Modules\Users\Http\Controllers\CoachBookingStatusController;
use App\Modules\Users\Http\Controllers\CoachClientNoteController;
use App\Modules\Users\Http\Controllers\CoachClientsController;
use App\Modules\Users\Http\Controllers\CoachDashboardController;
use App\Modules\Users\Http\Controllers\OnboardingController;
use App\Modules\Users\Http\Controllers\PublicCoachContactController;
use App\Modules\Users\Http\Controllers\PublicCoachPageController;
use App\Modules\Users\Http\Resources\PublicCoachResource;
use App\Modules\Users\Models\Coach;
use Illuminate\Support\Facades\Route;

// Coach routes — requires auth + verified + coach role
Route::middleware(['auth', 'verified', 'role_guard:coach'])->prefix('coach')->name('coach.')->group(function () {
    // Onboarding — accessible before onboarding is complete
    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/', [OnboardingController::class, 'index'])->name('index');
        Route::put('/step/1', [OnboardingController::class, 'updateStep1'])->name('step1');
        Route::put('/step/2', [OnboardingController::class, 'updateStep2'])->name('step2');
        Route::put('/step/3', [OnboardingController::class, 'updateStep3'])->name('step3');
        Route::put('/step/4', [OnboardingController::class, 'updateStep4'])->name('step4');
        Route::put('/step/5', [OnboardingController::class, 'updateStep5'])->name('step5');
        Route::put('/step/6', [OnboardingController::class, 'updateStep6'])->name('step6');
        Route::put('/step/7', [OnboardingController::class, 'updateStep7'])->name('step7');
        Route::put('/step/8', [OnboardingController::class, 'updateStep8'])->name('step8');
        Route::post('/publish', [OnboardingController::class, 'publish'])->name('publish');
        Route::post('/upload-photo', [OnboardingController::class, 'uploadPhoto'])->name('upload-photo');
        Route::post('/upload-gallery-photo', [OnboardingController::class, 'uploadGalleryPhoto'])->name('upload-gallery-photo');
        Route::post('/upload-video', [OnboardingController::class, 'uploadVideo'])->name('upload-video');
        Route::post('/upload-certification', [OnboardingController::class, 'uploadCertification'])->name('upload-certification');
        Route::post('/upload-insurance', [OnboardingController::class, 'uploadInsurance'])->name('upload-insurance');
        Route::delete('/gallery-photo', [OnboardingController::class, 'deleteGalleryPhoto'])->name('delete-gallery-photo');
    });

    // Dashboard — only accessible after onboarding is complete
    Route::middleware(['onboarding.complete'])->group(function () {
        Route::get('/dashboard', CoachDashboardController::class)->name('dashboard');
        Route::patch('/bookings/{booking}/complete', [CoachBookingStatusController::class, 'complete'])->name('bookings.complete');
        Route::post('/clients/{client}/notes', [CoachClientNoteController::class, 'store'])->name('clients.notes.store');

        // Client management
        Route::get('/clients', [CoachClientsController::class, 'index'])->name('clients.index');
        Route::get('/clients/{client}', [CoachClientsController::class, 'show'])->name('clients.show');

        // Agenda
        Route::get('/agenda', CoachAgendaController::class)->name('agenda');
        Route::post('/agenda/bookings', [CoachAgendaBookingController::class, 'store'])->name('agenda.bookings.store');
        Route::patch('/agenda/bookings/{booking}', [CoachAgendaBookingController::class, 'update'])->name('agenda.bookings.update');
        Route::patch('/agenda/bookings/{booking}/complete', [CoachAgendaBookingController::class, 'complete'])->name('agenda.bookings.complete');
        Route::patch('/agenda/bookings/{booking}/no-show', [CoachAgendaBookingController::class, 'noShow'])->name('agenda.bookings.no-show');
        Route::delete('/agenda/bookings/{booking}', [CoachAgendaBookingController::class, 'destroy'])->name('agenda.bookings.destroy');
    });
});

// Client/Athlete routes — requires auth + verified + client role
Route::middleware(['auth', 'verified', 'role_guard:client'])->prefix('my')->name('client.')->group(function () {
    Route::get('/dashboard', fn () => inertia('Users::Client/Dashboard'))->name('dashboard');
});

// Public routes — no auth required
// The `slug` wildcard must not swallow reserved coach sub-paths registered by
// other modules under the `coach/` prefix (e.g. WorkoutPlans' `coach/plans`,
// `coach/exercises`) — this module boots first, so its routes are matched
// before theirs.
Route::post('/coach/{coach:slug}/contact', PublicCoachContactController::class)
    ->middleware(['auth', 'verified', 'role_guard:client', 'throttle:10,60'])
    ->where('coach', '(?!plans$)(?!exercises$)[^/]+')
    ->name('coach.public.contact');
Route::get('/coach/{coach:slug}/login', function (Coach $coach) {
    session()->put('url.intended', route('coach.public.show', $coach->slug));

    return redirect()->route('login');
})->where('coach', '(?!plans$)(?!exercises$)[^/]+')->name('coach.public.login');
Route::get('/coach/{slug}', PublicCoachPageController::class)
    ->where('slug', '(?!plans$)(?!exercises$)[^/]+')
    ->name('coach.public.show');
Route::get('/coach/{slug}/book', function (string $slug) {
    $coach = Coach::where('slug', $slug)
        ->where('is_published', true)
        ->where('accepts_direct_booking', true)
        ->firstOrFail();

    $coach->load(['user', 'activePackages', 'availabilities']);
    $coach->loadCount('publishedReviews as reviews_count');
    $coach->loadAvg('publishedReviews as avg_rating', 'rating');

    return inertia('Users::Public/BookCoach', [
        'coach' => (new PublicCoachResource($coach))->resolve(),
    ]);
})->where('slug', '(?!plans$)(?!exercises$)[^/]+')->name('coach.public.book');
Route::get('/search', fn () => inertia('Users::Public/Search'))->name('coach.search');
