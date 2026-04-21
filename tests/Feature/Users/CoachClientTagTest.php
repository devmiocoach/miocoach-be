<?php

namespace Tests\Feature\Users;

use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachClientTagTest extends TestCase
{
    use WithCoachUser;

    public function test_coach_can_add_tag_to_client(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach, [], ['tags' => ['existing']]);

        $response = $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/tags", [
                'tag' => 'premium',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tags.0', 'existing')
            ->assertJsonPath('data.tags.1', 'premium');
    }

    public function test_add_tag_is_idempotent(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach, [], ['tags' => ['premium']]);

        $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/tags", ['tag' => 'premium']);

        $this->assertCount(1, $client->fresh()->tags);
    }

    public function test_coach_can_remove_tag_from_client(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach, [], ['tags' => ['vip', 'online']]);

        $response = $this->actingAsCoach($coachUser)
            ->deleteJson("/api/v1/coaches/me/clients/{$client->id}/tags/vip");

        $response->assertNoContent();
        $this->assertEquals(['online'], array_values($client->fresh()->tags));
    }
}
