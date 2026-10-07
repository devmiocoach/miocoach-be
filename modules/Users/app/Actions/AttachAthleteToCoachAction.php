<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Enums\CoachActivityType;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachActivity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Services\AuditLogService;

class AttachAthleteToCoachAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Coach $coach, Client $athlete, string $status = 'active'): void
    {
        try {
            $coach->athletes()->attach($athlete->id, [
                'status' => $status,
                'started_at' => $status === 'active' ? now() : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'athlete_id' => ['Questo atleta è già associato al coach.'],
            ]);
        }

        $this->audit->log('ATHLETE_ATTACHED', $coach->user_id, [
            'athlete_id' => $athlete->id,
            'status' => $status,
        ]);

        if ($status === 'active') {
            CoachActivity::create([
                'coach_id' => $coach->id,
                'event_type' => CoachActivityType::NewClient,
                'subject_type' => Client::class,
                'subject_id' => $athlete->id,
                'metadata' => [
                    'client_name' => $athlete->user?->name,
                ],
                'created_at' => now(),
            ]);
        }
    }
}
