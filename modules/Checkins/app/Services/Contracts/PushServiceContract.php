<?php

namespace Modules\Checkins\Services\Contracts;

interface PushServiceContract
{
    public function send(object $notifiable, string $title, string $body): void;
}
