<?php

namespace Modules\Auth\Actions;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Actions\AttachAthleteToCoachAction;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachInvitation;
use App\Services\InviteTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterViaInviteAction
{
    public function __construct(
        private readonly InviteTokenService $inviteTokenService,
        private readonly AttachAthleteToCoachAction $attachAthlete,
    ) {}

    public function handle(string $token, array $data): User
    {
        try {
            $payload = $this->inviteTokenService->decode($token);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages([
                'token' => ['Il link di invito non è valido o è scaduto.'],
            ]);
        }

        return DB::transaction(function () use ($token, $payload, $data) {
            $invitation = CoachInvitation::where('token', $token)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $invitation) {
                throw ValidationException::withMessages([
                    'token' => ['Il link di invito non è valido o è scaduto.'],
                ]);
            }

            if (User::where('email', $payload->email)->exists()) {
                throw ValidationException::withMessages([
                    'token' => ['Esiste già un account associato a questo invito.'],
                ]);
            }

            $user = User::create([
                'name' => $data['firstName'].' '.$data['lastName'],
                'email' => $payload->email,
                'password' => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);

            $user->assignRole(Role::CLIENT->value);

            $client = new Client;
            $client->user_id = $user->id;
            $client->birth_date = $data['dateOfBirth'] ?? null;
            $client->gender = $data['gender'] ?? null;
            $client->height_cm = $data['heightCm'] ?? null;
            $client->objective = $data['objective'] ?? null;
            $client->fitness_level = $data['fitnessLevel'] ?? null;
            $client->joined_at = now();
            $client->save();

            $coach = Coach::find($payload->coach_id);

            if (! $coach) {
                throw ValidationException::withMessages([
                    'token' => ['Il profilo coach non esiste più.'],
                ]);
            }

            $this->attachAthlete->handle($coach, $client, 'active');

            $invitation->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            return $user;
        });
    }
}
