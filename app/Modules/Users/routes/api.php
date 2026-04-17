<?php

use App\Modules\Users\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1')->middleware('auth:sanctum')->group(function () {
    Route::get('users/me', [UserController::class, 'me'])->name('users.me');
    Route::patch('users/me', [UserController::class, 'update'])->name('users.update');
    Route::delete('users/me', [UserController::class, 'destroy'])->name('users.destroy');
});
