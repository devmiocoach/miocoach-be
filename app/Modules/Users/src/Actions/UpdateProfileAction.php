<?php

namespace App\Modules\Users\Actions;

use App\Models\User;

class UpdateProfileAction
{
    public function handle(User $user, array $data): User
    {
        $user->update(array_filter([
            'name'   => $data['name'] ?? null,
            'phone'  => $data['phone'] ?? null,
            'locale' => $data['locale'] ?? null,
        ], fn($v) => $v !== null));

        return $user->fresh();
    }
}
