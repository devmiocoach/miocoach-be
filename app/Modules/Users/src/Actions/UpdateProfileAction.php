<?php

namespace App\Modules\Users\Actions;

use App\Models\User;

class UpdateProfileAction
{
    public function handle(User $user, array $data): User
    {
        // $data proviene da FormRequest::validated() con regole 'sometimes':
        // contiene solo le chiavi presenti nella richiesta.
        // I campi inviati esplicitamente con null vengono azzerati (es. phone → null).
        $user->update($data);

        return $user->fresh();
    }
}
