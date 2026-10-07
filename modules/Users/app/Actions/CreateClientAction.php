<?php

namespace App\Modules\Users\Actions;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateClientAction
{
    public function __construct(
        private readonly SendInvitationAction $invite,
        private readonly AttachAthleteToCoachAction $attach,
        private readonly AuditLogService $audit,
    ) {}

    public function handle(Coach $coach, array $data): array
    {
        $email = mb_strtolower($data['email']);
        $firstName = $data['first_name'];
        $lastName = $data['last_name'];
        $fullName = trim("{$firstName} {$lastName}");

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $client = DB::transaction(function () use ($existingUser, $coach, $fullName, $data) {
                $existingClient = $existingUser->client;

                if ($existingClient && $coach->athletes()->where('athlete_id', $existingClient->id)->exists()) {
                    throw ValidationException::withMessages([
                        'email' => ['Questo utente è già un tuo cliente.'],
                    ]);
                }

                if (! $existingUser->name) {
                    $existingUser->update(['name' => $fullName]);
                }

                $existingUser->assignRole(Role::CLIENT->value);

                if (! $existingClient) {
                    $existingClient = new Client;
                    $existingClient->user_id = $existingUser->id;
                    $existingClient->joined_at = now();
                    $existingClient->fill(array_intersect_key($data, array_flip([
                        'tags', 'phone', 'birth_date', 'gender', 'height_cm', 'weight_kg',
                    ])));
                    $existingClient->save();
                }

                $this->attach->handle($coach, $existingClient, 'active');

                return $existingClient;
            });

            $this->audit->log('CLIENT_CREATED', $coach->user_id, ['client_id' => $client->id]);

            return ['status' => 'created', 'client' => $client->load('user')];
        }

        $this->invite->handle($coach, $email);
        $this->audit->log('CLIENT_INVITED', $coach->user_id, ['email' => $email]);

        return ['status' => 'invited'];
    }
}
