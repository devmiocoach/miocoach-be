<?php

namespace Tests\Feature\Users;

use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachAvailabilityTest extends TestCase
{
    use WithCoachUser;

    public function test_coach_can_set_availability_slots(): void
    {
        [$user, $coach] = $this->createCoachUser();

        $response = $this->actingAsCoach($user)
            ->putJson('/api/v1/coaches/me/availability', [
                'slots' => [
                    [
                        'day_of_week'      => 1,
                        'start_time'       => '09:00',
                        'end_time'         => '12:00',
                        'duration_minutes' => 60,
                        'is_recurring'     => true,
                    ],
                    [
                        'day_of_week'      => 3,
                        'start_time'       => '14:00',
                        'end_time'         => '18:00',
                        'duration_minutes' => 45,
                        'is_recurring'     => true,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('coach_availabilities', 2);
    }

    public function test_availability_replace_deletes_existing_slots(): void
    {
        [$user, $coach] = $this->createCoachUser();

        $coach->availabilities()->create([
            'day_of_week'      => 1,
            'start_time'       => '09:00',
            'end_time'         => '12:00',
            'duration_minutes' => 60,
            'is_recurring'     => true,
        ]);

        $this->actingAsCoach($user)
            ->putJson('/api/v1/coaches/me/availability', [
                'slots' => [
                    [
                        'day_of_week'      => 5,
                        'start_time'       => '10:00',
                        'end_time'         => '13:00',
                        'duration_minutes' => 30,
                        'is_recurring'     => false,
                    ],
                ],
            ]);

        $this->assertDatabaseCount('coach_availabilities', 1);
        $this->assertDatabaseHas('coach_availabilities', ['day_of_week' => 5]);
        $this->assertDatabaseMissing('coach_availabilities', ['day_of_week' => 1]);
    }

    public function test_availability_validation_rejects_invalid_day(): void
    {
        [$user, $coach] = $this->createCoachUser();

        $response = $this->actingAsCoach($user)
            ->putJson('/api/v1/coaches/me/availability', [
                'slots' => [
                    [
                        'day_of_week'      => 9,
                        'start_time'       => '09:00',
                        'end_time'         => '12:00',
                        'duration_minutes' => 60,
                        'is_recurring'     => true,
                    ],
                ],
            ]);

        $response->assertUnprocessable();
    }
}
