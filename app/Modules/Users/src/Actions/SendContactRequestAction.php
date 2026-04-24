<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachContactRequest;
use App\Modules\Users\Notifications\CoachContactRequestNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SendContactRequestAction
{
    public function handle(Coach $coach, Client $client, ?string $message): CoachContactRequest
    {
        if (! $coach->is_published) {
            abort(404, 'Coach not found.');
        }

        $contactRequest = null;

        try {
            DB::transaction(function () use ($coach, $client, $message, &$contactRequest) {
                $contactRequest            = new CoachContactRequest();
                $contactRequest->coach_id  = $coach->id;
                $contactRequest->client_id = $client->id;
                $contactRequest->message   = $message;
                $contactRequest->status    = 'pending';
                $contactRequest->save();

                Notification::route('mail', $coach->user->email)
                    ->notify(new CoachContactRequestNotification($client, $coach, $message));
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'coach_id' => ['Hai già una richiesta in corso con questo coach.'],
            ]);
        }

        return $contactRequest;
    }
}
