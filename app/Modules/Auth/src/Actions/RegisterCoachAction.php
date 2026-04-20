<?php

namespace App\Modules\Auth\Actions;

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
        // Transazione limitata alle sole scritture DB — l'evento viene sparato dopo
        // il commit per evitare che job accodati (es. email verifica) vengano
        // processati prima che i dati siano effettivamente persistiti.
        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => mb_strtolower($data['email']),
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole('coach');

            $this->createCoachProfile->handle($user, $data);

            return $user;
        });

        event(new Registered($user)); // triggers email verification notification
    }
}
