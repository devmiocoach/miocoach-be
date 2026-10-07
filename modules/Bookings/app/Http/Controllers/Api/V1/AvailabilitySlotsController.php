<?php

namespace Modules\Bookings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Bookings\Services\BookingService;

class AvailabilitySlotsController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:480'],
        ]);

        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $date = Carbon::parse($request->input('date'));
        $duration = $request->filled('duration') ? (int) $request->input('duration') : null;

        $slots = $this->service->getAvailableSlots($coach, $date, $duration);

        return response()->json(['data' => $slots]);
    }
}
