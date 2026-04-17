<?php

namespace App\Modules\Users\Actions;

use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;

class CreateClientAction
{
    public function handle(User $user, Coach $coach, array $data = []): Client
    {
        $user->assignRole('client');

        return Client::create([
            'user_id'    => $user->id,
            'coach_id'   => $coach->id,
            'anamnesi'   => $data['anamnesi'] ?? null,
            'goals'      => $data['goals'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender'     => $data['gender'] ?? null,
            'height_cm'  => $data['height_cm'] ?? null,
            'weight_kg'  => $data['weight_kg'] ?? null,
            'joined_at'  => now(),
        ]);
    }
}
