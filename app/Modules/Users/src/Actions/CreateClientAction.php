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

        // I campi privilegiati (user_id, coach_id, joined_at) sono assegnati
        // direttamente perché esclusi da $fillable per protezione da mass assignment.
        $client = new Client();
        $client->user_id  = $user->id;
        $client->coach_id = $coach->id;
        $client->joined_at = now();
        $client->fill([
            'anamnesi'   => $data['anamnesi'] ?? null,
            'goals'      => $data['goals'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender'     => $data['gender'] ?? null,
            'height_cm'  => $data['height_cm'] ?? null,
            'weight_kg'  => $data['weight_kg'] ?? null,
        ]);
        $client->save();

        return $client;
    }
}
