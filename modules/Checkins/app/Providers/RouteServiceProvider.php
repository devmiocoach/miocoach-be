<?php

namespace Modules\Checkins\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::middleware('web')
            ->group(module_path('Checkins', '/routes/web.php'));
        Route::middleware('api')
            ->group(module_path('Checkins', '/routes/api.php'));
    }
}
