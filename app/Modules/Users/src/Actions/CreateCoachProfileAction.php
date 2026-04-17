<?php

namespace App\Modules\Users\Actions;

use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Support\Str;

class CreateCoachProfileAction
{
    public function handle(User $user, array $data = []): Coach
    {
        $user->assignRole('coach');

        return Coach::create([
            'user_id'        => $user->id,
            'slug'           => $data['slug'] ?? Str::slug($user->name) . '-' . $user->id,
            'bio'            => $data['bio'] ?? null,
            'description'    => $data['description'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'hourly_rate'    => $data['hourly_rate'] ?? null,
            'city'           => $data['city'] ?? null,
        ]);
    }
}
