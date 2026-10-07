<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;

class AddClientTagAction
{
    public function handle(Client $client, string $tag): Client
    {
        $tags = $client->tags ?? [];

        if (! in_array($tag, $tags, true)) {
            $tags[] = $tag;
            $client->update(['tags' => array_values($tags)]);
        }

        return $client->fresh();
    }
}
