<?php

namespace Modules\Checkins\Providers;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Providers\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Modules\Checkins\Console\DispatchCheckinsCommand;
use Modules\Checkins\Models\CheckinForm;
use Modules\Checkins\Models\CheckinResponse;
use Modules\Checkins\Services\Contracts\PushServiceContract;
use Modules\Checkins\Services\StubPushService;

class CheckinsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Checkins';

    protected string $nameLower = 'checkins';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    protected array $commands = [
        DispatchCheckinsCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(
            PushServiceContract::class,
            StubPushService::class,
        );

        Client::resolveRelationUsing('checkinResponses', fn (Client $client) => $client->hasMany(CheckinResponse::class));
        Client::resolveRelationUsing('assignedCheckinForms', fn (Client $client) => $client->belongsToMany(CheckinForm::class, 'checkin_form_client'));
        Coach::resolveRelationUsing('checkinForms', fn (Coach $coach) => $coach->hasMany(CheckinForm::class));
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->command('checkins:dispatch')->hourly();
    }
}
