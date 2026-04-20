# Auth Module — Complete Security Fix Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix every security vulnerability and spec compliance gap identified in the auth module security review, replacing Sanctum opaque tokens with RS256 JWT (access) + DB-backed refresh tokens in httpOnly cookies, adding audit logging, fixing 2FA login flow, and hardening all endpoints.

**Architecture:** Access tokens are short-lived RS256 JWTs (15 min); refresh tokens are 64-byte random values stored as SHA-256 hashes in a `refresh_tokens` DB table and sent as httpOnly Secure SameSite=Strict cookies. A custom `JwtAuthenticate` middleware replaces `auth:sanctum`. Audit events are written to an `audit_logs` table via `AuditLogService`. Backup codes are stored as individual SHA-256 hashes in `two_factor_backup_codes`. Password reset and email verification both use short-lived RS256 JWTs.

**Tech Stack:** Laravel 13, PHP 8.3, `firebase/php-jwt` ^6, Laravel Fortify (for TOTP generation only), Spatie Laravel Permission, PostgreSQL.

---

## File Map

### New files
- `app/Modules/Auth/database/migrations/2026_04_20_000001_create_refresh_tokens_table.php`
- `app/Modules/Auth/database/migrations/2026_04_20_000002_create_audit_logs_table.php`
- `app/Modules/Auth/database/migrations/2026_04_20_000003_create_two_factor_backup_codes_table.php`
- `app/Modules/Auth/src/Models/RefreshToken.php`
- `app/Modules/Auth/src/Models/AuditLog.php`
- `app/Modules/Auth/src/Models/TwoFactorBackupCode.php`
- `app/Modules/Auth/src/Services/JwtService.php`
- `app/Modules/Auth/src/Services/RefreshTokenService.php`
- `app/Modules/Auth/src/Services/AuditLogService.php`
- `app/Modules/Auth/src/Exceptions/AccountLockedException.php`
- `app/Modules/Auth/src/Http/Middleware/JwtAuthenticate.php`
- `app/Modules/Auth/src/Http/Middleware/RequireVerified.php`
- `app/Modules/Auth/src/Actions/VerifyTwoFactorLoginAction.php`
- `app/Modules/Auth/src/Http/Controllers/Api/V1/TwoFactorVerifyController.php`
- `app/Modules/Auth/src/Http/Requests/TwoFactorVerifyRequest.php`

### Modified files
- `composer.json` — add `firebase/php-jwt`
- `.env.example` — add `JWT_PRIVATE_KEY`, `JWT_PUBLIC_KEY`
- `config/auth.php` — add JWT config section
- `app/Models/User.php` — remove `HasApiTokens`, add `refreshTokens()` + `backupCodes()` relations
- `app/Modules/Auth/src/Services/AccountLockoutService.php` — MAX_FAILURES=5, LOCKOUT_MINUTES=15
- `app/Modules/Auth/src/Actions/LoginAction.php` — RS256 JWT, tempToken for 2FA, httpOnly cookie, audit log
- `app/Modules/Auth/src/Actions/RegisterAction.php` — return message only, no auto-login
- `app/Modules/Auth/src/Actions/RefreshTokenAction.php` — DB refresh tokens, httpOnly cookie
- `app/Modules/Auth/src/Actions/LogoutAction.php` — revoke DB refresh token, clear cookie, audit log
- `app/Modules/Auth/src/Actions/ResetPasswordAction.php` — JWT token validation, revoke all refresh tokens, audit log
- `app/Modules/Auth/src/Actions/VerifyEmailAction.php` — JWT token validation
- `app/Modules/Auth/src/Actions/EnableTwoFactorAction.php` — generate backup codes into backup_codes table
- `app/Modules/Auth/src/Http/Controllers/Api/V1/AuthController.php` — clean up, httpOnly cookie helpers
- `app/Modules/Auth/src/Http/Controllers/Api/V1/TwoFactorController.php` — return qrCodeUrl + backupCodes
- `app/Modules/Auth/src/Http/Controllers/Api/V1/EmailVerificationController.php` — redirect on verify
- `app/Modules/Auth/src/Http/Controllers/Api/V1/PasswordController.php` — JWT reset token
- `app/Modules/Auth/src/Notifications/EmailVerificationNotification.php` — JWT link
- `app/Modules/Auth/src/Notifications/ResetPasswordNotification.php` — JWT link
- `app/Modules/Auth/src/Providers/AuthServiceProvider.php` — register new services, middleware aliases
- `app/Modules/Auth/routes/api.php` — fix rate limits, add 2FA verify route, swap middleware

---

## Task 1: Install firebase/php-jwt + generate RS256 keys

**Files:**
- Modify: `composer.json`
- Modify: `.env.example`
- Modify: `config/auth.php`

- [ ] **Step 1: Add firebase/php-jwt to composer.json**

```bash
cd /path/to/project && composer require firebase/php-jwt:^6
```

- [ ] **Step 2: Generate RSA key pair and add to .env.example**

```bash
# Generate private key (4096 bit RSA)
openssl genrsa -out jwt_private.pem 4096
# Extract public key
openssl rsa -in jwt_private.pem -pubout -out jwt_public.pem
# Base64-encode for .env storage (single line)
echo "JWT_PRIVATE_KEY=$(base64 -i jwt_private.pem | tr -d '\n')"
echo "JWT_PUBLIC_KEY=$(base64 -i jwt_public.pem | tr -d '\n')"
# Delete PEM files after copying values to .env
rm jwt_private.pem jwt_public.pem
```

Add to `.env.example` (at bottom):
```
JWT_PRIVATE_KEY=base64_encoded_rsa_private_key_here
JWT_PUBLIC_KEY=base64_encoded_rsa_public_key_here
```

- [ ] **Step 3: Add JWT section to config/auth.php**

Add after `'password_timeout'` key:

```php
'jwt' => [
    'private_key' => base64_decode(env('JWT_PRIVATE_KEY', '')),
    'public_key'  => base64_decode(env('JWT_PUBLIC_KEY', '')),
    'access_ttl'  => 15 * 60,       // 15 minutes in seconds
    'temp_ttl'    => 5 * 60,        // 5 minutes for 2FA pending token
    'reset_ttl'   => 60 * 60,       // 1 hour for password reset
    'verify_ttl'  => 24 * 60 * 60,  // 24 hours for email verification
],
```

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock .env.example config/auth.php
git commit -m "feat: add firebase/php-jwt + RS256 key config"
```

---

## Task 2: DB migrations — refresh_tokens, audit_logs, two_factor_backup_codes

**Files:**
- Create: `app/Modules/Auth/database/migrations/2026_04_20_000001_create_refresh_tokens_table.php`
- Create: `app/Modules/Auth/database/migrations/2026_04_20_000002_create_audit_logs_table.php`
- Create: `app/Modules/Auth/database/migrations/2026_04_20_000003_create_two_factor_backup_codes_table.php`

- [ ] **Step 1: Create refresh_tokens migration**

```php
<?php
// app/Modules/Auth/database/migrations/2026_04_20_000001_create_refresh_tokens_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('token_hash', 64)->unique(); // SHA-256 hex = 64 chars
            $table->string('device_name', 255)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
