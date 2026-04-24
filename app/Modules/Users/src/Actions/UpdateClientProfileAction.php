<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;

class UpdateClientProfileAction
{
    public function handle(Client $client, array $data): Client
    {
        $client->update($data);

        return $client->fresh();
    }
}
