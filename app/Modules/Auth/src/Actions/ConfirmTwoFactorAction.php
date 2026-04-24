<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;

class ConfirmTwoFactorAction
{
    public function __construct(
        private readonly ConfirmTwoFactorAuthentication $fortifyAction,
    ) {}

    public function handle(User $user, string $code): void
    {
        ($this->fortifyAction)($user, $code);
    }
}
