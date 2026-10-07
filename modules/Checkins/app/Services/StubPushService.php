<?php

namespace Modules\Checkins\Services;

use Illuminate\Support\Facades\Log;
use Modules\Checkins\Services\Contracts\PushServiceContract;

class StubPushService implements PushServiceContract
{
    public function send(object $notifiable, string $title, string $body): void
    {
        Log::info('[Checkins Stub] PushService::send', [
            'notifiable' => $notifiable::class,
            'id' => $notifiable->getKey(),
            'title' => $title,
            'body' => $body,
        ]);
    }
}
