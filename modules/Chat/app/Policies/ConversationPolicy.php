<?php

namespace Modules\Chat\Policies;

use App\Models\User;
use Modules\Chat\Models\Conversation;

class ConversationPolicy
{
    public function participate(User $user, Conversation $conversation): bool
    {
        $conversation->loadMissing(['coach:id,user_id', 'client:id,user_id']);

        return $user->id === $conversation->coach?->user_id
            || $user->id === $conversation->client?->user_id;
    }
}
