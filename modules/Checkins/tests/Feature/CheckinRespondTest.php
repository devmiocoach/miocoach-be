<?php

namespace Modules\Checkins\Tests\Feature;

use App\Enums\Role;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Auth\Http\Middleware\JwtAuthenticate;
use Modules\Auth\Http\Middleware\RequireVerified;
use Modules\Checkins\Models\CheckinForm;
use Modules\Checkins\Notifications\ClientSubmittedCheckinNotification;
use Tests\TestCase;

class CheckinRespondTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsClient(Client $client): self
    {
        $client->user->assignRole(Role::CLIENT);

        $this->withoutMiddleware([JwtAuthenticate::class, RequireVerified::class])
            ->actingAs($client->user);

        return $this;
    }

    public function test_assigned_client_can_respond_and_coach_is_notified(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $form = CheckinForm::factory()->for($coach)->create([
            'questions' => [
                ['id' => 'q1', 'text' => 'Peso?', 'type' => 'number', 'required' => true, 'order' => 1],
            ],
        ]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        $this->actingAsClient($client)->postJson("/api/v1/checkin-forms/{$form->id}/respond", [
            'answers' => [['questionId' => 'q1', 'value' => 80]],
        ])->assertCreated();

        $this->assertDatabaseHas('checkin_responses', ['checkin_form_id' => $form->id, 'client_id' => $client->id]);
        Notification::assertSentTo($coach->user, ClientSubmittedCheckinNotification::class);
    }

    public function test_coach_notification_is_persisted_to_database(): void
    {
        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $form = CheckinForm::factory()->for($coach)->create([
            'questions' => [
                ['id' => 'q1', 'text' => 'Peso?', 'type' => 'number', 'required' => true, 'order' => 1],
            ],
        ]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        $this->actingAsClient($client)->postJson("/api/v1/checkin-forms/{$form->id}/respond", [
            'answers' => [['questionId' => 'q1', 'value' => 80]],
        ])->assertCreated();

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $coach->user->id,
            'notifiable_type' => $coach->user->getMorphClass(),
            'type' => ClientSubmittedCheckinNotification::class,
        ]);
    }

    public function test_unassigned_client_cannot_respond(): void
    {
        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $form = CheckinForm::factory()->for($coach)->create();

        $this->actingAsClient($client)->postJson("/api/v1/checkin-forms/{$form->id}/respond", [
            'answers' => [['questionId' => 'q1', 'value' => 80]],
        ])->assertForbidden();
    }

    public function test_missing_required_answer_is_rejected(): void
    {
        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        $form = CheckinForm::factory()->for($coach)->create([
            'questions' => [['id' => 'q1', 'text' => 'Peso?', 'type' => 'number', 'required' => true, 'order' => 1]],
        ]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        $this->actingAsClient($client)->postJson("/api/v1/checkin-forms/{$form->id}/respond", [
            'answers' => [],
        ])->assertJsonValidationErrorFor('answers', 'error.fields');
    }
}
