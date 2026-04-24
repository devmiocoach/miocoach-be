<?php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\CoachContactRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptContactRequestAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(CoachContactRequest $contactRequest): CoachContactRequest
    {
        if (! $contactRequest->isPending()) {
            throw ValidationException::withMessages([
                'action' => ['Questa richiesta è già stata elaborata.'],
            ]);
        }

        return DB::transaction(function () use ($contactRequest) {
            $client = $contactRequest->client;
            $coach  = $contactRequest->coach;

            $client->coach_id  = $coach->id;
            $client->joined_at = now();
            $client->save();

            $contactRequest->update(['status' => 'accepted']);

            $this->audit->log('CLIENT_CREATED', $coach->user_id, [
                'client_id' => $client->id,
                'via'       => 'contact_request',
            ]);

            return $contactRequest;
        });
    }
}
