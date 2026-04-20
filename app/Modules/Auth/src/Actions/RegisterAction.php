<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    public function handle(array $data, string $role = 'client'): void
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($role);

        event(new Registered($user)); // triggers email verification notification
    }
}
