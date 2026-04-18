<?php

namespace App\Modules\Users\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        // Il gruppo 'api' applica SubstituteBindings (necessario per route model binding)
        Route::middleware('api')->group(__DIR__ . '/../../routes/api.php');
    }
}
