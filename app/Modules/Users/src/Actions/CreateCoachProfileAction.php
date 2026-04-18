<?php

namespace App\Modules\Users\Actions;

use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Support\Str;

class CreateCoachProfileAction
{
    public function handle(User $user, array $data = []): Coach
    {
        return Coach::create([
            'user_id' => $user->id,
            'slug'    => Str::slug($user->name) . '-' . $user->id,

            // Profilo pubblico
            'bio'                 => $data['bio'] ?? null,
            'description'         => $data['description'] ?? null,
            'website_url'         => $data['website_url'] ?? null,
            'specializations'     => $data['specializations'] ?? null,
            'certifications'      => $data['certifications'] ?? null,
            'social_links'        => $data['social_links'] ?? null,
            'years_of_experience' => $data['years_of_experience'] ?? null,

            // Contatti e sede
            'phone'    => $data['phone'] ?? null,
            'address'  => $data['address'] ?? null,
            'city'     => $data['city'] ?? null,
            'province' => $data['province'] ?? null,

            // Tariffe e capacità
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'max_clients' => $data['max_clients'] ?? null,

            // Dati fiscali
            'ragione_sociale' => $data['ragione_sociale'] ?? null,
            'p_iva'           => $data['p_iva'] ?? null,
            'codice_fiscale'  => $data['codice_fiscale'] ?? null,
            'tax_regime'      => $data['tax_regime'] ?? null,
            'sdi_code'        => $data['sdi_code'] ?? null,
            'pec'             => $data['pec'] ?? null,
        ]);
    }
}
