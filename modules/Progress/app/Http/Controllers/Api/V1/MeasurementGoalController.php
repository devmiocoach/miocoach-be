<?php

namespace Modules\Progress\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Progress\Enums\MetricType;
use Modules\Progress\Http\Controllers\Api\V1\Concerns\VerifiesCoachOwnership;
use Modules\Progress\Http\Requests\UpsertGoalRequest;
use Modules\Progress\Models\MeasurementGoal;

class MeasurementGoalController extends Controller
{
    use VerifiesCoachOwnership;

    public function index(Request $request, Client $client): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || ! $this->ownsClient($coach, $client)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $goals = MeasurementGoal::where('client_id', $client->id)
            ->where('coach_id', $coach->id)
            ->get();

        return response()->json(['data' => $goals]);
    }

    public function upsert(UpsertGoalRequest $request, Client $client, string $metricType): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || ! $this->ownsClient($coach, $client)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $type = MetricType::tryFrom($metricType);
        if (! $type) {
            return response()->json(['message' => 'Tipo metrica non valido.'], 422);
        }

        $goal = MeasurementGoal::updateOrCreate(
            [
                'client_id' => $client->id,
                'coach_id' => $coach->id,
                'metric_type' => $type->value,
            ],
            [
                'target_value' => $request->validated('target_value'),
                'target_date' => $request->validated('target_date'),
            ]
        );

        return response()->json(['data' => $goal]);
    }

    public function destroy(Request $request, Client $client, string $metricType): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || ! $this->ownsClient($coach, $client)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $type = MetricType::tryFrom($metricType);
        if (! $type) {
            return response()->json(['message' => 'Tipo metrica non valido.'], 422);
        }

        MeasurementGoal::where('client_id', $client->id)
            ->where('coach_id', $coach->id)
            ->where('metric_type', $type->value)
            ->delete();

        return response()->json(null, 204);
    }
}
