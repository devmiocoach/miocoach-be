<?php

use ___MODULE_NAMESPACE___\___Module___\Http\Controllers\___Module___Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['jwt.auth'])->prefix('api/v1/{module-}')->group(function () {
    Route::apiResource('{module-}', ___Module___Controller::class, ['as' => 'api']);
});
