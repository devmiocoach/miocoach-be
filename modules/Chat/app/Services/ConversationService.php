<?php

namespace Modules\Chat\Services;

use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Support\Collection;
use Modules\Chat\Models\Conversation;

class ConversationService
{
    public function findOrCreate(Coach $coach, Client $client): Conversation
    {
        return Conversation::firstOrCreate([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
        ]);
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function forUser(User $user): Collection
    {
        return Conversation::query()
            ->where(fn ($q) => $q
                ->whereHas('coach', fn ($c) => $c->where('user_id', $user->id))
                ->orWhereHas('client', fn ($c) => $c->where('user_id', $user->id)))
            ->with(['coach.user:id,name', 'client.user:id,name', 'latestMessage'])
            ->orderByRaw('last_message_at IS NULL, last_message_at DESC')
            ->get();
    }
}
