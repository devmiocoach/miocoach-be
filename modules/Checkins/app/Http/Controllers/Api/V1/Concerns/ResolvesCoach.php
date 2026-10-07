<?php

namespace Modules\Checkins\Http\Controllers\Api\V1\Concerns;

use App\Models\User;
use App\Modules\Users\Models\Coach;
use Modules\Checkins\Models\CheckinForm;
use Symfony\Component\HttpFoundation\Response;

trait ResolvesCoach
{
    protected function resolveCoach(User $user): Coach
    {
        abort_unless($user->coach !== null, Response::HTTP_FORBIDDEN, 'Non autorizzato.');

        return $user->coach;
    }

    protected function assertOwnsForm(Coach $coach, CheckinForm $form): void
    {
        abort_unless($form->coach_id === $coach->id, Response::HTTP_FORBIDDEN, 'Non autorizzato.');
    }

    /**
     * @param  array<int, int>  $clientIds
     */
    protected function assertCoachOwnsClients(Coach $coach, array $clientIds): void
    {
        $ownedCount = $coach->activeAthletes()->whereIn('athlete_id', $clientIds)->count();

        abort_unless($ownedCount === count(array_unique($clientIds)), Response::HTTP_FORBIDDEN, 'Non autorizzato.');
    }
}
