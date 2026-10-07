<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Requests\CoachListingRequest;
use App\Modules\Users\Http\Resources\CoachListingResource;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\Review;
use Illuminate\Http\JsonResponse;

class CoachListingController extends Controller
{
    private const TIME_SLOTS = [
        'mattina' => ['06:00:00', '12:00:00'],
        'pomeriggio' => ['12:00:00', '18:00:00'],
        'sera' => ['18:00:00', '22:00:00'],
    ];

    public function index(CoachListingRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = Coach::query()
            ->where('is_published', true)
            ->with(['user', 'specializationRecords:id,name,slug'])
            ->withCount('publishedReviews as reviews_count')
            ->withAvg('publishedReviews as avg_rating', 'rating');

        if (! empty($filters['search'])) {
            $term = '%'.$this->escapeLike($filters['search']).'%';
            // `whereLike` compiles to ILIKE on Postgres and LIKE elsewhere.
            $query->where(function ($q) use ($term) {
                $q->whereLike('display_name', $term)
                    ->orWhereHas('user', fn ($u) => $u->whereLike('name', $term))
                    ->orWhereLike('tagline', $term)
                    ->orWhereLike('bio', $term)
                    ->orWhereHas('specializationRecords', fn ($s) => $s->whereLike('name', $term));
            });
        }

        if (! empty($filters['city'])) {
            $query->whereLike('city', '%'.$this->escapeLike($filters['city']).'%');
        }

        if (! empty($filters['mode'])) {
            $query->whereIn('mode', $filters['mode']);
        }

        if (isset($filters['price_min'])) {
            $query->where('single_session_price_cents', '>=', $filters['price_min'] * 100);
        }

        if (isset($filters['price_max'])) {
            $query->where('single_session_price_cents', '<=', $filters['price_max'] * 100);
        }

        if (! empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (! empty($filters['specialization'])) {
            $query->whereHas('specializationRecords', fn ($s) => $s->whereIn('slug', $filters['specialization']));
        }

        if (isset($filters['rating_min'])) {
            // A subquery instead of HAVING on the withAvg alias: Postgres rejects
            // aliases in HAVING without GROUP BY, and the pagination count breaks too.
            // CAST(? AS NUMERIC): PDO SQLite binds PHP floats as TEXT and SQLite's
            // storage-class ordering (REAL < TEXT) would make the comparison fail.
            $query->whereIn('coaches.id', Review::query()
                ->select('coach_id')
                ->whereNotNull('published_at')
                ->groupBy('coach_id')
                ->havingRaw('AVG(rating) >= CAST(? AS NUMERIC)', [(string) $filters['rating_min']]));
        }

        if (! empty($filters['available_days'])) {
            $query->whereHas('availabilities', fn ($q) => $q
                ->whereIn('day_of_week', $filters['available_days'])
                ->where('is_recurring', true));
        }

        if (! empty($filters['time_slots'])) {
            $ranges = array_map(fn ($slot) => self::TIME_SLOTS[$slot], $filters['time_slots']);
            $query->whereHas('availabilities', function ($q) use ($ranges) {
                $q->where('is_recurring', true)->where(function ($sub) use ($ranges) {
                    foreach ($ranges as [$from, $to]) {
                        $sub->orWhere(fn ($inner) => $inner->where('start_time', '<', $to)->where('end_time', '>', $from));
                    }
                });
            });
        }

        match ($filters['sort'] ?? 'relevance') {
            'rating_desc' => $query->orderByRaw($this->nullsLast('avg_rating', 'DESC')),
            'price_asc' => $query->orderByRaw($this->nullsLast('single_session_price_cents', 'ASC')),
            'price_desc' => $query->orderByRaw($this->nullsLast('single_session_price_cents', 'DESC')),
            default => $query->orderByDesc('is_verified')->orderByRaw($this->nullsLast('avg_rating', 'DESC'))->latest('id'),
        };

        $coaches = $query->paginate($filters['limit'] ?? 20);

        return response()->json(CoachListingResource::collection($coaches)->response()->getData(true));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Postgres supports `NULLS LAST`; SQLite/MySQL need an explicit null flag first.
     */
    private function nullsLast(string $column, string $direction): string
    {
        $nullLast = sprintf('%s %s NULLS LAST', $column, $direction);
        $nullFirst = sprintf('%s IS NULL, %s %s', $column, $column, $direction);

        return Coach::query()->getConnection()->getDriverName() === 'pgsql' ? $nullLast : $nullFirst;
    }
}
