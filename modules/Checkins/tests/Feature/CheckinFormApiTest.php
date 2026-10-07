<?php

namespace Modules\Checkins\Tests\Feature;

use App\Enums\Role;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Checkins\Models\CheckinForm;
use Tests\TestCase;

class CheckinFormApiTest extends TestCase
{
    use RefreshDatabase;

    private function coachWithClient(): array
    {
        $coach = Coach::factory()->create();
        $coach->user->assignRole(Role::COACH);
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        return [$coach, $client];
    }

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

    public function test_coach_creates_form_and_assigns_clients(): void
    {
        [$coach, $client] = $this->coachWithClient();

        $response = $this->actingAsCoach($coach)->postJson('/api/v1/coaches/me/checkin-forms', [
            'title' => 'Weekly check',
            'frequency' => 'weekly',
            'sendTime' => '19:00',
            'questions' => [
                ['id' => 'q1', 'text' => 'Peso?', 'type' => 'number', 'required' => true, 'order' => 1],
            ],
            'clientIds' => [$client->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'Weekly check');
        $form = CheckinForm::first();
        $this->assertTrue($form->clients()->where('clients.id', $client->id)->exists());
    }

    public function test_index_lists_only_own_forms(): void
    {
        [$coach] = $this->coachWithClient();
        CheckinForm::factory()->for($coach)->count(2)->create();
        CheckinForm::factory()->for(Coach::factory()->create())->create();

        $this->actingAsCoach($coach)->getJson('/api/v1/coaches/me/checkin-forms')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_cannot_update_another_coachs_form(): void
    {
        [$coach] = $this->coachWithClient();
        $foreign = CheckinForm::factory()->for(Coach::factory()->create())->create();

        $this->actingAsCoach($coach)->putJson("/api/v1/coaches/me/checkin-forms/{$foreign->id}", [
            'title' => 'Hacked', 'frequency' => 'daily', 'sendTime' => '08:00',
            'questions' => [['id' => 'q1', 'text' => 'x', 'type' => 'text', 'required' => false, 'order' => 1]],
        ])->assertForbidden();
    }

    public function test_assign_rejects_client_not_belonging_to_coach(): void
    {
        [$coach] = $this->coachWithClient();
        $form = CheckinForm::factory()->for($coach)->create();
        $stranger = Client::factory()->create();

        $this->actingAsCoach($coach)->postJson("/api/v1/coaches/me/checkin-forms/{$form->id}/assign", [
            'clientIds' => [$stranger->id],
        ])->assertForbidden();
    }
}
