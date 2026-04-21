<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;

class RemoveClientTagAction
{
    public function handle(Client $client, string $tag): void
    {
        $tags = array_values(array_filter($client->tags ?? [], fn ($t) => $t !== $tag));
        $client->update(['tags' => $tags]);
    }
}
