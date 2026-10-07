<?php

use Illuminate\Support\Facades\Route;
use Modules\CoachCommerce\Http\Controllers\CoachCommerceController;

Route::resource('coach-commerce', CoachCommerceController::class);
