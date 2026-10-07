<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Bookings\Services\BookingService;

class PublicAvailabilityController extends Controller
{
    public function __construct(
        private readonly BookingService $bookingService,
    ) {}

    public function availableDates(Coach $coach, Request $request): JsonResponse
    {
        if (! $coach->is_published) {
            return response()->json(['message' => 'Coach non trovato.'], 404);
        }

        $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $startOfMonth = Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth();

        $maxMonth = Carbon::today()->addMonths(6)->startOfMonth();
        if ($startOfMonth->greaterThan($maxMonth)) {
            return response()->json(['data' => []]);
        }

        $cacheKey = "coach:{$coach->id}:available-dates:{$request->input('month')}";

        $dates = cache()->remember($cacheKey, 300, function () use ($coach, $startOfMonth) {
            $endOfMonth = $startOfMonth->copy()->endOfMonth();
            $today = Carbon::today();

            $availableDays = $coach->availabilities()
                ->where('is_recurring', true)
                ->pluck('day_of_week')
                ->unique()
                ->toArray();

            if (empty($availableDays)) {
                return [];
            }

            $dates = [];
            $current = $startOfMonth->copy()->max($today);

            while ($current->lte($endOfMonth)) {
                $dayOfWeek = (int) $current->format('w');
                if (in_array($dayOfWeek, $availableDays)) {
                    $slots = $this->bookingService->getAvailableSlots($coach, $current->copy());
                    $hasAvailable = collect($slots)->contains('available', true);
                    if ($hasAvailable) {
                        $dates[] = $current->toDateString();
                    }
                }
                $current->addDay();
            }

            return $dates;
        });

        return response()->json(['data' => $dates]);
    }

    public function slots(Coach $coach, Request $request): JsonResponse
    {
        if (! $coach->is_published) {
            return response()->json(['message' => 'Coach non trovato.'], 404);
        }

        $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:480'],
        ]);

        $date = Carbon::parse($request->input('date'));
        $duration = $request->input('duration') ? (int) $request->input('duration') : null;

        $slots = $this->bookingService->getAvailableSlots($coach, $date, $duration);

        $available = array_filter($slots, fn (array $slot) => $slot['available']);

        return response()->json(['data' => array_values($available)]);
    }
}
