<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

class DisableTwoFactorAction
{
    public function __construct(
        private readonly DisableTwoFactorAuthentication $fortifyAction,
    ) {}

    public function handle(User $user): void
    {
        ($this->fortifyAction)($user);
    }
}
