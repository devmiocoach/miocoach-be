<?php

namespace Modules\WorkoutPlans\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::middleware('web')
            ->group(module_path('WorkoutPlans', '/routes/web.php'));
        Route::middleware('api')
            ->group(module_path('WorkoutPlans', '/routes/api.php'));
    }
}
