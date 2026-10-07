<?php

namespace Modules\Chat\Tests\Support;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Test-only broadcaster.
 *
 * Private-channel authorization requires the driver to (a) run the channel
 * authorization callbacks and reject non-participants with a 403, and (b) return
 * a signed auth response for authorized users. The `log` driver does not sign, and
 * `reverb`/`pusher` would make real HTTP calls whenever a `ShouldBroadcast` event is
 * dispatched elsewhere in the suite. This broadcaster does the authorization exactly
 * like Pusher but signs with a static payload and no-ops on broadcast — deterministic,
 * with no network access.
 */
class LocalAuthBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    public function auth($request)
    {
        $channelName = $this->normalizeChannelName($request->channel_name);

        if (empty($request->channel_name) ||
            ($this->isGuardedChannel($request->channel_name) &&
            ! $this->retrieveUser($request, $channelName))) {
            throw new AccessDeniedHttpException;
        }

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }

    /**
     * @param  mixed  $result
     * @return array<string, string>
     */
    public function validAuthenticationResponse($request, $result)
    {
        return ['auth' => 'local-auth:signature'];
    }

    /**
     * @param  array<int, string>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        // no-op: tests assert dispatch via Event::fake, never a live socket
    }
}
