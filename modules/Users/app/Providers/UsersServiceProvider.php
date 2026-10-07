<?php

namespace App\Modules\Users\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Console\Commands\BackfillCoachActivitiesCommand;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Observers\BookingActivityObserver;
use App\Modules\Users\Services\EncryptionService;
use App\Modules\Users\Services\GeocodingService;
use App\Modules\Users\Services\ObjectStorageService;
use App\Modules\Users\Services\SlugService;
use App\Providers\ModuleServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Modules\Bookings\Models\Booking;
use Modules\Users\Providers\RouteServiceProvider;

class UsersServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Users';

    protected string $nameLower = 'users';

    protected array $commands = [
        BackfillCoachActivitiesCommand::class,
    ];

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(EncryptionService::class);
        $this->app->singleton(GeocodingService::class);
        $this->app->singleton(SlugService::class);
        $this->app->singleton(ObjectStorageService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Booking::observe(BookingActivityObserver::class);

        Event::listen(Registered::class, function (Registered $event) {
            /** @var User $user */
            $user = $event->user;

            if (! $user->hasRole(Role::CLIENT->value)) {
                return;
            }

            if ($user->client !== null) {
                return;
            }

            $client = new Client;
            $client->user_id = $user->id;
            $client->joined_at = now();
            $client->save();
        });
    }
}
