<?php

namespace App\Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coach extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // user_id, slug, is_verified, stripe_connect_id, stripe_onboarding_completed
        // sono esclusi intenzionalmente: assegnati esplicitamente dalle Action,
        // mai tramite mass assignment da input utente.

        // Profilo pubblico
        'bio',
        'description',
        'website_url',
        'specializations',
        'certifications',
        'social_links',
        'years_of_experience',

        // Contatti e sede
        'phone',
        'address',
        'city',
        'province',

        // Tariffe e capacità
        'hourly_rate',
        'max_clients',

        // Dati fiscali
        'ragione_sociale',
        'p_iva',
        'codice_fiscale',
        'tax_regime',
        'sdi_code',
        'pec',

        // Visibilità
        'is_visible',
    ];

    protected function casts(): array
    {
        return [
            'specializations'             => 'array',
            'certifications'              => 'array',
            'social_links'                => 'array',
            'years_of_experience'         => 'integer',
            'hourly_rate'                 => 'decimal:2',
            'max_clients'                 => 'integer',
            'is_verified'                 => 'boolean',
            'is_visible'                  => 'boolean',
            'stripe_onboarding_completed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(CoachInvitation::class);
    }

    public function pendingInvitations(): HasMany
    {
        return $this->hasMany(CoachInvitation::class)->where('status', 'pending');
    }
}
