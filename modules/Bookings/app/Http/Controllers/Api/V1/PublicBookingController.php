<?php

namespace Modules\Bookings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Firebase\JWT\ExpiredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Modules\Auth\Services\JwtService;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Exceptions\BookingOverlapException;
use Modules\Bookings\Http\Requests\StorePublicBookingRequest;
use Modules\Bookings\Http\Resources\BookingResource;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingCalendarFile;
use Modules\Bookings\Services\BookingService;

class PublicBookingController extends Controller
{
    public function __construct(
        private readonly BookingService $service,
        private readonly JwtService $jwt,
    ) {}

    public function store(StorePublicBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->service->createPublic($request->validated());
        } catch (BookingOverlapException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new BookingResource($booking)], 201);
    }

    public function calendar(string $token, BookingCalendarFile $calendar): JsonResponse|Response
    {
        try {
            $payload = $this->jwt->decode($token);
        } catch (ExpiredException) {
            return response()->json(['message' => 'Il token calendario è scaduto.'], 422);
        } catch (\Throwable) {
            return response()->json(['message' => 'Token non valido.'], 404);
        }

        if (($payload->purpose ?? null) !== 'booking_cancellation' || ! isset($payload->booking_id)) {
            return response()->json(['message' => 'Token non valido.'], 404);
        }

        $booking = Booking::where('cancellation_token', $token)
            ->whereNotNull('cancellation_token')
            ->whereKey((int) $payload->booking_id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Token non valido.'], 404);
        }

        return response($calendar->contents($booking), 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"booking-{$booking->id}.ics\"",
        ]);
    }

    public function cancel(string $token): JsonResponse
    {
        try {
            $payload = $this->jwt->decode($token);
        } catch (ExpiredException) {
            return response()->json(['message' => 'Il token di cancellazione è scaduto.'], 422);
        } catch (\Throwable) {
            return response()->json(['message' => 'Token non valido.'], 404);
        }

        if (($payload->purpose ?? null) !== 'booking_cancellation' || ! isset($payload->booking_id)) {
            return response()->json(['message' => 'Token non valido.'], 404);
        }

        $booking = Booking::where('cancellation_token', $token)
            ->whereNotNull('cancellation_token')
            ->whereKey((int) $payload->booking_id)
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Token non valido.'], 404);
        }

        if ($booking->cancellation_token_expires_at?->isPast()) {
            return response()->json(['message' => 'Il token di cancellazione è scaduto.'], 422);
        }

        $windowHours = $booking->coach->cancellation_window_hours ?? 24;
        $hoursUntilBooking = now()->diffInHours($booking->starts_at, false);

        if ($hoursUntilBooking < $windowHours) {
            return response()->json([
                'message' => "La cancellazione non è più possibile. La policy del coach richiede almeno {$windowHours} ore di preavviso.",
            ], 422);
        }

        $this->service->cancel($booking, 'Cancellato dal cliente via token.', 'client');
        $booking->forceFill([
            'cancellation_token' => null,
            'cancellation_token_expires_at' => null,
            'status' => BookingStatus::Cancelled->value,
        ])->save();

        return response()->json(['message' => 'Prenotazione cancellata con successo.']);
    }
}
