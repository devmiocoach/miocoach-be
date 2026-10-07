<?php

use Illuminate\Support\Facades\Route;
use Modules\Chat\Http\Controllers\Coach\ChatController;

Route::middleware(['auth', 'verified', 'role_guard:coach', 'onboarding.complete'])
    ->prefix('coach')->name('coach.')->group(function () {
        Route::get('chat', [ChatController::class, 'index'])->name('chat');
        Route::post('chat/conversations', [ChatController::class, 'storeConversation'])->name('chat.conversations.store');
        Route::get('chat/conversations/{conversation}/messages', [ChatController::class, 'messages'])->name('chat.messages.index');
        Route::post('chat/conversations/{conversation}/messages', [ChatController::class, 'store'])->name('chat.messages.store');
        Route::post('chat/conversations/{conversation}/read', [ChatController::class, 'read'])->name('chat.messages.read');
        Route::post('chat/conversations/{conversation}/workout-plan', [ChatController::class, 'storeWorkoutPlan'])->name('chat.workout-plan.store');
        Route::post('chat/conversations/{conversation}/attachments', [ChatController::class, 'storeAttachment'])->name('chat.attachments.store');
        Route::get('chat/conversations/{conversation}/attachments/{attachment}', [ChatController::class, 'downloadAttachment'])->name('chat.attachments.download');
    });
