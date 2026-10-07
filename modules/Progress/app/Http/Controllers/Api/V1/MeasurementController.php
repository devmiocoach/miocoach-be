<?php

namespace Modules\Progress\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Progress\Enums\MetricType;
use Modules\Progress\Http\Controllers\Api\V1\Concerns\VerifiesCoachOwnership;
use Modules\Progress\Http\Requests\StoreMeasurementRequest;
use Modules\Progress\Http\Requests\UpdateMeasurementRequest;
use Modules\Progress\Models\BodyMeasurement;
use Modules\Progress\Models\MeasurementGoal;

class MeasurementController extends Controller
{
    use VerifiesCoachOwnership;

    public function index(Request $request, Client $client): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || ! $this->ownsClient($coach, $client)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $query = BodyMeasurement::forClient($client->id)->forCoach($coach->id);

        if ($request->filled('metric_type')) {
            $metric = MetricType::tryFrom($request->input('metric_type'));
            if ($metric) {
                $query->ofType($metric);
            }
        }

        if ($request->filled('from')) {
            $query->where('measured_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('measured_at', '<=', $request->input('to'));
        }

        $measurements = $query->orderByDesc('measured_at')->paginate(30);

        return response()->json($measurements);
    }

    public function store(StoreMeasurementRequest $request, Client $client): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || ! $this->ownsClient($coach, $client)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $measurement = BodyMeasurement::create([
            'client_id' => $client->id,
            'coach_id' => $coach->id,
            'metric_type' => $request->validated('metric_type'),
            'value' => $request->validated('value'),
            'measured_at' => $request->validated('measured_at'),
            'notes' => $request->validated('notes'),
        ]);

        return response()->json(['data' => $measurement], 201);
    }

    public function update(UpdateMeasurementRequest $request, Client $client, BodyMeasurement $measurement): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || $measurement->coach_id !== $coach->id || $measurement->client_id !== $client->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $measurement->update($request->validated());

        return response()->json(['data' => $measurement->fresh()]);
    }

    public function destroy(Request $request, Client $client, BodyMeasurement $measurement): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || $measurement->coach_id !== $coach->id || $measurement->client_id !== $client->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $measurement->delete();

        return response()->json(null, 204);
    }

    public function chart(Request $request, Client $client): JsonResponse
    {
        /** @var Coach|null $coach */
        $coach = $request->user()->coach;
        if (! $coach || ! $this->ownsClient($coach, $client)) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $metricType = MetricType::tryFrom($request->input('metric_type', ''));
        if (! $metricType) {
            return response()->json(['message' => 'metric_type richiesto.'], 422);
        }

        $range = $request->input('range', 'all');
        $from = $this->resolveRangeStart($range);

        $query = BodyMeasurement::forClient($client->id)
            ->forCoach($coach->id)
            ->ofType($metricType)
            ->orderBy('measured_at');

        if ($from) {
            $query->where('measured_at', '>=', $from);
        }

        $points = $query->get(['measured_at', 'value'])->map(fn ($m) => [
            'date' => $m->measured_at->toDateString(),
            'value' => $m->value,
        ]);

        $goal = MeasurementGoal::where('client_id', $client->id)
            ->where('coach_id', $coach->id)
            ->where('metric_type', $metricType->value)
            ->first();

        $summary = null;
        if ($points->isNotEmpty()) {
            $values = $points->pluck('value');
            $firstValue = $values->first();
            $lastValue = $values->last();
            $summary = [
                'first_value' => $firstValue,
                'last_value' => $lastValue,
                'delta' => round($lastValue - $firstValue, 2),
                'delta_percent' => $firstValue != 0 ? round((($lastValue - $firstValue) / $firstValue) * 100, 2) : null,
                'min' => $values->min(),
                'max' => $values->max(),
            ];
        }

        return response()->json([
            'data' => [
                'metric_type' => $metricType->value,
                'unit' => $metricType->getUnit(),
                'points' => $points->values(),
                'goal' => $goal ? [
                    'target_value' => $goal->target_value,
                    'target_date' => $goal->target_date?->toDateString(),
                ] : null,
                'summary' => $summary,
            ],
        ]);
    }

    private function resolveRangeStart(string $range): ?Carbon
    {
        return match ($range) {
            '1M' => now()->subMonth(),
            '3M' => now()->subMonths(3),
            '6M' => now()->subMonths(6),
            '1Y' => now()->subYear(),
            default => null,
        };
    }
}