```

- [ ] **Step 2: Create audit_logs migration**

```php
<?php
// app/Modules/Auth/database/migrations/2026_04_20_000002_create_audit_logs_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 64);           // e.g. LOGIN_SUCCESS
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['user_id', 'event']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
```

- [ ] **Step 3: Create two_factor_backup_codes migration**

```php
<?php
// app/Modules/Auth/database/migrations/2026_04_20_000003_create_two_factor_backup_codes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('two_factor_backup_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64); // SHA-256 of the plaintext code
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_backup_codes');
    }
};
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Auth/database/migrations/
git commit -m "feat: add refresh_tokens, audit_logs, two_factor_backup_codes migrations"
```

---

## Task 3: Models — RefreshToken, AuditLog, TwoFactorBackupCode

**Files:**
- Create: `app/Modules/Auth/src/Models/RefreshToken.php`
- Create: `app/Modules/Auth/src/Models/AuditLog.php`
- Create: `app/Modules/Auth/src/Models/TwoFactorBackupCode.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Create RefreshToken model**

```php
<?php
// app/Modules/Auth/src/Models/RefreshToken.php

namespace App\Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    protected $fillable = ['user_id', 'token_hash', 'device_name', 'expires_at', 'revoked_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return is_null($this->revoked_at) && $this->expires_at->isFuture();
    }
}
```

- [ ] **Step 2: Create AuditLog model**

```php
<?php
// app/Modules/Auth/src/Models/AuditLog.php

namespace App\Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null; // single timestamp column

    protected $fillable = ['user_id', 'event', 'ip_address', 'user_agent', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Create TwoFactorBackupCode model**

```php
<?php
// app/Modules/Auth/src/Models/TwoFactorBackupCode.php

namespace App\Modules\Auth\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorBackupCode extends Model
{
    protected $fillable = ['user_id', 'code_hash', 'used_at'];

