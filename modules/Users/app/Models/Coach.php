<?php

namespace App\Modules\Users\Models;

use App\Models\User;
use App\Modules\Users\Database\Factories\CoachFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Coach extends Model implements HasMedia
{
    /** @use HasFactory<CoachFactory> */
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected static function newFactory(): CoachFactory
    {
        return CoachFactory::new();
    }

    protected static function booted(): void
    {
        // DB cascade fires only on hard delete. On soft delete, clean up manually
        // to prevent orphaned pivot rows and pending invitations on deleted coaches.
        static::deleting(function (Coach $coach) {
            if ($coach->isForceDeleting()) {
                return;
            }

            $coach->athletes()->detach();
            $coach->invitations()->where('status', 'pending')->update(['status' => 'revoked']);
            $coach->contactRequests()->where('status', 'pending')->update(['status' => 'cancelled']);
            $coach->availabilities()->delete();
        });
    }

    protected $fillable = [
        // user_id, slug, is_verified, stripe_connect_id, stripe_onboarding_completed
        // sono esclusi intenzionalmente: assegnati esplicitamente dalle Action,
        // mai tramite mass assignment da input utente.

        // Profilo pubblico
        'display_name',
        'gender',
        'profile_photo_url',
        'cover_photo_url',
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
        'service_radius_km',
        'available_at_home',
        'partner_gyms',

        // Tariffe e capacità
        'hourly_rate',
        'price_per_session',
        'cancellation_window_hours',
        'session_duration_default',
        'single_session_price_cents',
        'monthly_subscription_cents',
        'quarterly_subscription_cents',
        'max_clients',

        // Policy
        'no_show_fee_cents',
        'late_tolerance_minutes',
        'free_first_session',
        'welcome_discount_enabled',
        'welcome_discount_percent',
        'custom_terms',
        'accepts_free_consultation',
        'accepts_direct_message',
        'accepts_direct_booking',

        // Gallery
        'gallery_photos',
        'intro_video_storage_path',

        // Dati fiscali
        'ragione_sociale',
        'p_iva',
        'codice_fiscale',
        'tax_regime',
        'sdi_code',
        'pec',
        'has_insurance',
        'insurance_company',
        'insurance_expiry',
        'insurance_file_url',
        'insurance_file_storage_path',

        // Visibilità e onboarding
        'is_visible',
        'is_published',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'specializations' => 'array',
            'languages' => 'array',
            // `certifications` is a json column like the ones above; without
            // this cast CoachResource handed clients a raw JSON string, and
            // CreateCoachProfileAction wrote an array straight into it.
            'certifications' => 'array',
            'social_links' => 'array',
            'partner_gyms' => 'array',
            'gallery_photos' => 'array',
            'years_of_experience' => 'integer',
            'hourly_rate' => 'decimal:2',
            'price_per_session' => 'decimal:2',
            'cancellation_window_hours' => 'integer',
            'session_duration_default' => 'string',
            'single_session_price_cents' => 'integer',
            'monthly_subscription_cents' => 'integer',
            'quarterly_subscription_cents' => 'integer',
            'no_show_fee_cents' => 'integer',
            'late_tolerance_minutes' => 'integer',
            'welcome_discount_percent' => 'integer',
            'service_radius_km' => 'integer',
            'max_clients' => 'integer',
            'lat' => 'float',
            'lng' => 'float',
            'is_verified' => 'boolean',
            'available_at_home' => 'boolean',
            'free_first_session' => 'boolean',
            'welcome_discount_enabled' => 'boolean',
            'accepts_free_consultation' => 'boolean',
            'accepts_direct_message' => 'boolean',
            'accepts_direct_booking' => 'boolean',
            'has_insurance' => 'boolean',
            'insurance_expiry' => 'date',
            'is_visible' => 'boolean',
            'is_published' => 'boolean',
            'stripe_onboarding_completed' => 'boolean',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /** @return array{free_consultation: bool, direct_message: bool, direct_booking: bool} */
    public function contactOptions(): array
    {
        return [
            'free_consultation' => (bool) $this->accepts_free_consultation,
            'direct_message' => (bool) $this->accepts_direct_message,
            'direct_booking' => (bool) $this->accepts_direct_booking,
        ];
    }

    public function registerMediaCollections(): void
    {
        $twoMb = 2 * 1024 * 1024;

        $this->addMediaCollection('profile_photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->acceptsFile(fn (File $file) => $file->size <= $twoMb);

        $this->addMediaCollection('cover_photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->acceptsFile(fn (File $file) => $file->size <= $twoMb);

        $this->addMediaCollection('gallery')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->acceptsFile(fn (File $file) => $file->size <= $twoMb);

        $this->addMediaCollection('intro_video')
            ->singleFile()
            ->acceptsMimeTypes(['video/mp4', 'video/quicktime']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('profile_photo', 'cover_photo', 'gallery')
            ->width(400)
            ->height(400)
            ->optimize();

        $this->addMediaConversion('medium')
            ->performOnCollections('profile_photo', 'cover_photo', 'gallery')
            ->width(800)
            ->optimize();

        $this->addMediaConversion('large')
            ->performOnCollections('cover_photo', 'gallery')
            ->width(1200)
            ->optimize();
    }

    public function hasCompletedOnboarding(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function athletes(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'athlete_coach', 'coach_id', 'athlete_id')
            ->withPivot(['status', 'started_at', 'ended_at'])
            ->withTimestamps();
    }

    public function activeAthletes(): BelongsToMany
    {
        return $this->athletes()->wherePivot('status', 'active');
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

    public function contactRequests(): HasMany
    {
        return $this->hasMany(CoachContactRequest::class);
    }

    public function pendingContactRequests(): HasMany
    {
        return $this->hasMany(CoachContactRequest::class)->where('status', 'pending');
    }

    public function specializationRecords(): BelongsToMany
    {
        return $this->belongsToMany(Specialization::class, 'coach_specialization');
    }

    /** @return HasMany<CoachActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(CoachActivity::class)->latest('created_at');
    }

    public function packages(): HasMany
    {
        return $this->hasMany(CoachPackage::class)->orderBy('sort_order');
    }

    public function activePackages(): HasMany
    {
        return $this->hasMany(CoachPackage::class)->where('is_active', true)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function publishedReviews(): HasMany
    {
        return $this->hasMany(Review::class)->whereNotNull('published_at');
    }
}
