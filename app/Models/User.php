<?php

namespace App\Models;

use App\Enums\Role;
use App\Enums\UserType;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Modules\Auth\Models\RefreshToken;
use Modules\Auth\Models\TwoFactorBackupCode;
use Modules\Auth\Notifications\EmailVerificationNotification;
use Modules\Auth\Notifications\ResetPasswordNotification;
use Modules\Auth\Traits\Sociable;
use Modules\Billing\Traits\Billable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasMedia, MustVerifyEmail
{
    use Billable;
    use HasFactory;
    use HasRoles;
    use InteractsWithMedia;
    use Notifiable;
    use Sociable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar',
        'type',
        'email_verified_at',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'type' => UserType::class,
        ];
    }

    /**
     * Set the user's email address.
     */
    protected function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower($value);
    }

    /**
     * Register media collections for this model
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatars')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    /**
     * Get avatar with fallback to default
     */
    public function getAvatarAttribute(): string
    {
        // First priority: Media library uploaded avatar
        $mediaAvatar = $this->getFirstMediaUrl('avatars');
        if ($mediaAvatar) {
            return $mediaAvatar;
        }

        // Second priority: Social login avatar URL from database
        if (! empty($this->attributes['avatar'])) {
            return $this->attributes['avatar'];
        }

        // Final fallback: Default avatar
        return asset('images/default-avatar.jpg');
    }

    /**
     * Check if user is an administrator
     *
     * @return bool True if the user has admin role
     */
    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    /**
     * Check if user is a regular user
     *
     * @return bool True if the user has user role
     */
    public function isUser(): bool
    {
        return $this->hasRole(Role::USER);
    }

    /**
     * Check if user is a subscriber
     *
     * @return bool True if the user has subscriber role
     */
    public function isSubscriber(): bool
    {
        return $this->hasRole(Role::SUBSCRIBER);
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin();
    }

    /** @return HasOne<Coach, $this> */
    public function coach(): HasOne
    {
        return $this->hasOne(Coach::class);
    }

    /** @return HasOne<Client, $this> */
    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function isCoach(): bool
    {
        return $this->type === UserType::COACH;
    }

    public function isAthlete(): bool
    {
        return $this->type === UserType::ATLETA;
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function backupCodes(): HasMany
    {
        return $this->hasMany(TwoFactorBackupCode::class);
    }

    /**
     * Check if user has confirmed 2FA (not just enabled).
     */
    public function hasConfirmedTwoFactorAuthentication(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Called by the password broker (web flow only) — the token is a broker
     * token, so the link must point at the Inertia reset page. The API flow
     * issues a JWT and notifies through ForgotPasswordAction instead.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(ResetPasswordNotification::forWeb($token, $this->getEmailForPasswordReset()));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new EmailVerificationNotification);
    }
}
