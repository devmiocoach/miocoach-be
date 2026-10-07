<?php

namespace Modules\Auth\Actions;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Actions\CreateCoachProfileAction;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterCoachAction
{
    public function __construct(
        private readonly CreateCoachProfileAction $createCoachProfile,
    ) {}

    public function handle(array $data): void
    {
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole(Role::COACH->value);

            $this->createCoachProfile->handle($user, $data);

            return $user;
        });

        event(new Registered($user));
    }
}
