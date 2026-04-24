<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\CoachContactRequest;
use Illuminate\Validation\ValidationException;

class DeclineContactRequestAction
{
    public function handle(CoachContactRequest $contactRequest): CoachContactRequest
    {
        if (! $contactRequest->isPending()) {
            throw ValidationException::withMessages([
                'action' => ['Questa richiesta è già stata elaborata.'],
            ]);
        }

        $contactRequest->update(['status' => 'declined']);

        return $contactRequest;
    }
}
