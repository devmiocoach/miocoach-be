<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Models\Booking;

class CoachAgendaController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $coach = $request->user()->coach;

        $weekStart = $request->filled('week')
            ? Carbon::parse($request->input('week'))->startOfWeek()
            : now()->startOfWeek();

        $weekEnd = $weekStart->copy()->endOfWeek();

        $bookings = Booking::forCoach($coach->id)
            ->with(['client.user'])
            ->whereBetween('starts_at', [$weekStart, $weekEnd])
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'client_id' => $b->client_id,
                'client' => $b->client ? [
                    'id' => $b->client->id,
                    'name' => $b->client->user?->name,
                    'avatar' => $b->client->user?->profile_photo_url,
                ] : null,
                'guest_name' => $b->guest_name,
                'starts_at' => $b->starts_at->toISOString(),
                'ends_at' => $b->ends_at?->toISOString(),
                'duration_minutes' => $b->duration_minutes,
                'status' => $b->status->value,
                'service_type' => $b->service_type?->value,
                'service_type_label' => $b->service_type?->getLabel(),
                'notes' => $b->notes,
                'price' => $b->price,
            ]);

        $availabilities = $coach->availabilities()
            ->where('is_recurring', true)
            ->get()
            ->map(fn ($a) => [
                'day_of_week' => $a->day_of_week,
                'start_time' => $a->start_time,
                'end_time' => $a->end_time,
            ]);

        $clients = $coach->activeAthletes()
            ->with('user')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->user?->name,
            ]);

        $serviceTypes = collect(BookingServiceType::cases())->map(fn ($t) => [
            'value' => $t->value,
            'label' => $t->getLabel(),
        ]);

        return Inertia::render('Users::Coach/Agenda/Index', [
            'bookings' => $bookings,
            'weekStart' => $weekStart->toISOString(),
            'weekEnd' => $weekEnd->toISOString(),
            'availabilities' => $availabilities,
            'clients' => $clients,
            'serviceTypes' => $serviceTypes,
            'defaultDuration' => $coach->session_duration_default ?? 60,
        ])->withoutSSR();
    }
}
