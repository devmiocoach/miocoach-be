<?php

namespace Modules\Checkins\Console;

use App\Modules\Users\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Modules\Checkins\Enums\CheckinFrequency;
use Modules\Checkins\Models\CheckinDispatch;
use Modules\Checkins\Models\CheckinForm;
use Modules\Checkins\Notifications\CheckinDueNotification;
use Modules\Checkins\Services\Contracts\PushServiceContract;

class DispatchCheckinsCommand extends Command
{
    protected $signature = 'checkins:dispatch';

    protected $description = 'Dispatch due check-in reminders to assigned clients (hourly scheduler with per-client timezone, missed-hour catch-up, and dedupe).';

    public function handle(PushServiceContract $push): int
    {
        $now = Carbon::now();

        CheckinForm::query()
            ->active()
            ->with('clients.user')
            ->get()
            ->each(function (CheckinForm $form) use ($now, $push) {
                foreach ($form->clients as $client) {
                    $this->dispatchIfDue($form, $client, $now, $push);
                }
            });

        return self::SUCCESS;
    }

    private function dispatchIfDue(CheckinForm $form, Client $client, Carbon $now, PushServiceContract $push): void
    {
        $clientNow = $now->copy()->setTimezone($client->timezone ?? config('app.timezone'));
        $assignedAt = Carbon::parse($client->pivot->assigned_at)->setTimezone($clientNow->getTimezone());

        if (! $this->isDayDue($form, $clientNow, $assignedAt)) {
            return;
        }

        // Missed-hour catch-up: any run at or after send_time on the due day
        // fires, not only the exact hour — so a skipped cron tick still sends
        // on the next run within the same day. A due day skipped entirely
        // (e.g. multi-day outage) is not retroactively caught up.
        if (! $this->isPastSendTime($form, $clientNow)) {
            return;
        }

        $periodKey = $this->periodKey($form, $clientNow, $assignedAt);

        if (! $this->recordDispatch($form, $client, $periodKey, $now)) {
            return;
        }

        $client->user?->notify(new CheckinDueNotification($form));

        if ($client->user) {
            $push->send($client->user, 'Check-in', "È ora del tuo check-in: {$form->title}");
        }
    }

    private function isDayDue(CheckinForm $form, Carbon $clientNow, Carbon $assignedAt): bool
    {
        return match ($form->frequency) {
            CheckinFrequency::Daily => true,
            CheckinFrequency::Weekly => $clientNow->dayOfWeek === $assignedAt->dayOfWeek,
            CheckinFrequency::Biweekly => $clientNow->dayOfWeek === $assignedAt->dayOfWeek
                && $this->weeksSinceAssignment($assignedAt, $clientNow) % 2 === 0,
            CheckinFrequency::Monthly => $clientNow->day === $assignedAt->day,
        };
    }

    private function isPastSendTime(CheckinForm $form, Carbon $clientNow): bool
    {
        // send_time is a bare "HH:MM:SS" wall-clock string with no timezone
        // of its own — it's always interpreted in the client's local time.
        $sendTime = substr((string) $form->send_time, 0, 5);

        return $clientNow->format('H:i') >= $sendTime;
    }

    private function periodKey(CheckinForm $form, Carbon $clientNow, Carbon $assignedAt): string
    {
        return match ($form->frequency) {
            CheckinFrequency::Daily => 'day:'.$clientNow->toDateString(),
            CheckinFrequency::Weekly => 'week:'.$clientNow->isoWeekYear.'-'.$clientNow->isoWeek,
            CheckinFrequency::Biweekly => 'biweek:'.intdiv($this->weeksSinceAssignment($assignedAt, $clientNow), 2),
            CheckinFrequency::Monthly => 'month:'.$clientNow->format('Y-m'),
        };
    }

    /**
     * Whole weeks between the assignment and now, aligned to the start of
     * each week before diffing. Diffing the raw timestamps instead would let
     * a DST shift or a time-of-day gap push the count off by one near a week
     * boundary; start-of-week alignment removes that source of error.
     */
    private function weeksSinceAssignment(Carbon $assignedAt, Carbon $clientNow): int
    {
        return (int) $assignedAt->copy()->startOfWeek()->diffInWeeks($clientNow->copy()->startOfWeek());
    }

    private function recordDispatch(CheckinForm $form, Client $client, string $periodKey, Carbon $now): bool
    {
        try {
            CheckinDispatch::create([
                'checkin_form_id' => $form->id,
                'client_id' => $client->id,
                'period_key' => $periodKey,
                'dispatched_at' => $now,
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Already dispatched for this (form, client, period) — including
            // a concurrent run racing us. The unique index is the source of
            // truth, not a prior exists() check.
            return false;
        }
    }
}
