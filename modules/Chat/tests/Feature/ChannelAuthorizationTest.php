<?php

namespace Modules\Chat\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Tests\Support\LocalAuthBroadcaster;
use Tests\TestCase;

class ChannelAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The suite-wide `log` broadcaster does not sign private-channel auth
        // responses, so an authorized user would get no 200. Swap only this test to a
        // deterministic test broadcaster that authorizes via the channel callbacks and
        // signs statically, with no network. forgetDrivers() defeats the manager's
        // per-connection memoization so the swap actually takes effect.
        Broadcast::extend('local-auth', fn () => new LocalAuthBroadcaster);
        config([
            'broadcasting.default' => 'local-auth',
            'broadcasting.connections.local-auth' => ['driver' => 'local-auth'],
        ]);
        Broadcast::forgetDrivers();

        // Channel callbacks are registered on whichever driver was default at boot.
        // Re-load the module's channel definitions so they bind to the freshly
        // resolved local-auth driver (DRY — reuses the real authorization callback).
        require module_path('Chat', 'routes/channels.php');
    }

    public function test_participant_can_authorize_channel(): void
    {
        $conversation = Conversation::factory()->create([
            'coach_id' => Coach::factory()->create()->id,
            'client_id' => Client::factory()->create()->id,
        ]);

        $response = $this->actingAs($conversation->coach->user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.1234',
                'channel_name' => 'private-chat.conversation.'.$conversation->id,
            ]);

        $response->assertOk();
    }

    public function test_outsider_is_denied(): void
    {
        $conversation = Conversation::factory()->create([
            'coach_id' => Coach::factory()->create()->id,
            'client_id' => Client::factory()->create()->id,
        ]);
        $outsider = Coach::factory()->create()->user;

        $response = $this->actingAs($outsider)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.1234',
                'channel_name' => 'private-chat.conversation.'.$conversation->id,
            ]);

        $response->assertForbidden();
    }
}
