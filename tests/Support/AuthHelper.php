<?php

namespace Tests\Support;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Auth;

class AuthHelper
{
    /**
     * Provision a stable coach (user + COACH role + Coach profile) for E2E runs.
     * Idempotent: safe to call in every beforeEach.
     */
    public static function ensureCoach(string $email): void
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'E2E Coach',
                'password' => bcrypt('secretsauce'),
                'email_verified_at' => now(),
            ],
        );

        if (! $user->hasRole(Role::COACH->value)) {
            $user->assignRole(Role::COACH->value);
        }

        Coach::firstOrCreate(
            ['user_id' => $user->id],
            ['slug' => 'e2e-coach-'.$user->id],
        );
    }

    public static function loginAs(string $email): array
    {
        $user = User::where('email', $email)->firstOrFail();

        // Web middleware (StartSession) doesn't run for playwright routes, so we
        // must start and save the session manually to persist it to the database.
        $session = app('session.store');
        $session->start();

        Auth::loginUsingId($user->id); // writes user ID + calls migrate() internally

        $session->save();

        $cookieName = config('session.cookie');
        $sessionId = $session->getId();

        // EncryptCookies middleware encrypts the session cookie on web routes.
        // Inject the same encrypted format so Laravel accepts the cookie.
        $encrypter = app('encrypter');
        $prefix = CookieValuePrefix::create($cookieName, $encrypter->getKey());
        $encryptedValue = $encrypter->encrypt($prefix.$sessionId, false);

        return [
            'name' => $cookieName,
            'value' => $encryptedValue,
            'domain' => parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost',
            'path' => '/',
        ];
    }
}
