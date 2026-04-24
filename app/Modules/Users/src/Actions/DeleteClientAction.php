<?php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;

class DeleteClientAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Client $client, Coach $coach): void
    {
        $clientId = $client->id;
        $client->delete();
        $this->audit->log('CLIENT_DELETED', $coach->user_id, ['client_id' => $clientId]);
    }
}
