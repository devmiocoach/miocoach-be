<?php

namespace Modules\Bookings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Exceptions\BookingOverlapException;
use Modules\Bookings\Http\Requests\CancelBookingRequest;
use Modules\Bookings\Http\Requests\StoreCoachBookingRequest;
use Modules\Bookings\Http\Requests\UpdateBookingRequest;
use Modules\Bookings\Http\Resources\BookingResource;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingService;

class CoachBookingController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $query = Booking::forCoach($coach->id)->with(['client.user']);

        if ($request->filled('from')) {
            $query->where('starts_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('starts_at', '<=', $request->input('to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->input('client_id'));
        }

        $bookings = $query->orderBy('starts_at')->paginate(20);

        return response()->json(BookingResource::collection($bookings)->response()->getData(true));
    }

    public function store(StoreCoachBookingRequest $request): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $client = Client::findOrFail($request->validated('client_id'));

        if (! $coach->athletes()->where('athlete_id', $client->id)->exists()) {
            return response()->json(['message' => 'Client non trovato.'], 404);
        }

        try {
            $booking = $this->service->createForCoach($coach, $client, $request->validated());
        } catch (BookingOverlapException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new BookingResource($booking)], 201);
    }

    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach || $booking->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $data = $request->validated();

        if (isset($data['starts_at'], $data['ends_at'])) {
            try {
                $this->service->reschedule($booking, $data);
            } catch (BookingOverlapException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        if (isset($data['status'])) {
            $newStatus = BookingStatus::from($data['status']);
            if ($newStatus === BookingStatus::Completed) {
                $this->service->complete($booking);
            } else {
                $booking->update(['status' => $newStatus->value]);
            }
        }

        $updatable = array_filter(
            ['notes' => $data['notes'] ?? null, 'price' => $data['price'] ?? null],
            fn ($v) => $v !== null,
        );

        if (! empty($updatable)) {
            $booking->fill($updatable)->save();
        }

        $booking->refresh()->load('client.user');

        return response()->json(['data' => new BookingResource($booking)]);
    }

    public function destroy(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach || $booking->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $this->service->cancel($booking, $request->validated('reason'), 'coach');

        return response()->json(null, 204);
    }
}
