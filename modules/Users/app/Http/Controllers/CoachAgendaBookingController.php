<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Bookings\Exceptions\BookingOverlapException;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingService;

class CoachAgendaBookingController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    public function store(Request $request): RedirectResponse
    {
        $coach = $request->user()->coach;

        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'service_type' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'recurrence_rule' => ['nullable', 'string', 'in:weekly,biweekly,monthly'],
        ]);

        $client = Client::findOrFail($data['client_id']);

        abort_if(! $coach->athletes()->where('athlete_id', $client->id)->exists(), 403);

        try {
            if (! empty($data['recurrence_rule'])) {
                $this->service->createRecurring($coach, $client, $data);
            } else {
                $this->service->createForCoach($coach, $client, $data);
            }
        } catch (BookingOverlapException $e) {
            return back()->withErrors(['starts_at' => $e->getMessage()]);
        }

        return back()->with('success', __('Booking created'));
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $coach = $request->user()->coach;

        abort_if($booking->coach_id !== $coach->id, 403);

        $data = $request->validate([
            'starts_at' => ['sometimes', 'required', 'date'],
            'ends_at' => ['sometimes', 'required', 'date', 'after:starts_at'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:15', 'max:480'],
            'service_type' => ['sometimes', 'required', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (isset($data['duration_minutes'])) {
            $startsAt = $booking->starts_at;
            $data['starts_at'] = $startsAt->toISOString();
            $data['ends_at'] = $startsAt->copy()->addMinutes($data['duration_minutes'])->toISOString();
            unset($data['duration_minutes']);
        }

        if (isset($data['starts_at']) || isset($data['ends_at'])) {
            try {
                $this->service->reschedule($booking, $data);
            } catch (BookingOverlapException $e) {
                return back()->withErrors(['starts_at' => $e->getMessage()]);
            }
        }

        $extraFields = array_intersect_key($data, array_flip(['notes', 'service_type', 'price']));
        if ($extraFields) {
            $booking->update($extraFields);
        }

        return back()->with('success', __('Session updated'));
    }

    public function complete(Request $request, Booking $booking): RedirectResponse
    {
        $coach = $request->user()->coach;

        abort_if($booking->coach_id !== $coach->id, 403);

        $this->service->complete($booking);

        return back()->with('success', __('Session completed'));
    }

    public function noShow(Request $request, Booking $booking): RedirectResponse
    {
        $coach = $request->user()->coach;

        abort_if($booking->coach_id !== $coach->id, 403);

        $this->service->markNoShow($booking);

        return back()->with('success', __('Marked as no-show'));
    }

    public function destroy(Request $request, Booking $booking): RedirectResponse
    {
        $coach = $request->user()->coach;

        abort_if($booking->coach_id !== $coach->id, 403);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? '';

        $this->service->cancel($booking, $reason, 'coach');

        return back()->with('success', __('Session cancelled'));
    }
}
