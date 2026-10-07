# Auth Module

Authentication, registration, magic link (passwordless), password reset, email verification, OAuth (Socialite), user impersonation, two-factor authentication (2FA), and dual auth (session + JWT).

## Key Files

| Layer | Files |
|-------|-------|
| Controllers (Web) | `LoginController`, `RegisterController`, `SocialiteController`, `ForgotPasswordController`, `ResetPasswordController`, `VerifyEmailController`, `EmailVerificationNotificationController`, `EmailVerificationPromptController`, `PasswordController`, `ReimpersonateController`, `MagicLinkController` |
| Controllers (Web 2FA) | `Web\TwoFactorController` (enable, showConfirm, confirm, disable, regenerateCodes, showChallenge, challenge), `Web\TwoFactorRecoveryController` (showRequest, sendRecoveryEmail, recover) |
| Controllers (API V1) | `Api\V1\AuthController` (login, registerClient, registerCoach, logout, refresh, logoutAll), `Api\V1\TwoFactorController` (enable, confirm, disable, regenerateCodes), `Api\V1\TwoFactorVerifyController`, `Api\V1\PasswordController`, `Api\V1\EmailVerificationController`, `Api\V1\InvitationController` |
| Models | `SocialAccount` (provider, tokens, avatar, last_login_at), `MagicLinkToken` (hashed token, expires_at, used_at) |
| Services | `JwtService` (RS256 encode/decode, access/temp/reset/verify tokens), `TokenBlacklistService`, `RefreshTokenService`, `AuditLogService`, `AccountLockoutService`, `RateLimiterService`, `SocialiteService` |
| Middleware | `JwtAuthenticate` (alias: `jwt.auth`), `RequireVerified` (alias: `verified.email`) |
| Actions | `LoginAction`, `LogoutAction`, `RefreshTokenAction`, `RevokeAllTokensAction`, `RegisterAction`, `RegisterCoachAction`, `RegisterViaInviteAction`, `ForgotPasswordAction`, `ResetPasswordAction`, `VerifyEmailAction`, `EnableTwoFactorAction`, `ConfirmTwoFactorAction`, `DisableTwoFactorAction`, `VerifyTwoFactorLoginAction`, `RegenerateBackupCodesAction` |
| Requests (Web) | `LoginRequest`, `RegisterRequest`, `TwoFactorRequest`, `TwoFactorChallengeRequest` |
| Requests (API) | `ApiLoginRequest`, `ApiRegisterRequest`, `RegisterCoachRequest`, `TwoFactorVerifyRequest` |
| Exceptions | `AuthException` (credentials, throttle), `SocialiteException` (disconnect, account linking, provider validation), `AccountLockedException` |
| Listeners | `AssignUserRole` (Registered), `UpdateUserLastLogin` (Login), `Impersonation` (TakeImpersonation — session history) |
| Notifications | `WelcomeNotification` (Registered), `MagicLinkNotification` (passwordless login link, 15-min expiry), `NewDeviceLoginNotification`, `TwoFactorRecoveryNotification` |
| Trait | `Sociable` — added to User model (socialAccounts relation, connected_providers, disconnect) |
| Filament | `AuthPlugin`, `UserResource` (list, create, view, edit), `UserForm`, `UsersTable` |
| Pages (Web) | `Login`, `Register`, `ForgotPassword`, `ResetPassword`, `VerifyEmail`, `MagicLink`, `TwoFactorEnable`, `TwoFactorConfirm`, `TwoFactorChallenge`, `TwoFactorRecovery` |
| Layout | `AuthCardLayout` — card with logo, status alerts, page transitions |
| Component | `SocialiteProviders` — Google/GitHub buttons with divider |

## Routes

### Web Routes (`/auth/*`)

**Guest routes**: login (GET/POST), register (GET/POST), forgot-password (GET/POST), reset-password/{token} (GET, signed), reset-password (POST, throttle:6,1), magic-link (GET/POST, throttle:5,1)

**Auth routes**: logout (ANY), verify-email (GET), verify-email/{id}/{hash} (GET, signed), email/verification-notification (POST, throttle:6,1), password (PUT)

