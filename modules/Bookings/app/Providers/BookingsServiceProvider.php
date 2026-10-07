<?php

namespace Modules\Bookings\Providers;

use App\Modules\Users\Models\Client;
use App\Providers\ModuleServiceProvider;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingEmailService;
use Modules\Bookings\Services\BookingService;
use Modules\Bookings\Services\Contracts\BookingEmailServiceContract;
use Modules\Bookings\Services\Contracts\CalendarServiceContract;
use Modules\Bookings\Services\Contracts\PushServiceContract;
use Modules\Bookings\Services\Contracts\SmsServiceContract;
use Modules\Bookings\Services\GoogleCalendarService;
use Modules\Bookings\Services\StubPushService;
use Modules\Bookings\Services\StubSmsService;

class BookingsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Bookings';

    protected string $nameLower = 'bookings';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(CalendarServiceContract::class, GoogleCalendarService::class);
        $this->app->bind(SmsServiceContract::class, StubSmsService::class);
        $this->app->bind(BookingEmailServiceContract::class, BookingEmailService::class);
        $this->app->bind(PushServiceContract::class, StubPushService::class);

        $this->app->bind(BookingService::class, fn ($app) => new BookingService(
            $app->make(CalendarServiceContract::class),
            $app->make(SmsServiceContract::class),
            $app->make(BookingEmailServiceContract::class),
            $app->make(PushServiceContract::class),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadViewsFrom(module_path($this->name, 'resources/views'), $this->nameLower);
        $this->registerClientRelations();
    }

    private function registerClientRelations(): void
    {
        Client::resolveRelationUsing('upcomingBooking', fn (Client $client) => $client
            ->hasOne(Booking::class, 'client_id')
            ->where('status', BookingStatus::Confirmed->value)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
        );

        Client::resolveRelationUsing('recentSessions', fn (Client $client) => $client
            ->hasMany(Booking::class, 'client_id')
            ->where('status', BookingStatus::Completed->value)
            ->orderByDesc('starts_at')
            ->limit(10)
        );
    }
}
