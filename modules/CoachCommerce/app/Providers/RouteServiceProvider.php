<?php

namespace Modules\CoachCommerce\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::middleware('web')
            ->group(module_path('CoachCommerce', '/routes/web.php'));
        Route::middleware('api')
            ->group(module_path('CoachCommerce', '/routes/api.php'));
    }
}
