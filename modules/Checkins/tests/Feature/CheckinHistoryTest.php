<?php

namespace Modules\Checkins\Tests\Feature;

use App\Enums\Role;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Checkins\Models\CheckinForm;
use Modules\Checkins\Models\CheckinResponse;
use Tests\TestCase;

class CheckinHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The JWT `jwt.auth` middleware requires a real bearer token and does not
     * honor `actingAs()`. Feature tests bypass it (and `verified.email`)
     * the same way other Checkins Api tests do.
     */
    private function actingAsCoach(Coach $coach): self
    {
        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($coach->user);

        return $this;
    }

    public function test_coach_sees_client_checkin_history_with_questions_expanded(): void
    {
        $coach = Coach::factory()->create();
        $coach->user->assignRole(Role::COACH);
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $form = CheckinForm::factory()->for($coach)->create([
            'questions' => [['id' => 'q1', 'text' => 'Peso?', 'type' => 'number', 'required' => true, 'order' => 1]],
        ]);
        CheckinResponse::factory()->for($form)->for($client)->create([
            'answers' => [['questionId' => 'q1', 'value' => 80]],
            'submitted_at' => '2026-06-05 19:00:00',
        ]);

        $this->actingAsCoach($coach)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/checkins?from=2026-06-01&to=2026-06-30")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.answers.0.questionText', 'Peso?');
    }

    public function test_coach_cannot_see_unlinked_client_history(): void
    {
        $coach = Coach::factory()->create();
        $coach->user->assignRole(Role::COACH);
        $stranger = Client::factory()->create();

        $this->actingAsCoach($coach)
            ->getJson("/api/v1/coaches/me/clients/{$stranger->id}/checkins")
            ->assertForbidden();
    }
}
