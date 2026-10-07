<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Users\Services\CoachDashboardStatsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Bookings\Models\Booking;

class CoachDashboardController extends Controller
{
    public function __invoke(Request $request, CoachDashboardStatsService $stats): Response
    {
        $coach = $request->user()->coach;

        return Inertia::render('Users::Coach/Dashboard', [
            'activeClientsCount' => $stats->activeClientsCount($coach),
            'sessionsThisWeek' => $stats->sessionsThisWeek($coach),
            'revenueThisMonth' => $stats->revenueThisMonth($coach),
            'expiringClientsCount' => $stats->expiringClientsCount($coach),

            'todaySessions' => $stats->todaySessions($coach)->map(fn (Booking $b) => [
                'id' => $b->id,
                'starts_at' => $b->starts_at->toISOString(),
                'ends_at' => $b->ends_at?->toISOString(),
                'status' => $b->status->value,
                'service_type' => $b->service_type?->value,
                'service_type_label' => $b->service_type?->getLabel(),
                'client' => $b->client ? [
                    'id' => $b->client->id,
                    'name' => $b->client->user?->name,
                    'avatar' => $b->client->user?->profile_photo_url,
                ] : null,
                'guest_name' => $b->guest_name,
            ]),

            'expiringThisWeekCount' => $stats->expiringThisWeekCount($coach),
            'inactiveClientsCount' => $stats->inactiveClientsCount($coach),

            // Left untyped on purpose: static analysis cannot see Eloquent's
            // magic properties on this model, and typing it would only add
            // noise to an already-passing baseline.
            'recentActivities' => $stats->recentActivities($coach)->map(fn ($a) => [
                'id' => $a->id,
                'event_type' => $a->event_type->value,
                'metadata' => $a->metadata,
                'created_at' => $a->created_at->toISOString(),
            ]),
        ])->withoutSSR();
    }
}
