<?php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;

class DetachAthleteFromCoachAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Coach $coach, Client $athlete): void
    {
        $coach->athletes()->updateExistingPivot($athlete->id, ['ended_at' => now()]);
        $coach->athletes()->detach($athlete->id);

        $this->audit->log('ATHLETE_DETACHED', $coach->user_id, [
            'athlete_id' => $athlete->id,
        ]);
    }
}
