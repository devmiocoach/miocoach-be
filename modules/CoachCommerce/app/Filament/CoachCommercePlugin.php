<?php

namespace Modules\CoachCommerce\Filament;

use App\Filament\ModulePlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class CoachCommercePlugin implements Plugin
{
    use ModulePlugin;

    public function getModuleName(): string
    {
        return 'CoachCommerce';
    }

    public function getId(): string
    {
        return 'coachcommerce';
    }

    public function boot(Panel $panel): void
    {
        //
    }
}