**2FA web routes** (auth middleware): `two-factor.enable` (POST), `two-factor.confirm.show` (GET), `two-factor.confirm` (POST), `two-factor.disable` (DELETE), `two-factor.regenerate-codes` (POST)

**2FA challenge** (no auth middleware, throttle:10,1): `two-factor.challenge.show` (GET), `two-factor.challenge` (POST)

**2FA recovery** (guest middleware): `two-factor.recovery.show` (GET), `two-factor.recovery.send` (POST, throttle:3,60), `two-factor.recover/{token}` (GET)

**Socialite** (outside guest/auth groups): `auth.socialite.redirect` (GET), `auth.socialite.callback` (GET), `auth.socialite.disconnect` (DELETE, auth)

**Magic Link** (outside guest/auth groups): `magic-link.authenticate` — `/auth/magic-link/{token}` (GET) — must be accessible from email clients

**Impersonation**: `/auth/impersonate/{userId}` (POST, auth)

### API Routes (`/api/v1/auth/*`)

**Public endpoints**:
| Method | Route | Middleware | Description |
|--------|-------|-----------|-------------|
| POST | `/login` | throttle:10,1 | Login with email/password, returns JWT tokens or temp_token if 2FA required |
| POST | `/register/client` | throttle:3,60 | Register as client (email verification required) |
| POST | `/register/coach` | throttle:3,60 | Register as coach (email verification required) |
| POST | `/two-factor/verify` | throttle:10,1 | Verify 2FA code with temp_token, returns access + refresh tokens |
| POST | `/forgot-password` | throttle:5,15 | Request password reset token |
| POST | `/reset-password` | throttle:10,15 | Reset password with token |
| GET | `/verify-email/{token}` | throttle:6,1 | Verify email address |
| GET | `/invite/{token}` | throttle:20,1 | Validate invitation token |
| POST | `/invite/{token}/register` | throttle:3,60 | Register via invitation |

**Authenticated endpoints** (`jwt.auth` middleware):
| Method | Route | Middleware | Description |
|--------|-------|-----------|-------------|
| POST | `/logout` | jwt.auth | Logout (blacklists access token, revokes refresh token) |
| POST | `/logout-all` | jwt.auth | Revoke all tokens for user |
| POST | `/refresh` | jwt.auth | Refresh access token using refresh_token cookie |
| POST | `/verify-email/resend` | jwt.auth, throttle:6,1 | Resend email verification |
| POST | `/two-factor/enable` | jwt.auth, verified.email | Enable 2FA, returns QR code SVG + backup codes |
| POST | `/two-factor/confirm` | jwt.auth, verified.email | Confirm 2FA with TOTP code |
| DELETE | `/two-factor` | jwt.auth, verified.email | Disable 2FA (requires password) |
| POST | `/two-factor/regenerate-codes` | jwt.auth, verified.email | Regenerate backup codes (invalidates all existing) |

