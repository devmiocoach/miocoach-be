<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Modules\Chat\Http\Controllers\Api\V1\ConversationController;
use Modules\Chat\Http\Controllers\Api\V1\MessageController;

Route::prefix('api/v1')->middleware(['jwt.auth', 'verified.email'])->group(function () {
    Route::post('broadcasting/auth', fn () => Broadcast::auth(request()))
        ->name('chat.broadcasting.auth');

    Route::get('conversations', [ConversationController::class, 'index'])
        ->middleware('throttle:60,1')->name('chat.conversations.index');
    Route::get('conversations/{conversation}/messages', [MessageController::class, 'index'])
        ->middleware('throttle:120,1')->name('chat.messages.index');
    Route::post('conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:60,1')->name('chat.messages.store');
    Route::post('conversations/{conversation}/read', [MessageController::class, 'read'])
        ->middleware('throttle:120,1')->name('chat.messages.read');
    Route::post('conversations/{conversation}/workout-plan', [MessageController::class, 'storeWorkoutPlan'])
        ->middleware('throttle:30,1')->name('chat.workout-plan.store');
    Route::post('conversations/{conversation}/attachments', [MessageController::class, 'storeAttachment'])
        ->middleware('throttle:30,1')->name('chat.attachments.store');
    Route::get('conversations/{conversation}/attachments/{attachment}', [MessageController::class, 'downloadAttachment'])
        ->middleware('throttle:120,1')->name('chat.attachments.download');
});
