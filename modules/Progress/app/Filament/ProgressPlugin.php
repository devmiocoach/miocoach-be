<?php

namespace Modules\Progress\Filament;

use App\Filament\ModulePlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class ProgressPlugin implements Plugin
{
    use ModulePlugin;

    public function getModuleName(): string
    {
        return 'Progress';
    }

    public function getId(): string
    {
        return 'progress';
    }

    public function boot(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make()
                ->label(__('Progress'))
                ->icon(Heroicon::OutlinedChartBar)
                ->collapsible(),
        ]);
    }
}
