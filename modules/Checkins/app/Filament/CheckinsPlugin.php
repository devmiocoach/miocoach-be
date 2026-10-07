<?php

namespace Modules\Checkins\Filament;

use App\Filament\ModulePlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class CheckinsPlugin implements Plugin
{
    use ModulePlugin;

    public function getModuleName(): string
    {
        return 'Checkins';
    }

    public function getId(): string
    {
        return 'checkins';
    }

    public function boot(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make()
                ->label(__('Check-ins'))
                ->icon(Heroicon::OutlinedChartBar)
                ->collapsible(),
        ]);
    }
}
