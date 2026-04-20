<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Users\Actions\CreateClientAction;
use App\Modules\Users\Models\CoachInvitation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterViaInviteAction
{
    public function __construct(
        private readonly CreateClientAction $createClient,
    ) {}

    public function handle(string $token, array $data): void
    {
        // Transazione limitata alle sole scritture DB — l'evento viene sparato dopo
        // il commit per evitare che job accodati vengano processati su dati non ancora persistiti.
        $user = DB::transaction(function () use ($token, $data) {
            $invitation = CoachInvitation::where('token', $token)->lockForUpdate()->first();

            if (! $invitation || ! $invitation->isPending()) {
                throw ValidationException::withMessages([
                    'token' => ['Il link di invito non è valido o è scaduto.'],
                ]);
            }

            if (User::where('email', $invitation->email)->exists()) {
                throw ValidationException::withMessages([
                    'token' => ['Esiste già un account associato a questo invito.'],
                ]);
            }

            $user = User::create([
                'name'     => $data['name'],
                'email'    => $invitation->email,
                'password' => Hash::make($data['password']),
            ]);

            // CreateClientAction gestisce internamente l'assignRole('client')
            $this->createClient->handle($user, $invitation->coach, $data);

            $invitation->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);

            return $user;
        });

        event(new Registered($user)); // triggers email verification notification
    }
}
