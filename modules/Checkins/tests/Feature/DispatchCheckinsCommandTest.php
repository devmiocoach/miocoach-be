<?php

namespace Modules\Checkins\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Modules\Checkins\Models\CheckinForm;
use Modules\Checkins\Notifications\CheckinDueNotification;
use Tests\TestCase;

class DispatchCheckinsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_form_due_at_matching_hour_notifies_client(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 19:00:00'));
        Notification::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $form = CheckinForm::factory()->for($coach)->create([
            'frequency' => 'daily',
            'send_time' => '19:00',
            'is_active' => true,
        ]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        $this->artisan('checkins:dispatch')->assertSuccessful();

        Notification::assertSentTo($client->user, CheckinDueNotification::class);
        Carbon::setTestNow();
    }

    public function test_form_not_due_at_other_hour_is_skipped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 08:00:00'));
        Notification::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $form = CheckinForm::factory()->for($coach)->create(['frequency' => 'daily', 'send_time' => '19:00', 'is_active' => true]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        $this->artisan('checkins:dispatch')->assertSuccessful();

        Notification::assertNothingSent();
        Carbon::setTestNow();
    }

    public function test_missed_hour_is_caught_up_on_a_later_run_the_same_day(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $form = CheckinForm::factory()->for($coach)->create(['frequency' => 'daily', 'send_time' => '19:00', 'is_active' => true]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        // Cron never ran at 19:00 — the next tick, at 20:30, must still catch it up.
        Carbon::setTestNow(Carbon::parse('2026-07-18 20:30:00'));
        $this->artisan('checkins:dispatch')->assertSuccessful();

        Notification::assertSentTo($client->user, CheckinDueNotification::class);
        Carbon::setTestNow();
    }

    public function test_running_dispatch_twice_in_the_same_period_notifies_only_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 19:00:00'));
        Notification::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create(['timezone' => 'UTC']);
        $form = CheckinForm::factory()->for($coach)->create(['frequency' => 'daily', 'send_time' => '19:00', 'is_active' => true]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        $this->artisan('checkins:dispatch')->assertSuccessful();
        $this->artisan('checkins:dispatch')->assertSuccessful();

        Notification::assertSentTimes(CheckinDueNotification::class, 1);
        $this->assertDatabaseCount('checkin_dispatches', 1);
        Carbon::setTestNow();
    }

    public function test_biweekly_form_is_due_two_weeks_after_assignment(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create(['timezone' => 'UTC']);
        $form = CheckinForm::factory()->for($coach)->create(['frequency' => 'biweekly', 'send_time' => '09:00', 'is_active' => true]);

        // Assigned on a Saturday; due again on a Saturday two weeks later.
        $assignedAt = Carbon::parse('2026-07-04 09:00:00');
        $form->clients()->attach($client->id, ['assigned_at' => $assignedAt]);

        Carbon::setTestNow($assignedAt->copy()->addWeeks(2));
        $this->artisan('checkins:dispatch')->assertSuccessful();

        Notification::assertSentTo($client->user, CheckinDueNotification::class);
        Carbon::setTestNow();
    }

    public function test_biweekly_form_is_not_due_one_week_after_assignment(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create(['timezone' => 'UTC']);
        $form = CheckinForm::factory()->for($coach)->create(['frequency' => 'biweekly', 'send_time' => '09:00', 'is_active' => true]);

        $assignedAt = Carbon::parse('2026-07-04 09:00:00');
        $form->clients()->attach($client->id, ['assigned_at' => $assignedAt]);

        Carbon::setTestNow($assignedAt->copy()->addWeeks(1));
        $this->artisan('checkins:dispatch')->assertSuccessful();

        Notification::assertNothingSent();
        Carbon::setTestNow();
    }

    public function test_client_timezone_prevents_a_premature_notification(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();
        // Los Angeles is UTC-7 in July: at UTC 09:00 it's only 02:00 local —
        // long before the client's 09:00 local send_time.
        $client = Client::factory()->create(['timezone' => 'America/Los_Angeles']);
        $form = CheckinForm::factory()->for($coach)->create(['frequency' => 'daily', 'send_time' => '09:00', 'is_active' => true]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        Carbon::setTestNow(Carbon::parse('2026-07-18 09:00:00', 'UTC'));
        $this->artisan('checkins:dispatch')->assertSuccessful();

        Notification::assertNothingSent();
        Carbon::setTestNow();
    }

    public function test_client_timezone_fires_at_the_clients_local_send_time(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();
        // UTC 16:00 is 09:00 in Los Angeles (UTC-7 in July) — local send_time reached.
        $client = Client::factory()->create(['timezone' => 'America/Los_Angeles']);
        $form = CheckinForm::factory()->for($coach)->create(['frequency' => 'daily', 'send_time' => '09:00', 'is_active' => true]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        Carbon::setTestNow(Carbon::parse('2026-07-18 16:00:00', 'UTC'));
        $this->artisan('checkins:dispatch')->assertSuccessful();

        Notification::assertSentTo($client->user, CheckinDueNotification::class);
        Carbon::setTestNow();
    }
}
