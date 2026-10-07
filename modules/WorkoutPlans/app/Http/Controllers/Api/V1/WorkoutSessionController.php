<?php

namespace Modules\WorkoutPlans\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\WorkoutPlans\Http\Requests\CompleteWorkoutSessionRequest;
use Modules\WorkoutPlans\Http\Requests\StartWorkoutSessionRequest;
use Modules\WorkoutPlans\Http\Resources\WorkoutSessionResource;
use Modules\WorkoutPlans\Jobs\CalculateAiProgressionJob;
use Modules\WorkoutPlans\Models\PersonalRecord;
use Modules\WorkoutPlans\Models\SessionSet;
use Modules\WorkoutPlans\Models\WorkoutDay;
use Modules\WorkoutPlans\Models\WorkoutSession;

class WorkoutSessionController extends Controller
{
    public function start(StartWorkoutSessionRequest $request): JsonResponse
    {
        $client = $request->user()->client;

        if (! $client) {
            return response()->json(['message' => 'Client profile not found.'], 403);
        }

        $day = WorkoutDay::with('workoutExercises.exercise')->findOrFail($request->validated('dayId'));

        $session = WorkoutSession::create([
            'client_id' => $client->id,
            'workout_plan_id' => $request->validated('planId'),
            'workout_day_id' => $day->id,
            'started_at' => now(),
        ]);

        $session->setRelation('workoutDay', $day);

        return response()->json(['data' => new WorkoutSessionResource($session)], 201);
    }

    public function complete(CompleteWorkoutSessionRequest $request, WorkoutSession $workoutSession): JsonResponse
    {
        $client = $request->user()->client;

        if (! $client || $workoutSession->client_id !== $client->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $sets = $request->validated('sets');

        DB::transaction(function () use ($request, $workoutSession, $sets, $client) {
            $insertData = array_map(fn ($set) => [
                'workout_session_id' => $workoutSession->id,
                'exercise_id' => $set['exerciseId'],
                'set_number' => $set['setNumber'],
                'reps_done' => $set['repsDone'] ?? null,
                'weight_kg' => $set['weightKg'] ?? null,
                'rir_actual' => $set['rirActual'] ?? null,
                'is_completed' => $set['isCompleted'] ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ], $sets);

            SessionSet::insert($insertData);

            $totalVolume = collect($sets)
                ->filter(fn ($s) => ($s['isCompleted'] ?? false) && isset($s['weightKg'], $s['repsDone']))
                ->sum(fn ($s) => $s['weightKg'] * $s['repsDone']);

            $workoutSession->update([
                'completed_at' => now(),
                'total_volume_kg' => $totalVolume > 0 ? $totalVolume : null,
                'duration_minutes' => $request->validated('durationMinutes'),
                'notes' => $request->validated('notes'),
            ]);

            $exerciseMaxWeights = collect($sets)
                ->filter(fn ($s) => ($s['isCompleted'] ?? false) && isset($s['weightKg']))
                ->groupBy('exerciseId')
                ->map(fn ($group) => $group->max('weightKg'));

            foreach ($exerciseMaxWeights as $exerciseId => $maxWeight) {
                $existing = PersonalRecord::where('client_id', $client->id)
                    ->where('exercise_id', $exerciseId)
                    ->first();

                if (! $existing || $maxWeight > $existing->weight_kg) {
                    PersonalRecord::updateOrCreate(
                        ['client_id' => $client->id, 'exercise_id' => $exerciseId],
                        ['weight_kg' => $maxWeight, 'recorded_at' => now()]
                    );
                }

                CalculateAiProgressionJob::dispatch($workoutSession->id, $exerciseId);
            }
        });

        return response()->json(['data' => new WorkoutSessionResource($workoutSession->fresh())]);
    }
}
