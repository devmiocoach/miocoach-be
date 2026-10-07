<?php

namespace Modules\Chat\Filament;

use App\Filament\ModulePlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class ChatPlugin implements Plugin
{
    use ModulePlugin;

    public function getModuleName(): string
    {
        return 'Chat';
    }

    public function getId(): string
    {
        return 'chat';
    }

    public function boot(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make()
                ->label(__('Chat'))
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->collapsible(),
        ]);
    }
}
