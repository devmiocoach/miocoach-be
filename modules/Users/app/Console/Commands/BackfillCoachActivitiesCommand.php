<?php

namespace App\Modules\Users\Console\Commands;

use App\Modules\Users\Enums\CoachActivityType;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachActivity;
use Illuminate\Console\Command;
use Modules\Bookings\Models\Booking;

class BackfillCoachActivitiesCommand extends Command
{
    protected $signature = 'coach:backfill-activities {--days=30 : Number of days to look back}';

    protected $description = 'Backfill coach_activities table from existing bookings and client attachments';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $since = now()->subDays($days);

        $this->info("Backfilling activities from the last {$days} days...");

        $count = 0;

        // Backfill bookings created
        Booking::where('created_at', '>=', $since)
            ->whereNotNull('coach_id')
            ->orderBy('created_at')
            ->chunk(200, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    CoachActivity::firstOrCreate([
                        'coach_id' => $booking->coach_id,
                        'event_type' => CoachActivityType::NewBooking,
                        'subject_type' => Booking::class,
                        'subject_id' => $booking->id,
                    ], [
                        'metadata' => [
                            'client_name' => $booking->client?->user->name ?? $booking->guest_name,
                            'service_type' => $booking->service_type?->value,
                            'starts_at' => $booking->starts_at?->toISOString(),
                        ],
                        'created_at' => $booking->created_at,
                    ]);
                    $count++;
                }
            });

        // Backfill completed bookings
        Booking::where('updated_at', '>=', $since)
            ->where('status', 'completed')
            ->whereNotNull('coach_id')
            ->orderBy('updated_at')
            ->chunk(200, function ($bookings) use (&$count) {
                foreach ($bookings as $booking) {
                    CoachActivity::firstOrCreate([
                        'coach_id' => $booking->coach_id,
                        'event_type' => CoachActivityType::SessionCompleted,
                        'subject_type' => Booking::class,
                        'subject_id' => $booking->id,
                    ], [
                        'metadata' => [
                            'client_name' => $booking->client?->user->name ?? $booking->guest_name,
                            'service_type' => $booking->service_type?->value,
                        ],
                        'created_at' => $booking->updated_at,
                    ]);
                    $count++;
                }
            });

        // Backfill new client attachments
        Coach::whereHas('athletes')->each(function (Coach $coach) use ($since, &$count) {
            $coach->athletes()
                ->wherePivot('created_at', '>=', $since)
                ->get()
                ->each(function ($client) use ($coach, &$count) {
                    CoachActivity::firstOrCreate([
                        'coach_id' => $coach->id,
                        'event_type' => CoachActivityType::NewClient,
                        'subject_type' => $client::class,
                        'subject_id' => $client->id,
                    ], [
                        'metadata' => [
                            'client_name' => $client->user?->name,
                        ],
                        'created_at' => $client->pivot->created_at,
                    ]);
                    $count++;
                });
        });

        $this->info("Done. Processed {$count} records.");

        return self::SUCCESS;
    }
}
