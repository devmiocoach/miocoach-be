<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

class EnableTwoFactorAction
{
    public function __construct(
        private readonly EnableTwoFactorAuthentication $fortifyAction,
    ) {}

    public function handle(User $user): void
    {
        ($this->fortifyAction)($user);
    }
}
