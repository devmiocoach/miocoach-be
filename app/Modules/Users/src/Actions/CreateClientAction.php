<?php

namespace App\Modules\Users\Actions;

use App\Models\User;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class CreateClientAction
{
    public function __construct(
        private readonly SendInvitationAction $invite,
        private readonly AuditLogService $audit,
    ) {}

    public function handle(Coach $coach, array $data): array
    {
        $email     = mb_strtolower($data['email']);
        $firstName = $data['first_name'];
        $lastName  = $data['last_name'];
        $fullName  = trim("{$firstName} {$lastName}");

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $client = DB::transaction(function () use ($existingUser, $coach, $fullName, $data) {
                if ($existingUser->client?->coach_id === $coach->id) {
                    throw ValidationException::withMessages([
                        'email' => ['Questo utente è già un tuo cliente.'],
                    ]);
                }

                if (! $existingUser->name) {
                    $existingUser->update(['name' => $fullName]);
                }

                $clientRole = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
                $existingUser->assignRole($clientRole);

                $client = $existingUser->client;
                if ($client && $client->coach_id === null) {
                    $client->coach_id  = $coach->id;
                    $client->joined_at = now();
                    $client->fill(array_intersect_key($data, array_flip([
                        'tags', 'phone', 'birth_date', 'gender', 'height_cm', 'weight_kg',
                    ])));
                    $client->save();
                } else {
                    $client            = new Client();
                    $client->user_id   = $existingUser->id;
                    $client->coach_id  = $coach->id;
                    $client->joined_at = now();
                    $client->fill(array_intersect_key($data, array_flip([
                        'tags', 'phone', 'birth_date', 'gender', 'height_cm', 'weight_kg',
                    ])));
                    $client->save();
                }

                return $client;
            });

            $this->audit->log('CLIENT_CREATED', $coach->user_id, ['client_id' => $client->id]);

            return ['status' => 'created', 'client' => $client->load('user')];
        }

        $this->invite->handle($coach, $email);
        $this->audit->log('CLIENT_INVITED', $coach->user_id, ['email' => $email]);

        return ['status' => 'invited'];
    }
}
