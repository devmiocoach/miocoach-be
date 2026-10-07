<?php

namespace Modules\Auth\Listeners;

use App\Enums\Role;
use App\Enums\UserType;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

class AssignUserRole
{
    public function handle(Registered $event): void
    {
        /** @var User $user */
        $user = $event->user;

        if ($user->roles->isNotEmpty()) {
            return;
        }

        $role = match ($user->type) {
            UserType::COACH => Role::COACH,
            UserType::ATLETA => Role::CLIENT,
            default => Role::USER,
        };

        $user->syncRoles([$role->value]);
    }
}
