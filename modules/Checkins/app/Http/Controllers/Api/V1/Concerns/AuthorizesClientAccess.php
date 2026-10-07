<?php

namespace Modules\Checkins\Http\Controllers\Api\V1\Concerns;

use App\Models\User;
use App\Modules\Users\Models\Client;
use Symfony\Component\HttpFoundation\Response;

trait AuthorizesClientAccess
{
    protected function authorizeClientAccess(User $user, Client $client): void
    {
        $isSelf = $user->client?->id === $client->id;
        $isLinkedCoach = $user->coach
            && $user->coach->activeAthletes()->where('athlete_id', $client->id)->exists();

        abort_unless($isSelf || $isLinkedCoach, Response::HTTP_FORBIDDEN, 'Non autorizzato.');
    }
}
