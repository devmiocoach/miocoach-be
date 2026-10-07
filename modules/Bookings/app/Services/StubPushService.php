<?php

namespace Modules\Bookings\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\Bookings\Services\Contracts\PushServiceContract;

class StubPushService implements PushServiceContract
{
    public function send(Model $user, string $title, string $body, array $data = []): void
    {
        Log::info('[Bookings Stub] PushService::send', [
            'user_id' => $user->getKey(),
            'title' => $title,
            'body' => $body,
        ]);
    }
}
