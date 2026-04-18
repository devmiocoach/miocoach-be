<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    public function handle(array $data, string $role = 'client', string $deviceType = 'web', ?string $deviceName = null): array
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($role);

        event(new Registered($user));

        if ($deviceType === 'mobile') {
            $token = $user->createToken($deviceName ?? 'mobile-device');
            return [
                'access_token' => $token->plainTextToken,
                'token_type'   => 'Bearer',
                'user'         => ['id' => $user->id, 'role' => $user->getRoleNames()->first()],
            ];
        }

        auth()->login($user);
        session()->regenerate();

        return [
            'user' => ['id' => $user->id, 'role' => $user->getRoleNames()->first()],
        ];
    }
}
