<?php

namespace Modules\Progress\Providers;

use App\Providers\ModuleServiceProvider;

class ProgressServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Progress';

    protected string $nameLower = 'progress';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