### Settings Module Integration

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/settings/security` | Security settings page (2FA enable/disable UI) |

## Patterns

### Dual Auth Architecture

The module supports two authentication surfaces simultaneously:

**Session-based (web/Inertia SPA):**
- Uses Laravel's built-in session guard
- Login via `LoginController` → `Auth::login()` → session
- 2FA challenge via session storage (`two_factor_pending_user_id`)
- Protected by `auth` middleware

**JWT-based (API/mobile/third-party):**
- Uses RS256 asymmetric keys (private key signs, public key verifies)
- Login via `Api\V1\AuthController` → `LoginAction` → JWT access token + refresh token cookie
- 2FA challenge via temp JWT token (`two_fa_pending: true` claim)
- Protected by `jwt.auth` middleware
- Refresh tokens stored in DB, blacklisted on logout

### JWT Token Flow

1. **Login** → POST `/api/v1/auth/login` with email + password
2. If 2FA enabled → returns `{ two_factor_required: true, temp_token: "..." }`
3. **2FA verify** → POST `/api/v1/auth/two-factor/verify` with temp_token + code
4. Returns `{ access_token, token_type: "Bearer" }` + `refresh_token` cookie
5. **API calls** → include `Authorization: Bearer <access_token>` header
6. **Refresh** → POST `/api/v1/auth/refresh` (uses refresh_token cookie)
7. **Logout** → POST `/api/v1/auth/logout` (blacklists token, revokes refresh)

### JWT Token Types

| Type | TTL | Purpose | Claims |
|------|-----|---------|--------|
| Access | 1h (default) | API authentication | `sub`, `role`, `iss`, `iat`, `exp` |
| Refresh | 30d (default) | Token refresh (stored in DB + httpOnly cookie) | DB-stored, not JWT |
| Temp | 5m (default) | 2FA challenge during login | `sub`, `two_fa_pending` |
| Reset | 1h (default) | Password reset | `purpose`, `email` |
| Verify | 24h (default) | Email verification | `purpose`, `sub`, `email_hash` |
| Invite | 7d (JWT_INVITE_TTL) | Coach invite link | `purpose: 'invite'`, `coach_id`, `email` |

### 2FA Web Flow (Inertia)

1. User clicks "Enable" on `/settings/security` → POST `two-factor.enable`
2. Renders `TwoFactorEnable.vue` with QR code SVG + backup codes
3. User scans QR → clicks "Avanti" → `TwoFactorConfirm.vue`
4. User enters TOTP code → POST `two-factor.confirm` → redirect to dashboard
5. On next login: `LoginController` detects `two_factor_confirmed_at` → redirects to `two-factor.challenge.show`
6. User enters code → POST `two-factor.challenge` → `Auth::login()` → dashboard

### 2FA API Flow (JWT)

1. POST `/api/v1/auth/login` → if 2FA enabled, returns `temp_token`
2. POST `/api/v1/auth/two-factor/verify` with `temp_token` + `code` → returns `access_token` + `refresh_token`
3. Subsequent API calls use `Authorization: Bearer <access_token>`

### 2FA Backup Codes Regeneration

Users can regenerate backup codes at any time from `/settings/security` or via API:
- **Web**: POST `two-factor.regenerate-codes` → invalidates all existing codes, generates 8 new ones, redirects to confirm page to display them
- **API**: POST `/api/v1/auth/two-factor/regenerate-codes` → returns new codes in JSON response
- Old codes are immediately invalidated (DB delete + re-insert)
- Codes are stored as SHA-256 hashes, never plaintext

### 2FA Recovery Flow (Lost Device)

When a user loses access to both their authenticator app AND backup codes:
1. From challenge page, click "Non hai accesso né ai codici? Recupera il tuo account"
2. Enter email → POST `two-factor.recovery.send` (throttled: 3 attempts per 60 min)
3. System generates a 24-hour recovery token (SHA-256 hashed, stored in cache)
4. Email sent with `TwoFactorRecoveryNotification` containing recovery link
5. User clicks link → GET `two-factor.recover/{token}` → 2FA disabled, all backup codes deleted
6. User can log in normally and re-enable 2FA if desired

**Security considerations:**
- Recovery token stored in cache (not DB) with 24h expiry
- Token is hashed before storage (plain token only in email link)
- Rate limited to prevent abuse
- Only works for accounts with 2FA already enabled
- Disables 2FA completely — user must re-enable manually

### Socialite Dual-Flow
`SocialiteController::callback()` checks `Auth::check()` to branch:
- **Guest**: finds/creates user via `SocialiteService::handleCallback()`, logs in, redirects to intended URL
- **Authenticated**: links account via `SocialiteService::linkAccountToUser()`, redirects to `settings.profile`

### Disconnect Validation
Cannot disconnect if it's the user's only login method. `SocialiteService::disconnectProvider()` throws `SocialiteException::cannotDisconnectOnlyMethod()` when `socialAccounts->count() === 1 && !$user->password`.

Also prevents account takeover: linking a social ID already owned by another user throws `accountAlreadyLinked`.

### Rate Limiting
`LoginRequest::ensureIsNotRateLimited()` — 5 attempts per `email|ip` key. Fires `Lockout` event, throws `AuthException::throttle($seconds)`. Cleared on success.

API login uses `RateLimiterService` + `AccountLockoutService` (progressive lockout on repeated failures).

### Impersonation
Uses `lab404/laravel-impersonate` + `filament-impersonate`. Session stores history at `impersonation.recent_history` (max 4 user IDs). `ReimpersonateController` lets admins re-impersonate from recent list (max 3 shown in UI, filters deleted users and self). Stop via `filament-impersonate.leave` route.

### Magic Link Flow
`MagicLinkController::store()` silently finds the user by email (no error on unknown email). If found: deletes existing tokens for that user, creates a new `MagicLinkToken` (token = SHA-256 hash of `Str::random(64)`, expires in 15 min), and sends `MagicLinkNotification` with the plain-token URL.

`MagicLinkController::authenticate()` hashes the incoming token, looks it up, calls `isValid()` (not expired + not used), logs in the user, marks the token used, and redirects to intended URL or dashboard.

**Token storage:** Plain token only lives in the email link. DB stores `hash('sha256', $plainToken)`. This means even if the DB is compromised, tokens cannot be forged or replayed.

### Logout Action Handler
`app.ts` registers a logout icon and action handler via `registerIcon('logout', ...)` and `registerAction('logout', ...)` from `@/lib/navigation`. The action shows a confirmation dialog and posts to `route('logout')`.

### New Device Notification
`LoginAction` detects new device (IP + User-Agent hash) and sends `NewDeviceLoginNotification`. Known devices cached for 30 days.

## ENV Variables

```
GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_CLIENT_REDIRECT_URI
GITHUB_CLIENT_ID, GITHUB_CLIENT_SECRET, GITHUB_CLIENT_REDIRECT_URI
JWT_PRIVATE_KEY          # RSA 2048-bit PEM (literal \n supported)
JWT_PUBLIC_KEY           # RSA 2048-bit PEM (literal \n supported)
JWT_ACCESS_TTL=3600      # Access token lifetime in seconds (default: 1h)
JWT_REFRESH_TTL=2592000  # Refresh token lifetime (default: 30d)
JWT_TEMP_TTL=300         # Temp 2FA token lifetime (default: 5m)
JWT_RESET_TTL=3600       # Password reset token lifetime (default: 1h)
JWT_VERIFY_TTL=86400     # Email verify token lifetime (default: 24h)
JWT_INVITE_TTL=604800   # Invite token lifetime (default: 7d)
AUTH_MAGIC_LINK_ENABLED=true
AUTH_MAGIC_LINK_EXPIRY=15
```

Redirect URIs default to `/auth/socialite/{provider}/callback`. Providers configured in `config/services.php`.

## Testing

```bash
php artisan test --testsuite=Modules --filter='^Modules\\Auth\\Tests'  # PHPUnit
npx playwright test --project="@Auth*"                 # E2E
```

**E2E coverage**: login (basic, errors, security/rate-limiting, social, logout), register (basic, errors), forgot-password (basic, errors), verify-email. Page objects in `tests/e2e/pages/`, fixtures in `tests/e2e/fixtures/users.ts`.

## Gotchas

- `LoginRequest::validateCredentials()` validates without logging in — the controller handles `Auth::login()` separately
- Social users get email auto-verified (`email_verified_at = now()`) and a random password
- Socialite redirect/callback routes are outside both guest and auth middleware groups
- `RegisterRequest::passedValidation()` hashes the password before the controller sees it
- Filament UserResource enforces single role (maxItems: 1) despite multi-select UI
- Magic link authenticate route is outside both guest and auth middleware groups (link is clicked from email client)
- `MagicLinkToken::isValid()` checks both `expires_at->isFuture()` and `used_at === null`
- JWT keys in `.env` can use literal `\n` — `JwtService::normalizeKey()` converts them to actual newlines
- `jwt.auth` middleware rejects tokens with `two_fa_pending: true` claim (incomplete 2FA)
- API 2FA disable requires password confirmation + rate limiting (5 attempts per 15 min)
- Fortify routes are disabled via `Fortify::ignoreRoutes()` — all 2FA logic is custom
- Backup codes regeneration invalidates ALL existing codes immediately — old codes cannot be used after regeneration
- 2FA recovery tokens are stored in cache (not DB) with 24h expiry — clearing cache invalidates all pending recoveries
- Recovery email is throttled to 3 attempts per 60 minutes per IP
- `TwoFactorRecoveryNotification` disables 2FA completely when the link is clicked — no additional confirmation
