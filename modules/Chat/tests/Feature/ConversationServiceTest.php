<?php

namespace Modules\Chat\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Policies\ConversationPolicy;
use Modules\Chat\Services\ConversationService;
use Tests\TestCase;

class ConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_or_create_is_idempotent(): void
    {
        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $service = app(ConversationService::class);

        $a = $service->findOrCreate($coach, $client);
        $b = $service->findOrCreate($coach, $client);

        $this->assertEquals($a->id, $b->id);
        $this->assertEquals(1, Conversation::count());
    }

    public function test_policy_allows_only_participants(): void
    {
        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $outsider = Coach::factory()->create();
        $conversation = Conversation::factory()->create([
            'coach_id' => $coach->id,
            'client_id' => $client->id,
        ]);
        $policy = new ConversationPolicy;

        $this->assertTrue($policy->participate($coach->user, $conversation));
        $this->assertTrue($policy->participate($client->user, $conversation));
        $this->assertFalse($policy->participate($outsider->user, $conversation));
    }
}
