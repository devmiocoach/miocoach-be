<?php

namespace Modules\WorkoutPlans\Filament;

use App\Filament\ModulePlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class WorkoutPlansPlugin implements Plugin
{
    use ModulePlugin;

    public function getModuleName(): string
    {
        return 'WorkoutPlans';
    }

    public function getId(): string
    {
        return 'workoutplans';
    }

    public function boot(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make()
                ->label(__('Workout Plans'))
                ->icon(Heroicon::OutlinedClipboardDocumentList)
                ->collapsible(),
        ]);
    }
}
