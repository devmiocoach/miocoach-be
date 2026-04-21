<?php

namespace App\Modules\Users\Providers;

use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Services\EncryptionService;
use App\Modules\Users\Services\GeocodingService;
use App\Modules\Users\Services\ObjectStorageService;
use App\Modules\Users\Services\SlugService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EncryptionService::class);
        $this->app->singleton(GeocodingService::class);
        $this->app->singleton(SlugService::class);
        $this->app->singleton(ObjectStorageService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        Route::middleware('api')->group(__DIR__ . '/../../routes/api.php');

        Event::listen(Registered::class, function (Registered $event) {
            /** @var User $user */
            $user = $event->user;

            if (! $user->hasRole('client')) {
                return;
            }

            if ($user->client !== null) {
                return;
            }

            $client            = new Client();
            $client->user_id   = $user->id;
            $client->joined_at = now();
            $client->save();
        });
    }
}
