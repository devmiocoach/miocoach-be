<?php

namespace Modules\Auth\Actions;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    public function handle(array $data, Role $role = Role::CLIENT): void
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($role->value);

        event(new Registered($user));
    }
}
