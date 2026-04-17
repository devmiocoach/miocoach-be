<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;

class RevokeAllTokensAction
{
    public function handle(User $user): void
    {
        $user->tokens()->delete();
    }
}
