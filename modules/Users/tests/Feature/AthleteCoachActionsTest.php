<?php

namespace App\Modules\Users\Tests\Feature;

use App\Modules\Users\Actions\AttachAthleteToCoachAction;
use App\Modules\Users\Actions\DetachAthleteFromCoachAction;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Providers\UsersServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AthleteCoachActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->app->register(UsersServiceProvider::class);
    }

    public function test_attach_creates_pivot_record(): void
    {
        $coach = Coach::factory()->create();
        $athlete = Client::factory()->create();

        app(AttachAthleteToCoachAction::class)->handle($coach, $athlete);

        $this->assertDatabaseHas('athlete_coach', [
            'coach_id' => $coach->id,
            'athlete_id' => $athlete->id,
            'status' => 'active',
        ]);
    }

    public function test_attach_sets_started_at_when_active(): void
    {
        $coach = Coach::factory()->create();
        $athlete = Client::factory()->create();

        app(AttachAthleteToCoachAction::class)->handle($coach, $athlete, 'active');

        $pivot = $coach->athletes()->first()->pivot;
        $this->assertNotNull($pivot->started_at);
    }

    public function test_attach_pending_does_not_set_started_at(): void
    {
        $coach = Coach::factory()->create();
        $athlete = Client::factory()->create();

        app(AttachAthleteToCoachAction::class)->handle($coach, $athlete, 'pending');

        $pivot = $coach->athletes()->first()->pivot;
        $this->assertNull($pivot->started_at);
    }

    public function test_attach_throws_if_already_attached(): void
    {
        $coach = Coach::factory()->create();
        $athlete = Client::factory()->create();

        app(AttachAthleteToCoachAction::class)->handle($coach, $athlete);

        $this->expectException(ValidationException::class);
        app(AttachAthleteToCoachAction::class)->handle($coach, $athlete);
    }

    public function test_detach_removes_pivot_record(): void
    {
        $coach = Coach::factory()->create();
        $athlete = Client::factory()->create();

        $coach->athletes()->attach($athlete->id, ['status' => 'active', 'started_at' => now()]);

        app(DetachAthleteFromCoachAction::class)->handle($coach, $athlete);

        $this->assertDatabaseMissing('athlete_coach', [
            'coach_id' => $coach->id,
            'athlete_id' => $athlete->id,
        ]);
    }

    public function test_detach_does_not_affect_other_relationships(): void
    {
        $coach = Coach::factory()->create();
        $athlete1 = Client::factory()->create();
        $athlete2 = Client::factory()->create();

        $coach->athletes()->attach($athlete1->id, ['status' => 'active', 'started_at' => now()]);
        $coach->athletes()->attach($athlete2->id, ['status' => 'active', 'started_at' => now()]);

        app(DetachAthleteFromCoachAction::class)->handle($coach, $athlete1);

        $this->assertDatabaseMissing('athlete_coach', ['athlete_id' => $athlete1->id]);
        $this->assertDatabaseHas('athlete_coach', ['athlete_id' => $athlete2->id]);
    }
}
