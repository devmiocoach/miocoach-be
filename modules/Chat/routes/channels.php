<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Policies\ConversationPolicy;

Broadcast::channel(
    'chat.conversation.{conversation}',
    function (User $user, Conversation $conversation) {
        return (new ConversationPolicy)->participate($user, $conversation);
    }
);
