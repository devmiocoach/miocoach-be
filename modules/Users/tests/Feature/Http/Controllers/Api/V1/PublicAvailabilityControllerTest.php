<?php

namespace App\Modules\Users\Tests\Feature\Http\Controllers\Api\V1;

use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachAvailability;
use App\Modules\Users\Providers\UsersServiceProvider;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Modules\Auth\Providers\AuthServiceProvider;
use Modules\Bookings\Providers\BookingsServiceProvider;
use Modules\Bookings\Services\BookingService;
use Tests\TestCase;

class PublicAvailabilityControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
        $this->app->register(AuthServiceProvider::class);
        $this->app->register(BookingsServiceProvider::class);
    }

    public function test_available_dates_returns_dates_for_month(): void
    {
        $coach = Coach::factory()->create(['is_published' => true]);

        CoachAvailability::factory()->create([
            'coach_id' => $coach->id,
            'day_of_week' => 1,
            'is_recurring' => true,
            'start_time' => '09:00:00',
            'end_time' => '12:00:00',
            'duration_minutes' => 60,
        ]);

        $mock = Mockery::mock(BookingService::class);
        $mock->shouldReceive('getAvailableSlots')
            ->andReturn([
                ['starts_at' => '2026-08-03T09:00:00Z', 'ends_at' => '2026-08-03T10:00:00Z', 'duration_minutes' => 60, 'available' => true],
            ]);
        $this->app->instance(BookingService::class, $mock);

        $response = $this->getJson("/api/v1/coaches/{$coach->id}/available-dates?month=2026-08");

        $response->assertOk();
        $response->assertJsonStructure(['data']);
        $this->assertIsArray($response->json('data'));
    }

    public function test_available_dates_requires_month_parameter(): void
    {
        $coach = Coach::factory()->create(['is_published' => true]);

        $response = $this->getJson("/api/v1/coaches/{$coach->id}/available-dates");

        $response->assertUnprocessable();
    }

    public function test_available_dates_returns_404_for_unpublished_coach(): void
    {
        $coach = Coach::factory()->create(['is_published' => false]);

        $response = $this->getJson("/api/v1/coaches/{$coach->id}/available-dates?month=2026-08");

        $response->assertNotFound();
    }

    public function test_slots_returns_available_slots_for_date(): void
    {
        $coach = Coach::factory()->create(['is_published' => true]);

        $futureDate = Carbon::today()->addDays(3)->toDateString();

        $mock = Mockery::mock(BookingService::class);
        $mock->shouldReceive('getAvailableSlots')
            ->once()
            ->andReturn([
                ['starts_at' => $futureDate.'T09:00:00Z', 'ends_at' => $futureDate.'T10:00:00Z', 'duration_minutes' => 60, 'available' => true],
                ['starts_at' => $futureDate.'T10:00:00Z', 'ends_at' => $futureDate.'T11:00:00Z', 'duration_minutes' => 60, 'available' => false],
            ]);
        $this->app->instance(BookingService::class, $mock);

        $response = $this->getJson("/api/v1/coaches/{$coach->id}/slots?date={$futureDate}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_slots_requires_date_parameter(): void
    {
        $coach = Coach::factory()->create(['is_published' => true]);

        $response = $this->getJson("/api/v1/coaches/{$coach->id}/slots");

        $response->assertUnprocessable();
    }

    public function test_slots_rejects_past_date(): void
    {
        $coach = Coach::factory()->create(['is_published' => true]);

        $response = $this->getJson("/api/v1/coaches/{$coach->id}/slots?date=2020-01-01");

        $response->assertUnprocessable();
    }
}
