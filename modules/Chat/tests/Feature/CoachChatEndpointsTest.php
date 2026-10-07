<?php

namespace Modules\Chat\Tests\Feature;

use App\Enums\Role;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Chat\Models\Conversation;
use Modules\WorkoutPlans\Models\WorkoutPlan;
use Tests\TestCase;

class CoachChatEndpointsTest extends TestCase
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
        $conversation = Conversation::factory()->create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
        ]);

        return [$coach, $client, $conversation];
    }

    public function test_coach_can_send_and_list_messages_over_session_auth(): void
    {
        [$coach, , $conversation] = $this->scenario();

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson("/coach/chat/conversations/{$conversation->id}/messages", ['body' => 'ciao web'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'ciao web');

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->getJson("/coach/chat/conversations/{$conversation->id}/messages")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'ciao web');
    }

    public function test_coach_can_mark_messages_read_over_session_auth(): void
    {
        [$coach, $client, $conversation] = $this->scenario();
        $client->user->assignRole(Role::CLIENT);

        // A message from the client, so the coach has something unread to mark.
        $conversation->messages()->create([
            'sender_user_id' => $client->user->id,
            'type' => 'text',
            'body' => 'from client',
        ]);

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson("/coach/chat/conversations/{$conversation->id}/read")
            ->assertOk()
            ->assertJsonCount(1, 'message_ids');
    }

    public function test_non_participant_coach_is_denied(): void
    {
        [, , $conversation] = $this->scenario();
        $outsider = Coach::factory()->create();
        $outsider->user->assignRole(Role::COACH);

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($outsider->user)
            ->getJson("/coach/chat/conversations/{$conversation->id}/messages")
            ->assertForbidden();
    }

    public function test_coach_sends_workout_plan_card_over_session_auth(): void
    {
        [$coach, , $conversation] = $this->scenario();
        $plan = WorkoutPlan::factory()->create([
            'coach_id' => $coach->id,
            'name' => 'Scheda Web',
        ]);

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson("/coach/chat/conversations/{$conversation->id}/workout-plan", ['workout_plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('data.type', 'workout_plan')
            ->assertJsonPath('data.meta.name', 'Scheda Web');
    }

    public function test_coach_cannot_send_another_coachs_plan_over_session_auth(): void
    {
        [$coach, , $conversation] = $this->scenario();
        $foreignPlan = WorkoutPlan::factory()->create();

        $this->withoutMiddleware(EnsureOnboardingComplete::class)
            ->actingAs($coach->user)
            ->postJson("/coach/chat/conversations/{$conversation->id}/workout-plan", ['workout_plan_id' => $foreignPlan->id])
            ->assertForbidden();
    }
}
