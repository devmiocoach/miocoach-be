<?php

namespace Modules\Chat\Providers;

use App\Providers\ModuleServiceProvider;

class ChatServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Chat';

    protected string $nameLower = 'chat';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        $this->loadRoutesFrom(module_path($this->name, 'routes/channels.php'));
    }
}
