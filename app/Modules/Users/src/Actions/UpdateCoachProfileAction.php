<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Coach;

class UpdateCoachProfileAction
{
    public function handle(Coach $coach, array $data): Coach
    {
        // $data proviene da FormRequest::validated() con regole 'sometimes':
        // contiene solo le chiavi presenti nella richiesta, quindi i campi
        // assenti non vengono toccati; i campi inviati con null vengono azzerati.
        $coach->update($data);

        return $coach->fresh();
    }
}
