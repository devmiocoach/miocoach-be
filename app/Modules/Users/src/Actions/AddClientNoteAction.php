<?php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientNote;
use App\Modules\Users\Models\Coach;

class AddClientNoteAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Client $client, Coach $coach, string $content): ClientNote
    {
        $note            = new ClientNote();
        $note->client_id = $client->id;
        $note->coach_id  = $coach->id;
        $note->content   = $content;
        $note->save();

        $this->audit->log('CLIENT_NOTE_ADDED', $coach->user_id ?? $note->coach_id, [
            'client_id' => $note->client_id,
            'note_id'   => $note->id,
        ]);

        return $note;
    }
}
