<?php

namespace Modules\Progress\Http\Controllers\Api\V1\Concerns;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;

trait VerifiesCoachOwnership
{
    /**
     * Only an active coach-client link grants access. A pending or suspended
     * link must not authorize reading or writing the client's progress data.
     */
    private function ownsClient(Coach $coach, Client $client): bool
    {
        return $coach->activeAthletes()->where('athlete_id', $client->id)->exists();
    }
}
