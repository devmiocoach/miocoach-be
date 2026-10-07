<?php

namespace App\Modules\Users\Tests\Feature\Http;

use App\Enums\UserType;
use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachAvailability;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Providers\AuthServiceProvider;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Providers\BookingsServiceProvider;
use Tests\TestCase;

class CoachAgendaControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(BookingsServiceProvider::class);
    }

    private function createCoachUser(): User
    {
        $user = User::factory()->create(['type' => UserType::COACH]);
        $user->coach()->save(Coach::factory()->make(['onboarding_completed_at' => now()]));
        $user->assignRole('coach');

        return $user;
    }

    private function createClientForCoach(Coach $coach): Client
    {
        $user = User::factory()->create(['type' => UserType::ATLETA]);
        $client = Client::factory()->create(['user_id' => $user->id]);
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        return $client;
    }

    public function test_coach_can_view_agenda(): void
    {
        $user = $this->createCoachUser();

        $response = $this->actingAs($user)->get(route('coach.agenda'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Users::Coach/Agenda/Index', false)
            ->has('bookings')
            ->has('weekStart')
            ->has('weekEnd')
            ->has('availabilities')
            ->has('clients')
            ->has('serviceTypes')
            ->has('defaultDuration')
        );
    }

    public function test_agenda_returns_week_bookings(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $thisWeek = Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now()->startOfWeek()->addDay()->setHour(10),
            'ends_at' => now()->startOfWeek()->addDay()->setHour(11),
            'status' => BookingStatus::Confirmed,
        ]);

        $nextWeek = Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now()->addWeek()->startOfWeek()->addDay()->setHour(10),
            'ends_at' => now()->addWeek()->startOfWeek()->addDay()->setHour(11),
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($user)->get(route('coach.agenda'));

        $response->assertInertia(fn ($page) => $page
            ->has('bookings', 1)
            ->where('bookings.0.id', $thisWeek->id)
        );
    }

    public function test_agenda_respects_week_param(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $nextMonday = now()->addWeek()->startOfWeek();

        Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => $nextMonday->copy()->addDay()->setHour(10),
            'ends_at' => $nextMonday->copy()->addDay()->setHour(11),
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($user)->get(route('coach.agenda', [
            'week' => $nextMonday->toDateString(),
        ]));

        $response->assertInertia(fn ($page) => $page
            ->has('bookings', 1)
        );
    }

    public function test_agenda_excludes_cancelled_bookings(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now()->startOfWeek()->addDay()->setHour(14),
            'ends_at' => now()->startOfWeek()->addDay()->setHour(15),
            'status' => BookingStatus::Cancelled,
        ]);

        $response = $this->actingAs($user)->get(route('coach.agenda'));

        $response->assertInertia(fn ($page) => $page
            ->has('bookings', 0)
        );
    }

    public function test_coach_can_create_booking(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;
        $client = $this->createClientForCoach($coach);

        CoachAvailability::create([
            'coach_id' => $coach->id,
            'day_of_week' => now()->addDay()->dayOfWeek,
            'start_time' => '08:00',
            'end_time' => '18:00',
            'is_recurring' => true,
            'duration_minutes' => 60,
        ]);

        $startsAt = now()->addDay()->setHour(10)->setMinute(0)->setSecond(0);
        $endsAt = $startsAt->copy()->addHour();

        $response = $this->actingAs($user)->post(route('coach.agenda.bookings.store'), [
            'client_id' => $client->id,
            'service_type' => BookingServiceType::PersonalTraining->value,
            'starts_at' => $startsAt->toISOString(),
            'ends_at' => $endsAt->toISOString(),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('bookings', [
            'coach_id' => $coach->id,
            'client_id' => $client->id,
            'service_type' => BookingServiceType::PersonalTraining->value,
        ]);
    }

    public function test_coach_can_reschedule_booking(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now()->addDay()->setHour(10),
            'ends_at' => now()->addDay()->setHour(11),
            'status' => BookingStatus::Confirmed,
        ]);

        $newStart = now()->addDays(2)->setHour(14)->setMinute(0)->setSecond(0);
        $newEnd = $newStart->copy()->addHour();

        $response = $this->actingAs($user)->patch(route('coach.agenda.bookings.update', $booking), [
            'starts_at' => $newStart->toISOString(),
            'ends_at' => $newEnd->toISOString(),
        ]);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals($newStart->format('Y-m-d H:i'), $booking->starts_at->format('Y-m-d H:i'));
    }

    public function test_coach_can_complete_booking(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now(),
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($user)->patch(route('coach.agenda.bookings.complete', $booking));

        $response->assertRedirect();
        $this->assertEquals(BookingStatus::Completed->value, $booking->fresh()->status->value);
    }

    public function test_coach_can_cancel_booking(): void
    {
        $user = $this->createCoachUser();
        $coach = $user->coach;

        $booking = Booking::factory()->create([
            'coach_id' => $coach->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($user)->delete(route('coach.agenda.bookings.destroy', $booking), [
            'reason' => 'Client requested',
        ]);

        $response->assertRedirect();
        $this->assertEquals(BookingStatus::Cancelled->value, $booking->fresh()->status->value);
    }

    public function test_coach_cannot_modify_other_coaches_booking(): void
    {
        $user = $this->createCoachUser();

        $otherCoachUser = $this->createCoachUser();
        $otherCoach = $otherCoachUser->coach;

        $booking = Booking::factory()->create([
            'coach_id' => $otherCoach->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'status' => BookingStatus::Confirmed,
        ]);

        $response = $this->actingAs($user)->patch(route('coach.agenda.bookings.complete', $booking));

        $response->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $response = $this->get(route('coach.agenda'));

        $response->assertRedirect();
    }
}
