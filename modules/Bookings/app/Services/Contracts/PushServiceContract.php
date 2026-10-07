<?php

namespace Modules\Bookings\Services\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PushServiceContract
{
    public function send(Model $user, string $title, string $body, array $data = []): void;
}
