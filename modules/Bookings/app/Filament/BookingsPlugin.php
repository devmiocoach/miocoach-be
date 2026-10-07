<?php

namespace Modules\Bookings\Filament;

use App\Filament\ModulePlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class BookingsPlugin implements Plugin
{
    use ModulePlugin;

    public function getModuleName(): string
    {
        return 'Bookings';
    }

    public function getId(): string
    {
        return 'bookings';
    }

    public function boot(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make()
                ->label(__('Bookings'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->collapsible(),
        ]);
    }
}
