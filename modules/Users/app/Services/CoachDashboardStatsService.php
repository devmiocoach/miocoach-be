<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachActivity;
use Illuminate\Database\Eloquent\Collection;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;

/**
 * Aggregates the coach dashboard KPIs.
 *
 * Shared by the Inertia web dashboard (`CoachDashboardController`) and the
 * mobile JSON API (`Api\V1\CoachDashboardController`) so the two never drift.
 * Every method is a single query — callers pick the ones they need.
 */
class CoachDashboardStatsService
{
    /**
     * Bookings that count as "real" sessions for KPI purposes.
     *
     * @var list<BookingStatus>
     */
    private const COUNTED_STATUSES = [BookingStatus::Confirmed, BookingStatus::Completed];

    public function activeClientsCount(Coach $coach): int
    {
        return $coach->activeAthletes()->count();
    }

    public function sessionsThisWeek(Coach $coach): int
    {
        return Booking::forCoach($coach->id)
            ->whereIn('status', self::COUNTED_STATUSES)
            ->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->count();
    }

    public function revenueThisMonth(Coach $coach): float
    {
        return (float) Booking::forCoach($coach->id)
            ->whereIn('status', self::COUNTED_STATUSES)
            ->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('price');
    }

    /**
     * Active athletes whose subscription lapses within the next 7 days.
     *
     * Deliberately a different window from [expiringThisWeekCount]: this one is
     * a rolling 7 days, that one stops at the end of the calendar week.
     */
    public function expiringClientsCount(Coach $coach): int
    {
        return $this->expiringBefore($coach, now()->addDays(7));
    }

    /**
     * Active athletes whose subscription lapses before the calendar week ends.
     */
    public function expiringThisWeekCount(Coach $coach): int
    {
        return $this->expiringBefore($coach, now()->endOfWeek());
    }

    /**
     * Athletes needing attention: suspended links, plus active links whose
     * subscription has already lapsed.
     *
     * Feeds the "clienti scaduti/sospesi" alert banner.
     */
    public function inactiveClientsCount(Coach $coach): int
    {
        $suspendedCount = $coach->athletes()
            ->wherePivot('status', 'suspended')
            ->count();

        $expiredCount = $coach->activeAthletes()
            ->whereNotNull('clients.subscription_expires_at')
            ->where('clients.subscription_expires_at', '<', now())
            ->count();

        return $suspendedCount + $expiredCount;
    }

    /**
     * Today's confirmed/completed sessions, earliest first, with the client
     * (and their user) eager-loaded for display.
     *
     * @return Collection<int, Booking>
     */
    public function todaySessions(Coach $coach): Collection
    {
        return Booking::forCoach($coach->id)
            ->whereDate('starts_at', now()->startOfDay())
            ->whereIn('status', self::COUNTED_STATUSES)
            ->with(['client.user'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @return Collection<int, CoachActivity>
     */
    public function recentActivities(Coach $coach, int $limit = 10): Collection
    {
        return $coach->activities()->limit($limit)->get();
    }

    private function expiringBefore(Coach $coach, \DateTimeInterface $until): int
    {
        return $coach->activeAthletes()
            ->whereNotNull('clients.subscription_expires_at')
            ->where('clients.subscription_expires_at', '>', now())
            ->where('clients.subscription_expires_at', '<=', $until)
            ->count();
    }
}