    protected $casts = ['used_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUsed(): bool
    {
        return !is_null($this->used_at);
    }
}
```

- [ ] **Step 4: Update User model — remove HasApiTokens, add new relations**

Replace the entire `app/Models/User.php` with:

```php
<?php

namespace App\Models;

use App\Modules\Auth\Models\RefreshToken;
use App\Modules\Auth\Models\TwoFactorBackupCode;
use App\Modules\Auth\Notifications\EmailVerificationNotification;
use App\Modules\Auth\Notifications\ResetPasswordNotification;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'avatar',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new EmailVerificationNotification());
    }

    public function coach(): HasOne
    {
        return $this->hasOne(Coach::class);
    }

    public function client(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function backupCodes(): HasMany
    {
        return $this->hasMany(TwoFactorBackupCode::class);
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Auth/src/Models/ app/Models/User.php
git commit -m "feat: add RefreshToken, AuditLog, TwoFactorBackupCode models; remove HasApiTokens from User"
```

---

## Task 4: JwtService — RS256 access token, tempToken, password-reset token, email-verify token

**Files:**
- Create: `app/Modules/Auth/src/Services/JwtService.php`

- [ ] **Step 1: Create JwtService**

```php
<?php
// app/Modules/Auth/src/Services/JwtService.php

namespace App\Modules\Auth\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;

class JwtService
{
    private const ALGORITHM = 'RS256';

    private string $privateKey;
    private string $publicKey;

    public function __construct()
    {
        $this->privateKey = config('auth.jwt.private_key');
        $this->publicKey  = config('auth.jwt.public_key');
    }

    public function generateAccessToken(User $user): string
    {
        return $this->encode([
            'sub'  => (string) $user->id,
            'role' => $user->getRoleNames()->first(),
        ], config('auth.jwt.access_ttl'));
    }

    public function generateTempToken(User $user): string
    {
        return $this->encode([
            'sub'            => (string) $user->id,
            'two_fa_pending' => true,
        ], config('auth.jwt.temp_ttl'));
    }

    public function generatePasswordResetToken(string $email): string
    {
        return $this->encode([
            'purpose' => 'password_reset',
            'email'   => $email,
        ], config('auth.jwt.reset_ttl'));
    }

    public function generateEmailVerifyToken(User $user): string
    {
        return $this->encode([
            'purpose'    => 'email_verify',
            'sub'        => (string) $user->id,
            'email_hash' => sha1($user->email),
        ], config('auth.jwt.verify_ttl'));
    }

    /**
     * @throws \Firebase\JWT\ExpiredException
     * @throws \Firebase\JWT\SignatureInvalidException
     * @throws \UnexpectedValueException
     */
    public function decode(string $token): stdClass
    {
        return JWT::decode($token, new Key($this->publicKey, self::ALGORITHM));
    }

    private function encode(array $claims, int $ttl): string
    {
        $now = time();

        return JWT::encode(
            array_merge([
                'iss' => config('app.url'),
                'iat' => $now,
                'exp' => $now + $ttl,
            ], $claims),
            $this->privateKey,
            self::ALGORITHM,
        );
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Auth/src/Services/JwtService.php
git commit -m "feat: add JwtService with RS256 access/temp/reset/verify token generation"
```

---

## Task 5: RefreshTokenService — create, rotate, revoke, revokeAll

**Files:**
- Create: `app/Modules/Auth/src/Services/RefreshTokenService.php`

- [ ] **Step 1: Create RefreshTokenService**

```php
<?php
// app/Modules/Auth/src/Services/RefreshTokenService.php

namespace App\Modules\Auth\Services;

use App\Models\User;
use App\Modules\Auth\Models\RefreshToken;
use Illuminate\Validation\ValidationException;

class RefreshTokenService
{
    private const TOKEN_BYTES = 64;
    private const TTL_DAYS    = 30;

    public function create(User $user, ?string $deviceName = null): string
    {
        $plaintext = bin2hex(random_bytes(self::TOKEN_BYTES));

        RefreshToken::create([
            'user_id'     => $user->id,
            'token_hash'  => hash('sha256', $plaintext),
            'device_name' => $deviceName,
            'expires_at'  => now()->addDays(self::TTL_DAYS),
        ]);

        return $plaintext;
    }

    /**
     * Rotate: revoke old token, issue new one. Atomic in a transaction.
     *
     * @throws ValidationException if token is invalid/expired/revoked
     */
    public function rotate(string $plaintext, User $user, ?string $deviceName = null): string
    {
        $existing = RefreshToken::where('token_hash', hash('sha256', $plaintext))
            ->where('user_id', $user->id)
            ->first();

        if (! $existing || ! $existing->isValid()) {
            throw ValidationException::withMessages([
                'token' => ['Refresh token non valido o scaduto.'],
            ]);
        }

        $existing->update(['revoked_at' => now()]);

        return $this->create($user, $deviceName ?? $existing->device_name);
    }

    public function revoke(string $plaintext): void
    {
        RefreshToken::where('token_hash', hash('sha256', $plaintext))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function revokeAll(User $user): void
    {
        $user->refreshTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function findValid(string $plaintext, User $user): ?RefreshToken
    {
        $token = RefreshToken::where('token_hash', hash('sha256', $plaintext))
            ->where('user_id', $user->id)
            ->first();

        return ($token && $token->isValid()) ? $token : null;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Auth/src/Services/RefreshTokenService.php
git commit -m "feat: add RefreshTokenService with create/rotate/revoke/revokeAll"
```

---

## Task 6: AuditLogService

**Files:**
- Create: `app/Modules/Auth/src/Services/AuditLogService.php`

- [ ] **Step 1: Create AuditLogService**

```php
<?php
// app/Modules/Auth/src/Services/AuditLogService.php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Models\AuditLog;

class AuditLogService
{
    public const LOGIN_SUCCESS    = 'LOGIN_SUCCESS';
    public const LOGIN_FAILED     = 'LOGIN_FAILED';
    public const LOGOUT           = 'LOGOUT';
    public const PASSWORD_RESET   = 'PASSWORD_RESET';
    public const TWO_FA_SUCCESS   = 'TWO_FA_SUCCESS';
    public const TWO_FA_FAILED    = 'TWO_FA_FAILED';

    public function log(string $event, ?int $userId = null, array $metadata = []): void
    {
        AuditLog::create([
            'user_id'    => $userId,
            'event'      => $event,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata'   => $metadata ?: null,
        ]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Auth/src/Services/AuditLogService.php
git commit -m "feat: add AuditLogService"
```

---

## Task 7: AccountLockoutService fix + AccountLockedException (423)

**Files:**
- Modify: `app/Modules/Auth/src/Services/AccountLockoutService.php`
- Create: `app/Modules/Auth/src/Exceptions/AccountLockedException.php`

- [ ] **Step 1: Fix AccountLockoutService thresholds**

Replace the entire file:

```php
<?php
// app/Modules/Auth/src/Services/AccountLockoutService.php

namespace App\Modules\Auth\Services;

use Illuminate\Support\Facades\Cache;

class AccountLockoutService
{
    private const MAX_FAILURES    = 5;  // spec: 5 failures
    private const LOCKOUT_MINUTES = 15; // spec: 15 minutes
    private const PREFIX          = 'account_lockout:';

    public function increment(string $email): void
    {
        $key = $this->key($email);
        $ttl = now()->addMinutes(self::LOCKOUT_MINUTES);

        Cache::add($key, 0, $ttl);
        Cache::increment($key);
    }

    public function isLocked(string $email): bool
    {
        return Cache::get($this->key($email), 0) >= self::MAX_FAILURES;
    }

    public function availableIn(string $email): int
    {
        return Cache::getTimeToLive($this->key($email)) ?? 0;
    }

    public function clear(string $email): void
    {
        Cache::forget($this->key($email));
    }

    private function key(string $email): string
    {
        return self::PREFIX . mb_strtolower($email) . '|' . request()->ip();
    }
}
```

- [ ] **Step 2: Create AccountLockedException**

```php
<?php
// app/Modules/Auth/src/Exceptions/AccountLockedException.php

namespace App\Modules\Auth\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountLockedException extends HttpException
{
    public function __construct(int $retryAfter)
    {
        parent::__construct(
            statusCode: 423,
            message: "Account bloccato per troppi tentativi falliti. Riprova tra {$retryAfter} secondi.",
            headers: ['Retry-After' => $retryAfter],
        );
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Auth/src/Services/AccountLockoutService.php \
        app/Modules/Auth/src/Exceptions/AccountLockedException.php
git commit -m "fix: lockout threshold 5 failures/15 min; add 423 AccountLockedException"
```

---

## Task 8: JwtAuthenticate middleware + RequireVerified middleware

**Files:**
- Create: `app/Modules/Auth/src/Http/Middleware/JwtAuthenticate.php`
- Create: `app/Modules/Auth/src/Http/Middleware/RequireVerified.php`

- [ ] **Step 1: Create JwtAuthenticate middleware**

```php
<?php
// app/Modules/Auth/src/Http/Middleware/JwtAuthenticate.php

namespace App\Modules\Auth\Http\Middleware;

use App\Models\User;
use App\Modules\Auth\Services\JwtService;
use Closure;
use Firebase\JWT\ExpiredException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class JwtAuthenticate
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if (! $bearer) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $payload = $this->jwt->decode($bearer);
        } catch (ExpiredException) {
            return response()->json(['message' => 'Token scaduto.'], 401);
        } catch (\Throwable) {
            return response()->json(['message' => 'Token non valido.'], 401);
        }

        // Reject temp tokens (two_fa_pending) — they cannot authenticate full requests
        if (! empty($payload->two_fa_pending)) {
            return response()->json(['message' => 'Autenticazione incompleta.'], 401);
        }

        $user = User::find($payload->sub);

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
```

- [ ] **Step 2: Create RequireVerified middleware**

```php
<?php
// app/Modules/Auth/src/Http/Middleware/RequireVerified.php

namespace App\Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email non verificata. Controlla la tua casella email.',
            ], 403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Auth/src/Http/Middleware/
git commit -m "feat: add JwtAuthenticate and RequireVerified middleware"
```

---

## Task 9: Update LoginAction — RS256 JWT, tempToken, httpOnly cookie, audit log, 423 on lockout

**Files:**
- Modify: `app/Modules/Auth/src/Actions/LoginAction.php`

- [ ] **Step 1: Rewrite LoginAction**

```php
<?php
// app/Modules/Auth/src/Actions/LoginAction.php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Exceptions\AccountLockedException;
use App\Modules\Auth\Notifications\NewDeviceLoginNotification;
use App\Modules\Auth\Services\AccountLockoutService;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Auth\Services\RateLimiterService;
use App\Modules\Auth\Services\RefreshTokenService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class LoginAction
{
    public function __construct(
        private readonly RateLimiterService  $rateLimiter,
        private readonly AccountLockoutService $lockout,
        private readonly JwtService          $jwt,
        private readonly RefreshTokenService $refreshTokens,
        private readonly AuditLogService     $audit,
    ) {}

    public function handle(string $email, string $password, ?string $deviceName = null): array
    {
        $this->rateLimiter->check($email);

        if ($this->lockout->isLocked($email)) {
            event(new Lockout(request()));
            throw new AccountLockedException($this->lockout->availableIn($email));
        }

        $email = mb_strtolower($email);

        if (! Auth::attempt(['email' => $email, 'password' => $password])) {
            $this->rateLimiter->hit($email);
            $this->lockout->increment($email);
            $this->audit->log(AuditLogService::LOGIN_FAILED, null, ['email' => $email]);

            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        $this->rateLimiter->clear($email);
        $this->lockout->clear($email);

        /** @var User $user */
        $user = Auth::user();
        Auth::logout(); // stateless — no session

        if ($user->hasEnabledTwoFactorAuthentication()) {
            $tempToken = $this->jwt->generateTempToken($user);

            return ['two_factor_required' => true, 'temp_token' => $tempToken];
        }

        $this->notifyNewDeviceIfNeeded($user);

        $accessToken  = $this->jwt->generateAccessToken($user);
        $refreshToken = $this->refreshTokens->create($user, $deviceName);

        $this->audit->log(AuditLogService::LOGIN_SUCCESS, $user->id);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    private function notifyNewDeviceIfNeeded(User $user): void
    {
        $ip       = request()->ip();
        $ua       = request()->userAgent() ?? 'unknown';
        $cacheKey = 'known_device:' . $user->id . ':' . hash('sha256', $ip . '|' . $ua);

        if (! Cache::has($cacheKey)) {
            Cache::put($cacheKey, true, now()->addDays(30));
            $user->notify(new NewDeviceLoginNotification($ip, $ua));
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Auth/src/Actions/LoginAction.php
git commit -m "feat: LoginAction uses RS256 JWT, tempToken for 2FA, 423 lockout, audit log"
```

---

## Task 10: Update RegisterAction — 201 + message, no auto-login

**Files:**
- Modify: `app/Modules/Auth/src/Actions/RegisterAction.php`

- [ ] **Step 1: Rewrite RegisterAction**

```php
<?php
// app/Modules/Auth/src/Actions/RegisterAction.php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;

class RegisterAction
{
    public function handle(array $data, string $role = 'client'): void
    {
        $user = User::create([
            'name'     => $data['name'],
            'email'    => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);

        $user->assignRole($role);

        event(new Registered($user)); // triggers email verification notification
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Auth/src/Actions/RegisterAction.php
git commit -m "fix: RegisterAction no longer auto-logs in; returns void; triggers verification email"
```

---

## Task 11: VerifyTwoFactorLoginAction + TwoFactorVerifyController + TwoFactorVerifyRequest

**Files:**
- Create: `app/Modules/Auth/src/Actions/VerifyTwoFactorLoginAction.php`
- Create: `app/Modules/Auth/src/Http/Controllers/Api/V1/TwoFactorVerifyController.php`
- Create: `app/Modules/Auth/src/Http/Requests/TwoFactorVerifyRequest.php`

- [ ] **Step 1: Create VerifyTwoFactorLoginAction**

```php
<?php
// app/Modules/Auth/src/Actions/VerifyTwoFactorLoginAction.php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Models\TwoFactorBackupCode;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Auth\Services\RefreshTokenService;
use Firebase\JWT\ExpiredException;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;

class VerifyTwoFactorLoginAction
{
    public function __construct(
        private readonly JwtService                      $jwt,
        private readonly RefreshTokenService             $refreshTokens,
        private readonly AuditLogService                 $audit,
        private readonly TwoFactorAuthenticationProvider $totp,
    ) {}

    public function handle(string $tempToken, string $code, ?string $deviceName = null): array
    {
        // Decode and validate the pending token
        try {
            $payload = $this->jwt->decode($tempToken);
        } catch (ExpiredException) {
            throw ValidationException::withMessages([
                'temp_token' => ['Token scaduto. Effettua nuovamente il login.'],
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'temp_token' => ['Token non valido.'],
            ]);
        }

        if (empty($payload->two_fa_pending)) {
            throw ValidationException::withMessages([
                'temp_token' => ['Token non valido.'],
            ]);
        }

        $user = User::findOrFail((int) $payload->sub);

        // Try TOTP first
        $totpValid = $this->totp->verify(
            decrypt($user->two_factor_secret),
            $code,
        );

        if (! $totpValid) {
            // Try backup code
            if (! $this->consumeBackupCode($user, $code)) {
                $this->audit->log(AuditLogService::TWO_FA_FAILED, $user->id);

                throw ValidationException::withMessages([
                    'code' => ['Codice 2FA non valido.'],
                ]);
            }
        }

        $accessToken  = $this->jwt->generateAccessToken($user);
        $refreshToken = $this->refreshTokens->create($user, $deviceName);

        $this->audit->log(AuditLogService::LOGIN_SUCCESS, $user->id);
        $this->audit->log(AuditLogService::TWO_FA_SUCCESS, $user->id);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
        ];
    }

    private function consumeBackupCode(User $user, string $code): bool
    {
        $hash = hash('sha256', $code);

        $backupCode = TwoFactorBackupCode::where('user_id', $user->id)
            ->where('code_hash', $hash)
            ->whereNull('used_at')
            ->first();

        if (! $backupCode) {
            return false;
        }

        $backupCode->update(['used_at' => now()]);

        return true;
    }
}
```

- [ ] **Step 2: Create TwoFactorVerifyRequest**

```php
<?php
// app/Modules/Auth/src/Http/Requests/TwoFactorVerifyRequest.php

namespace App\Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class TwoFactorVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $key = '2fa-login:' . $this->ip();

        if (RateLimiter::tooManyAttempts($key, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'code' => ["Troppi tentativi. Riprova tra {$seconds} secondi."],
            ]);
        }

        RateLimiter::hit($key, decay: 60 * 15);
    }

    public function rules(): array
    {
        return [
            'temp_token'  => ['required', 'string'],
            'code'        => ['required', 'string', 'max:16'], // TOTP (6 digits) or backup code
            'device_name' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 3: Create TwoFactorVerifyController**

```php
<?php
// app/Modules/Auth/src/Http/Controllers/Api/V1/TwoFactorVerifyController.php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\VerifyTwoFactorLoginAction;
use App\Modules\Auth\Http\Requests\TwoFactorVerifyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class TwoFactorVerifyController extends Controller
{
    public function __invoke(TwoFactorVerifyRequest $request, VerifyTwoFactorLoginAction $action): JsonResponse
    {
        $result = $action->handle(
            tempToken:  $request->temp_token,
            code:       $request->code,
            deviceName: $request->device_name,
        );

        return response()
            ->json(['access_token' => $result['access_token'], 'token_type' => 'Bearer'])
            ->withCookie(cookie(
                name:     'refresh_token',
                value:    $result['refresh_token'],
                minutes:  60 * 24 * 30,
                path:     '/api/v1/auth/refresh',
                secure:   true,
                httpOnly: true,
                sameSite: 'Strict',
            ));
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Auth/src/Actions/VerifyTwoFactorLoginAction.php \
        app/Modules/Auth/src/Http/Controllers/Api/V1/TwoFactorVerifyController.php \
        app/Modules/Auth/src/Http/Requests/TwoFactorVerifyRequest.php
git commit -m "feat: add POST /auth/two-factor/verify endpoint with tempToken + TOTP + backup code"
```

---

## Task 12: Update RefreshTokenAction — DB tokens + httpOnly cookie

**Files:**
- Modify: `app/Modules/Auth/src/Actions/RefreshTokenAction.php`

- [ ] **Step 1: Rewrite RefreshTokenAction**

```php
<?php
// app/Modules/Auth/src/Actions/RefreshTokenAction.php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Auth\Services\RefreshTokenService;
use Illuminate\Validation\ValidationException;

class RefreshTokenAction
{
    public function __construct(
        private readonly JwtService          $jwt,
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function handle(User $user, ?string $currentRefreshToken, ?string $deviceName = null): array
    {
        if (empty($currentRefreshToken)) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Refresh token mancante.'],
            ]);
        }

        $newRefreshToken = $this->refreshTokens->rotate($currentRefreshToken, $user, $deviceName);
        $accessToken     = $this->jwt->generateAccessToken($user);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $newRefreshToken,
        ];
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Auth/src/Actions/RefreshTokenAction.php
git commit -m "feat: RefreshTokenAction uses DB refresh tokens with rotation"
```

---

## Task 13: Update LogoutAction — revoke DB refresh token, clear cookie, audit log

**Files:**
- Modify: `app/Modules/Auth/src/Actions/LogoutAction.php`

- [ ] **Step 1: Rewrite LogoutAction**

```php
<?php
// app/Modules/Auth/src/Actions/LogoutAction.php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Auth\Services\RefreshTokenService;

class LogoutAction
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
        private readonly AuditLogService     $audit,
    ) {}

    public function handle(User $user, ?string $refreshToken): void
    {
        if ($refreshToken) {
            $this->refreshTokens->revoke($refreshToken);
        }

        $this->audit->log(AuditLogService::LOGOUT, $user->id);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Auth/src/Actions/LogoutAction.php
git commit -m "feat: LogoutAction revokes DB refresh token, logs LOGOUT audit event"
```

---

## Task 14: Fix ResetPasswordAction — JWT token, revoke all refresh tokens, audit log

**Files:**
- Modify: `app/Modules/Auth/src/Actions/ResetPasswordAction.php`
- Modify: `app/Modules/Auth/src/Actions/ForgotPasswordAction.php`
- Modify: `app/Modules/Auth/src/Notifications/ResetPasswordNotification.php`

- [ ] **Step 1: Rewrite ForgotPasswordAction to emit JWT reset token**

```php
<?php
// app/Modules/Auth/src/Actions/ForgotPasswordAction.php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\JwtService;
use Illuminate\Support\Facades\Log;

class ForgotPasswordAction
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(string $email): void
    {
        $user = User::where('email', mb_strtolower($email))->first();

        if (! $user) {
            // Anti-enumeration: always succeed silently
            Log::info('Password reset requested for unknown email', ['email' => $email]);
            return;
        }

        $token = $this->jwt->generatePasswordResetToken($user->email);

        $user->sendPasswordResetNotification($token);
    }
}
```

- [ ] **Step 2: Update ResetPasswordNotification to use the token directly as URL param**

```php
<?php
// app/Modules/Auth/src/Notifications/ResetPasswordNotification.php

namespace App\Modules\Auth\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(private readonly string $token) {}

    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $resetUrl = config('app.frontend_url', config('app.url'))
            . '/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage())
            ->subject('Reset della tua password — MioCoach')
            ->greeting('Ciao ' . $notifiable->name . ',')
            ->line('Hai richiesto il reset della tua password.')
            ->action('Reimposta Password', $resetUrl)
            ->line('Il link scade tra 60 minuti.')
            ->line('Se non hai richiesto il reset, ignora questa email.')
            ->salutation('Il team MioCoach');
    }
}
```

- [ ] **Step 3: Rewrite ResetPasswordAction — JWT decode, revoke all tokens, audit log**

```php
<?php
// app/Modules/Auth/src/Actions/ResetPasswordAction.php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Auth\Services\RefreshTokenService;
use Firebase\JWT\ExpiredException;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResetPasswordAction
{
    public function __construct(
        private readonly JwtService          $jwt,
        private readonly RefreshTokenService $refreshTokens,
        private readonly AuditLogService     $audit,
    ) {}

    public function handle(array $data): void
    {
        // Decode JWT reset token
        try {
            $payload = $this->jwt->decode($data['token']);
        } catch (ExpiredException) {
            throw ValidationException::withMessages([
                'token' => ['Il link di reset è scaduto. Richiedi un nuovo link.'],
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'token' => ['Il link di reset non è valido.'],
            ]);
        }

        if (($payload->purpose ?? '') !== 'password_reset') {
            throw ValidationException::withMessages([
                'token' => ['Il link di reset non è valido.'],
            ]);
        }

        if (mb_strtolower($data['email']) !== mb_strtolower($payload->email)) {
            throw ValidationException::withMessages([
                'email' => ['Email non corrispondente al token di reset.'],
            ]);
        }

        $user = User::where('email', mb_strtolower($data['email']))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['Il link di reset non è valido.'],
            ]);
        }

        $user->forceFill([
            'password'       => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        // Revoke ALL existing refresh tokens (attacker loses access)
        $this->refreshTokens->revokeAll($user);

        event(new PasswordReset($user));

        $this->audit->log(AuditLogService::PASSWORD_RESET, $user->id);
    }
}
```

- [ ] **Step 4: Update ResetPasswordRequest to remove 'confirmed' duplicate on email field**

The existing `ResetPasswordRequest` is already correct. No changes needed.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Auth/src/Actions/ForgotPasswordAction.php \
        app/Modules/Auth/src/Actions/ResetPasswordAction.php \
        app/Modules/Auth/src/Notifications/ResetPasswordNotification.php
git commit -m "feat: password reset uses JWT token; revokes all refresh tokens on reset; logs PASSWORD_RESET"
```

---

## Task 15: Fix email verification — JWT token + redirect

**Files:**
- Modify: `app/Modules/Auth/src/Actions/VerifyEmailAction.php`
- Modify: `app/Modules/Auth/src/Notifications/EmailVerificationNotification.php`
- Modify: `app/Modules/Auth/src/Http/Controllers/Api/V1/EmailVerificationController.php`

- [ ] **Step 1: Update EmailVerificationNotification to send JWT link**

```php
<?php
// app/Modules/Auth/src/Notifications/EmailVerificationNotification.php

namespace App\Modules\Auth\Notifications;

use App\Modules\Auth\Services\JwtService;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationNotification extends Notification
{
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        $token = app(JwtService::class)->generateEmailVerifyToken($notifiable);

        $verifyUrl = config('app.url') . '/api/v1/auth/verify-email/' . $token;

        return (new MailMessage())
            ->subject('Verifica il tuo indirizzo email — MioCoach')
            ->greeting('Ciao ' . $notifiable->name . ',')
            ->line('Clicca il pulsante qui sotto per verificare la tua email.')
            ->action('Verifica Email', $verifyUrl)
            ->line('Il link scade tra 24 ore.')
            ->line('Se non hai creato un account MioCoach, ignora questa email.')
            ->salutation('Il team MioCoach');
    }
}
```

- [ ] **Step 2: Update VerifyEmailAction to decode JWT**

```php
<?php
// app/Modules/Auth/src/Actions/VerifyEmailAction.php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Notifications\EmailVerificationNotification;
use App\Modules\Auth\Services\JwtService;
use Firebase\JWT\ExpiredException;
use Illuminate\Auth\Events\Verified;
use Illuminate\Validation\ValidationException;

class VerifyEmailAction
{
    public function __construct(private readonly JwtService $jwt) {}

    /**
     * Decodes the JWT verification token and marks the user as verified.
     *
     * @return User the verified user (for redirect)
     * @throws ValidationException on invalid/expired token
     */
    public function handle(string $token): User
    {
        try {
            $payload = $this->jwt->decode($token);
        } catch (ExpiredException) {
            throw ValidationException::withMessages([
                'token' => ['Il link di verifica è scaduto. Richiedine uno nuovo.'],
            ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'token' => ['Il link di verifica non è valido.'],
            ]);
        }

        if (($payload->purpose ?? '') !== 'email_verify') {
            throw ValidationException::withMessages([
                'token' => ['Il link di verifica non è valido.'],
            ]);
        }

        $user = User::findOrFail((int) $payload->sub);

        if (sha1($user->email) !== $payload->email_hash) {
            throw ValidationException::withMessages([
                'token' => ['Il link di verifica non è valido.'],
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return $user;
    }

    public function resend(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            $user->notify(new EmailVerificationNotification());
        }
    }
}
```

- [ ] **Step 3: Update EmailVerificationController — redirect on verify**

```php
<?php
// app/Modules/Auth/src/Http/Controllers/Api/V1/EmailVerificationController.php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\VerifyEmailAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, string $token, VerifyEmailAction $action): RedirectResponse
    {
        try {
            $action->handle($token);
        } catch (ValidationException) {
            return redirect(
                config('app.frontend_url', config('app.url')) . '/login?verified=false&error=invalid_token'
            );
        }

        return redirect(
            config('app.frontend_url', config('app.url')) . '/login?verified=true'
        );
    }

    public function resend(Request $request, VerifyEmailAction $action): JsonResponse
    {
        $action->resend($request->user());

        return response()->json(['message' => 'Email di verifica reinviata.']);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Auth/src/Actions/VerifyEmailAction.php \
        app/Modules/Auth/src/Notifications/EmailVerificationNotification.php \
        app/Modules/Auth/src/Http/Controllers/Api/V1/EmailVerificationController.php
git commit -m "feat: email verification uses JWT token; verify endpoint redirects to /login?verified=true"
```

---

## Task 16: Fix TwoFactor backup codes — hashed individually in DB

**Files:**
- Modify: `app/Modules/Auth/src/Actions/EnableTwoFactorAction.php`
- Modify: `app/Modules/Auth/src/Http/Controllers/Api/V1/TwoFactorController.php`

- [ ] **Step 1: Rewrite EnableTwoFactorAction to generate hashed backup codes**

```php
<?php
// app/Modules/Auth/src/Actions/EnableTwoFactorAction.php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Models\TwoFactorBackupCode;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

class EnableTwoFactorAction
{
    private const BACKUP_CODE_COUNT = 8;

    public function __construct(
        private readonly EnableTwoFactorAuthentication $fortifyAction,
    ) {}

    /**
     * Enables 2FA and generates 8 one-time backup codes.
     * Returns the plaintext codes (shown once; only hashes stored in DB).
     *
     * @return string[] plaintext backup codes
     */
    public function handle(User $user): array
    {
        ($this->fortifyAction)($user);

        // Delete any existing backup codes (re-setup)
        $user->backupCodes()->delete();

        $plaintextCodes = [];

        for ($i = 0; $i < self::BACKUP_CODE_COUNT; $i++) {
            $code = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
            $plaintextCodes[] = $code;

            TwoFactorBackupCode::create([
                'user_id'   => $user->id,
                'code_hash' => hash('sha256', $code),
            ]);
        }

        return $plaintextCodes;
    }
}
```

- [ ] **Step 2: Update TwoFactorController to return qrCodeUrl + backupCodes**

```php
<?php
// app/Modules/Auth/src/Http/Controllers/Api/V1/TwoFactorController.php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\ConfirmTwoFactorAction;
use App\Modules\Auth\Actions\DisableTwoFactorAction;
use App\Modules\Auth\Actions\EnableTwoFactorAction;
use App\Modules\Auth\Http\Requests\TwoFactorRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function enable(Request $request, EnableTwoFactorAction $action): JsonResponse
    {
        if ($request->user()->hasEnabledTwoFactorAuthentication()) {
            return response()->json(['message' => '2FA è già abilitato sul tuo account.'], 409);
        }

        $backupCodes = $action->handle($request->user());

        return response()->json([
            'message'      => '2FA abilitato. Scansiona il QR code con la tua app authenticator.',
            'qr_code_url'  => $request->user()->twoFactorQrCodeSvg(), // SVG inline or URL depending on Fortify config
            'backup_codes' => $backupCodes, // shown only once
        ]);
    }

    public function confirm(TwoFactorRequest $request, ConfirmTwoFactorAction $action): JsonResponse
    {
        $action->handle($request->user(), $request->code);

        return response()->json(['message' => '2FA confermato e attivo.']);
    }

    public function disable(Request $request, DisableTwoFactorAction $action): JsonResponse
    {
        $rateLimitKey = '2fa-disable:' . $request->user()->id;

        if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw ValidationException::withMessages([
                'password' => ["Troppi tentativi. Riprova tra {$seconds} secondi."],
            ]);
        }

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->password, $request->user()->password)) {
            RateLimiter::hit($rateLimitKey, decay: 60 * 15);
            throw ValidationException::withMessages([
                'password' => ['Password non corretta.'],
            ]);
        }

        RateLimiter::clear($rateLimitKey);
        $action->handle($request->user());

        // Delete backup codes
        $request->user()->backupCodes()->delete();

        return response()->json(['message' => '2FA disabilitato.']);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Auth/src/Actions/EnableTwoFactorAction.php \
        app/Modules/Auth/src/Http/Controllers/Api/V1/TwoFactorController.php
git commit -m "feat: 2FA backup codes individually hashed in DB; return qr_code_url + backup_codes"
```

---

## Task 17: Update AuthController — cookie management, clean responses

**Files:**
- Modify: `app/Modules/Auth/src/Http/Controllers/Api/V1/AuthController.php`

- [ ] **Step 1: Rewrite AuthController**

```php
<?php
// app/Modules/Auth/src/Http/Controllers/Api/V1/AuthController.php

namespace App\Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Actions\LoginAction;
use App\Modules\Auth\Actions\LogoutAction;
use App\Modules\Auth\Actions\RefreshTokenAction;
use App\Modules\Auth\Actions\RegisterAction;
use App\Modules\Auth\Actions\RegisterCoachAction;
use App\Modules\Auth\Actions\RevokeAllTokensAction;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Requests\RegisterCoachRequest;
use App\Modules\Auth\Http\Requests\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Cookie;

class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->handle(
            email:      $request->email,
            password:   $request->password,
            deviceName: $request->device_name,
        );

        if (isset($result['two_factor_required'])) {
            return response()->json([
                'two_factor_required' => true,
                'temp_token'          => $result['temp_token'],
            ]);
        }

        return $this->tokenResponse($result['access_token'], $result['refresh_token']);
    }

    public function registerClient(RegisterRequest $request, RegisterAction $action): JsonResponse
    {
        $action->handle(data: $request->validated(), role: 'client');

        return response()->json(['message' => 'Controlla la tua email per verificare il tuo account.'], 201);
    }

    public function registerCoach(RegisterCoachRequest $request, RegisterCoachAction $action): JsonResponse
    {
        $action->handle(data: $request->validated());

        return response()->json(['message' => 'Controlla la tua email per verificare il tuo account.'], 201);
    }

    public function logout(Request $request, LogoutAction $action): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');
        $action->handle($request->user(), $refreshToken);

        return response()
            ->json(['message' => 'Logout effettuato con successo.'])
            ->withoutCookie('refresh_token');
    }

    public function refresh(Request $request, RefreshTokenAction $action): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');
        $result = $action->handle($request->user(), $refreshToken, $request->input('device_name'));

        return $this->tokenResponse($result['access_token'], $result['refresh_token']);
    }

    public function logoutAll(Request $request, RevokeAllTokensAction $action): JsonResponse
    {
        $action->handle($request->user());

        return response()
            ->json(['message' => 'Tutti i token sono stati revocati.'])
            ->withoutCookie('refresh_token');
    }

    private function tokenResponse(string $accessToken, string $refreshToken): JsonResponse
    {
        return response()
            ->json([
                'access_token' => $accessToken,
                'token_type'   => 'Bearer',
                'expires_in'   => config('auth.jwt.access_ttl'),
            ])
            ->withCookie(cookie(
                name:     'refresh_token',
                value:    $refreshToken,
                minutes:  60 * 24 * 30,
                path:     '/api/v1/auth',
                secure:   true,
                httpOnly: true,
                sameSite: 'Strict',
            ));
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Auth/src/Http/Controllers/Api/V1/AuthController.php
git commit -m "feat: AuthController sends refresh_token as httpOnly Secure SameSite=Strict cookie; clean responses"
```

---

## Task 18: Update RevokeAllTokensAction to use RefreshTokenService

**Files:**
- Modify: `app/Modules/Auth/src/Actions/RevokeAllTokensAction.php`

- [ ] **Step 1: Rewrite RevokeAllTokensAction**

```php
<?php
// app/Modules/Auth/src/Actions/RevokeAllTokensAction.php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Services\RefreshTokenService;

class RevokeAllTokensAction
{
    public function __construct(private readonly RefreshTokenService $refreshTokens) {}

    public function handle(User $user): void
    {
        $this->refreshTokens->revokeAll($user);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Modules/Auth/src/Actions/RevokeAllTokensAction.php
git commit -m "fix: RevokeAllTokensAction uses RefreshTokenService"
```

---

## Task 19: Update routes — rate limits, middleware aliases, new endpoints

**Files:**
- Modify: `app/Modules/Auth/routes/api.php`
- Modify: `app/Modules/Auth/src/Providers/AuthServiceProvider.php`

- [ ] **Step 1: Register middleware aliases and new services in AuthServiceProvider**

```php
<?php
// app/Modules/Auth/src/Providers/AuthServiceProvider.php

namespace App\Modules\Auth\Providers;

use App\Modules\Auth\Http\Middleware\JwtAuthenticate;
use App\Modules\Auth\Http\Middleware\RequireVerified;
use App\Modules\Auth\Services\AccountLockoutService;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Auth\Services\JwtService;
use App\Modules\Auth\Services\RateLimiterService;
use App\Modules\Auth\Services\RefreshTokenService;
use App\Modules\Auth\Services\TokenBlacklistService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtService::class);
        $this->app->singleton(RefreshTokenService::class);
        $this->app->singleton(AuditLogService::class);
        $this->app->singleton(TokenBlacklistService::class);
        $this->app->singleton(AccountLockoutService::class);

        $this->app->singleton(RateLimiterService::class, function ($app) {
            return new RateLimiterService($app->make(RateLimiter::class));
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('jwt.auth', JwtAuthenticate::class);
        $router->aliasMiddleware('verified.email', RequireVerified::class);

        Route::middleware('api')->group(__DIR__ . '/../../routes/api.php');
    }
}
```

- [ ] **Step 2: Rewrite routes/api.php**

```php
<?php
// app/Modules/Auth/routes/api.php

use App\Modules\Auth\Http\Controllers\Api\V1\AuthController;
use App\Modules\Auth\Http\Controllers\Api\V1\EmailVerificationController;
use App\Modules\Auth\Http\Controllers\Api\V1\InvitationController;
use App\Modules\Auth\Http\Controllers\Api\V1\PasswordController;
use App\Modules\Auth\Http\Controllers\Api\V1\TwoFactorController;
use App\Modules\Auth\Http\Controllers\Api\V1\TwoFactorVerifyController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth')->name('auth.')->group(function () {

    // ─── Public endpoints ─────────────────────────────────────────────────────

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    // Spec: 3 registrations per IP per hour
    Route::post('register/client', [AuthController::class, 'registerClient'])
        ->middleware('throttle:3,60')
        ->name('register.client');

    Route::post('register/coach', [AuthController::class, 'registerCoach'])
        ->middleware('throttle:3,60')
        ->name('register.coach');

    // 2FA login completion (public — uses tempToken for auth)
    Route::post('two-factor/verify', TwoFactorVerifyController::class)
        ->middleware('throttle:10,1')
        ->name('two-factor.verify');

    // Invitations (public, one-time token)
    Route::get('invite/{token}', [InvitationController::class, 'validate'])
        ->middleware('throttle:20,1')
        ->name('invite.validate');

    Route::post('invite/{token}/register', [InvitationController::class, 'register'])
        ->middleware('throttle:3,60')
        ->name('invite.register');

    // Password reset: 5 requests per 15 min per IP
    Route::post('forgot-password', [PasswordController::class, 'forgotPassword'])
        ->middleware('throttle:5,15')
        ->name('password.forgot');

    Route::post('reset-password', [PasswordController::class, 'resetPassword'])
        ->middleware('throttle:10,15')
        ->name('password.reset');

    // Email verification (JWT token in path, redirects to frontend)
    Route::get('verify-email/{token}', [EmailVerificationController::class, 'verify'])
        ->middleware('throttle:6,1')
        ->name('verification.verify');

    // ─── Authenticated endpoints (JWT access token required) ──────────────────

    Route::middleware('jwt.auth')->group(function () {

        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');

        // Resend verification (does not require verified)
        Route::post('verify-email/resend', [EmailVerificationController::class, 'resend'])
            ->middleware('throttle:6,1')
            ->name('verification.resend');

        // ─── Verified users only ─────────────────────────────────────────────

        Route::middleware('verified.email')->group(function () {

            Route::prefix('two-factor')->name('two-factor.')->group(function () {
                Route::post('enable', [TwoFactorController::class, 'enable'])->name('enable');
                Route::post('confirm', [TwoFactorController::class, 'confirm'])->name('confirm');
                Route::delete('', [TwoFactorController::class, 'disable'])->name('disable');
            });
        });
    });
});
```

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Auth/routes/api.php \
        app/Modules/Auth/src/Providers/AuthServiceProvider.php
git commit -m "feat: update routes — rate limits per spec, jwt.auth + verified.email middleware, 2FA verify endpoint"
```

---

## Task 20: Run migrations, clear caches, verify

- [ ] **Step 1: Run migrations**

```bash
php artisan migrate
```

Expected output: 3 new tables created — `refresh_tokens`, `audit_logs`, `two_factor_backup_codes`.

- [ ] **Step 2: Clear caches**

```bash
php artisan config:clear && php artisan route:clear && php artisan cache:clear
```

- [ ] **Step 3: Verify routes are registered correctly**

```bash
php artisan route:list --path=api/v1/auth
```

Expected: `POST api/v1/auth/login`, `POST api/v1/auth/register/client`, `POST api/v1/auth/register/coach`, `POST api/v1/auth/two-factor/verify`, `GET api/v1/auth/verify-email/{token}`, and all authenticated routes.

- [ ] **Step 4: Remove Sanctum auth guard references from any remaining code**

Search for any remaining `auth:sanctum` references:

```bash
grep -r "auth:sanctum" app/ routes/
```

Replace each occurrence with `jwt.auth`.

- [ ] **Step 5: Add `FRONTEND_URL` to .env.example**

Add to `.env.example`:
```
APP_FRONTEND_URL=http://localhost:3000
```

Add to `config/app.php` in the `env` calls section:
```php
'frontend_url' => env('APP_FRONTEND_URL', env('APP_URL')),
```

- [ ] **Step 6: Final commit**

```bash
git add -p
git commit -m "fix: remove remaining auth:sanctum references; add APP_FRONTEND_URL config"
```

---

## Spec Compliance Checklist (self-review)

| Requirement | Task | Status |
|---|---|---|
| POST /auth/register — email format+uniqueness | existing RegisterRequest | ✅ |
| POST /auth/register — password policy (8+,case,num,symbol) | existing RegisterRequest | ✅ |
| POST /auth/register — bcrypt cost 12 | env + User cast | ✅ |
| POST /auth/register — send verification email | RegisterAction + Registered event | ✅ Task 10 |
| POST /auth/register — 201 `{message}` | Task 17 | ✅ |
| POST /auth/register — rate limit 3/IP/hour | Task 19 | ✅ |
| POST /auth/login — constant-time comparison | Auth::attempt (password_verify) | ✅ |
| POST /auth/login — 423 on lockout + Retry-After | Task 7 | ✅ |
| POST /auth/login — lockAccount after 5 failures, 15 min | Task 7 | ✅ |
| POST /auth/login — 2FA → `{requiresTwoFa, tempToken}` | Task 9 | ✅ |
| POST /auth/login — RS256 JWT access_token (15 min) | Task 4, 9 | ✅ |
| POST /auth/login — refresh_token (64 bytes, hash in DB) | Task 5, 9 | ✅ |
| POST /auth/login — refresh_token in httpOnly Secure SameSite=Strict cookie | Task 17 | ✅ |
| POST /auth/login — audit log LOGIN_SUCCESS/LOGIN_FAILED | Task 9 | ✅ |
| POST /auth/two-factor/verify — validate TOTP + backup codes | Task 11 | ✅ |
| POST /auth/two-factor/verify — complete login, emit tokens | Task 11 | ✅ |
| POST /auth/two-factor/setup — generate TOTP secret | Task 16 | ✅ |
| POST /auth/two-factor/setup — 8 one-time backup codes, hashed in DB | Task 16 | ✅ |
| POST /auth/two-factor/setup — return `{qrCodeUrl, backupCodes}` | Task 16 | ✅ |
| POST /auth/refresh — read refresh_token from httpOnly cookie | Task 12, 17 | ✅ |
| POST /auth/refresh — verify hash in DB, check expiry + revokedAt | Task 5, 12 | ✅ |
| POST /auth/refresh — token rotation | Task 5, 12 | ✅ |
| POST /auth/logout — revoke refresh_token (revokedAt=now) | Task 13 | ✅ |
| POST /auth/logout — clear httpOnly cookie | Task 17 | ✅ |
| POST /auth/logout — audit log LOGOUT | Task 13 | ✅ |
| GET /auth/verify-email/:token — JWT verify | Task 15 | ✅ |
| GET /auth/verify-email/:token — User.isVerified = true | Task 15 | ✅ |
| GET /auth/verify-email/:token — redirect /login?verified=true | Task 15 | ✅ |
| POST /auth/forgot-password — send JWT reset link (1h) | Task 14 | ✅ |
| POST /auth/forgot-password — always 200, no enumeration | Task 14 | ✅ |
| POST /auth/reset-password — JWT token validation | Task 14 | ✅ |
| POST /auth/reset-password — password policy + HIBP | existing ResetPasswordRequest | ✅ |
| POST /auth/reset-password — revoke ALL refresh tokens | Task 14 | ✅ |
| POST /auth/reset-password — log PASSWORD_RESET | Task 14 | ✅ |
| authenticate middleware — RS256 JWT, 401 if invalid/expired | Task 8 | ✅ |
| authorize(roles[]) — Spatie HasRoles | existing (Spatie) | ✅ |
| requireVerified — 403 if !isVerified | Task 8, 19 | ✅ |
