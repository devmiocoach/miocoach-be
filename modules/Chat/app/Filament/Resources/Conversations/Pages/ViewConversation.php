<?php

namespace Modules\Chat\Filament\Resources\Conversations\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Chat\Filament\Resources\Conversations\ConversationResource;

class ViewConversation extends ViewRecord
{
    protected static string $resource = ConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
