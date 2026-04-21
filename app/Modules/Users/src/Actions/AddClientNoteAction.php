<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientNote;
use App\Modules\Users\Models\Coach;

class AddClientNoteAction
{
    public function handle(Client $client, Coach $coach, string $content): ClientNote
    {
        $note            = new ClientNote();
        $note->client_id = $client->id;
        $note->coach_id  = $coach->id;
        $note->content   = $content;
        $note->save();

        return $note;
    }
}
