<?php

use Illuminate\Support\Facades\Route;
use Modules\CoachCommerce\Http\Controllers\Api\V1\SubscriptionPlanController;

Route::prefix('api/v1')->middleware(['jwt.auth', 'verified.email', 'role:coach'])->group(function () {

    Route::prefix('coaches/me')->group(function () {
        Route::get('subscription-plans', [SubscriptionPlanController::class, 'index'])->middleware('throttle:60,1')->name('coaches.me.subscription-plans.index');
        Route::post('subscription-plans', [SubscriptionPlanController::class, 'store'])->middleware('throttle:30,1')->name('coaches.me.subscription-plans.store');
        Route::put('subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'update'])->middleware('throttle:30,1')->name('coaches.me.subscription-plans.update');
        Route::delete('subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'destroy'])->middleware('throttle:20,1')->name('coaches.me.subscription-plans.destroy');
    });

});
