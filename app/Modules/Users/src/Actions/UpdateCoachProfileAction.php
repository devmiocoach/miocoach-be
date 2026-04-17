<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Coach;

class UpdateCoachProfileAction
{
    public function handle(Coach $coach, array $data): Coach
    {
        $coach->update(array_filter([
            'bio'            => $data['bio'] ?? null,
            'description'    => $data['description'] ?? null,
            'specialization' => $data['specialization'] ?? null,
            'certifications' => $data['certifications'] ?? null,
            'hourly_rate'    => $data['hourly_rate'] ?? null,
            'city'           => $data['city'] ?? null,
            'is_visible'     => $data['is_visible'] ?? null,
            'social_links'   => $data['social_links'] ?? null,
        ], fn($v) => $v !== null));

        return $coach->fresh();
    }
}
