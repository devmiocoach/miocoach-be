<?php

namespace Modules\WorkoutPlans\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateAiProgressionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $workoutSessionId,
        public readonly int $exerciseId,
    ) {}

    public function handle(): void
    {
        Log::info('TODO: requires AI progression service', [
            'workout_session_id' => $this->workoutSessionId,
            'exercise_id' => $this->exerciseId,
        ]);
    }
}
