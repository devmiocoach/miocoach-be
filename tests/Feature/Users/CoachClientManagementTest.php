<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachClientManagementTest extends TestCase
{
    use WithCoachUser;

    public function test_client_model_has_new_fields(): void
    {
        [$user, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $client->tags  = ['premium', 'online'];
        $client->phone = '+39 333 1234567';
        $client->save();

        $fresh = $client->fresh();
        $this->assertEquals(['premium', 'online'], $fresh->tags);
        $this->assertEquals('+39 333 1234567', $fresh->phone);
    }
}
