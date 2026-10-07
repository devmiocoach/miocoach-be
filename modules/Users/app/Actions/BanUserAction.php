<?php

namespace App\Modules\Users\Actions;

use App\Models\User;

class BanUserAction
{
    public function handle(User $user): void
    {
        // Revoca tutti i token e soft-delete
        $user->tokens()->delete();
        $user->delete();
    }
}
