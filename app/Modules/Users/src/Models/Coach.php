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
        'tagline',
        'description',
        'website_url',
        'intro_video_url',
        'specializations',
        'languages',
        'social_links',
        'years_of_experience',
        'mode',

        // Contatti e sede
        'phone',
        'address',
        'city',
        'province',
        'lat',
        'lng',

        // Tariffe e capacità
        'hourly_rate',
        'price_per_session',
        'cancellation_window_hours',
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
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'specializations'             => 'array',
            'languages'                   => 'array',
            'social_links'                => 'array',
            'years_of_experience'         => 'integer',
            'hourly_rate'                 => 'decimal:2',
            'price_per_session'           => 'decimal:2',
            'cancellation_window_hours'   => 'integer',
            'max_clients'                 => 'integer',
            'lat'                         => 'float',
            'lng'                         => 'float',
            'is_verified'                 => 'boolean',
            'is_visible'                  => 'boolean',
            'is_published'                => 'boolean',
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

    public function availabilities(): HasMany
    {
        return $this->hasMany(CoachAvailability::class);
    }

    public function certificationRecords(): HasMany
    {
        return $this->hasMany(Certification::class);
    }

    public function verifiedCertifications(): HasMany
    {
        return $this->hasMany(Certification::class)->whereNotNull('verified_at');
    }
}
