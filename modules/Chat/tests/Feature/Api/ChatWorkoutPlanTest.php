<?php

namespace Modules\Chat\Tests\Feature\Api;

use App\Enums\Role;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Chat\Models\Conversation;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class ChatWorkoutPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Coach, 1: Client, 2: Conversation}
     */
    private function scenario(): array
    {
        $coach = Coach::factory()->create();
        $coach->user->assignRole(Role::COACH);
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        $conversation = Conversation::factory()->create(['coach_id' => $coach->id, 'client_id' => $client->id]);

        return [$coach, $client, $conversation];
    }

    public function test_coach_sends_workout_plan_card(): void
    {
        [$coach, , $conversation] = $this->scenario();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $coach->id, 'name' => 'Scheda Forza']);

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/workout-plan", ['workout_plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('data.type', 'workout_plan')
            ->assertJsonPath('data.meta.workout_plan_id', $plan->id)
            ->assertJsonPath('data.meta.name', 'Scheda Forza');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'type' => 'workout_plan',
        ]);
    }

    public function test_coach_cannot_send_another_coachs_plan(): void
    {
        [$coach, , $conversation] = $this->scenario();
        $foreignPlan = WorkoutPlan::factory()->create();

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/workout-plan", ['workout_plan_id' => $foreignPlan->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $conversation->id,
            'type' => 'workout_plan',
        ]);
    }

    public function test_non_participant_cannot_send_workout_plan(): void
    {
        [$coach, , $conversation] = $this->scenario();
        $plan = WorkoutPlan::factory()->create(['coach_id' => $coach->id]);

        $outsider = Coach::factory()->create();
        $outsider->user->assignRole(Role::COACH);

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($outsider->user)
            ->postJson("/api/v1/conversations/{$conversation->id}/workout-plan", ['workout_plan_id' => $plan->id])
            ->assertForbidden();
    }
}
