<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Resources\CoachActivityResource;
use App\Modules\Users\Services\CoachDashboardStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Bookings\Http\Resources\BookingResource;

/**
 * Mobile counterpart of the Inertia coach dashboard. Serves the same KPIs from
 * the same [CoachDashboardStatsService], so the two views can never disagree.
 */
class CoachDashboardController extends Controller
{
    public function __invoke(Request $request, CoachDashboardStatsService $stats): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        return response()->json([
            'data' => [
                'stats' => [
                    'active_clients_count' => $stats->activeClientsCount($coach),
                    'sessions_this_week' => $stats->sessionsThisWeek($coach),
                    'revenue_this_month' => $stats->revenueThisMonth($coach),
                    'expiring_clients_count' => $stats->expiringClientsCount($coach),
                    'expiring_this_week_count' => $stats->expiringThisWeekCount($coach),
                    'inactive_clients_count' => $stats->inactiveClientsCount($coach),
                ],
                'today_sessions' => BookingResource::collection($stats->todaySessions($coach)),
                'recent_activities' => CoachActivityResource::collection($stats->recentActivities($coach)),
            ],
        ]);
    }
}
