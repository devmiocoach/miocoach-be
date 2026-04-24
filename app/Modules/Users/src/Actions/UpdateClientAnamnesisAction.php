<?php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientAnamnesis;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Services\EncryptionService;

class UpdateClientAnamnesisAction
{
    public function __construct(
        private readonly EncryptionService $encryption,
        private readonly AuditLogService $audit,
    ) {}

    public function handle(Client $client, Coach $coach, string $content): void
    {
        $encrypted = $this->encryption->encrypt($content);

        ClientAnamnesis::updateOrCreate(
            ['client_id' => $client->id],
            [
                'content_encrypted' => $encrypted['ciphertext'],
                'iv'                => $encrypted['iv'],
                'tag'               => $encrypted['tag'],
            ]
        );

        $this->audit->log('ANAMNESIS_UPDATED', $coach->user_id, ['client_id' => $client->id]);
    }
}
