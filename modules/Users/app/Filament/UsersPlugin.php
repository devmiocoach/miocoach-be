<?php

namespace Modules\Users\Filament;

use App\Filament\ModulePlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class UsersPlugin implements Plugin
{
    use ModulePlugin;

    public function getModuleName(): string
    {
        return 'Users';
    }

    public function getId(): string
    {
        return 'users';
    }

    public function boot(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make()
                ->label('Gestione Coach')
                ->icon(Heroicon::OutlinedAcademicCap)
                ->collapsible(),
        ]);
    }
}
