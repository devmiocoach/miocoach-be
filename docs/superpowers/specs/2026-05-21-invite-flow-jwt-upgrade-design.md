# Invite Flow JWT Upgrade — Design Spec

**Date:** 2026-05-21  
**Scope:** `modules/Users` + `modules/Auth` + `app/Services/`  
**Out of scope:** Notifications module (tracked in `MODULES_TODO.md`)

---

## Problem

1. `SendInvitationAction` emette `Str::random(64)` con TTL 72h. Spec richiede JWT 7gg con claims `{ coach_id, email }`.
2. `InvitationController::register()` ritorna 201 + messaggio — non ritorna token. Spec richiede login diretto (access + refresh token).
3. `RegisterViaInviteRequest` accetta solo `name` + `password`. Spec richiede `firstName, lastName, dateOfBirth, gender, heightCm, objective, fitnessLevel`.
4. `RegisterViaInviteAction` chiama `CreateClientAction::handle($user, $coach, $data)` — firma errata (CreateClientAction è `handle(Coach, array)`).
5. `coach_invitations.token` è VARCHAR(64) — JWT non ci sta (~300 chars).
6. DB `clients` manca colonne `objective` e `fitness_level`.

---

## Architecture

### Cross-module token layer

`JwtService` vive in `modules/Auth`. `SendInvitationAction` vive in `modules/Users`. I moduli non si importano direttamente. Soluzione: `app/Services/InviteTokenService` (layer cross-dominio accessibile da entrambi i moduli).

```
modules/Users/Actions/SendInvitationAction
    → app/Services/InviteTokenService::generate()
        → modules/Auth/Services/JwtService::generateInviteToken()

modules/Auth/Actions/RegisterViaInviteAction
    → app/Services/InviteTokenService::decode()
        → modules/Auth/Services/JwtService::decode()
```

### Revocabilità

JWT + DB record: il token JWT è il valore salvato in `coach_invitations.token`. Accept-invite fa lookup `WHERE token = <jwt> AND status = pending`. `DELETE /coaches/invitations/{id}` invalida il DB record → token JWT non più accettato anche se non ancora scaduto.

---

## Data Model Changes

### Migration 1 — `coach_invitations.token`: VARCHAR(64) → TEXT

JWT RS256 con claims standard occupa ~350-450 chars. Indice univoco rimane su TEXT (MySQL supporta prefix index).

```php
Schema::table('coach_invitations', function (Blueprint $table) {
    $table->text('token')->change();
});
```

### Migration 2 — `clients`: +`objective`, +`fitness_level`

```php
Schema::table('clients', function (Blueprint $table) {
    $table->string('objective', 255)->nullable()->after('avatar_url');
    $table->string('fitness_level', 50)->nullable()->after('objective');
});
```

---

## Files — Changes & Additions

### `app/Services/InviteTokenService.php` (NUOVO)

```php
namespace App\Services;

use Modules\Auth\Services\JwtService;
use stdClass;

class InviteTokenService
{
    public function __construct(private readonly JwtService $jwt) {}

    public function generate(int $coachId, string $email): string
    {
        return $this->jwt->generateInviteToken($coachId, $email);
    }

    /** @throws \InvalidArgumentException se JWT non è un invito */
    public function decode(string $token): stdClass
    {
        $payload = $this->jwt->decode($token);

        if (($payload->purpose ?? null) !== 'invite') {
            throw new \InvalidArgumentException('Token is not an invite token.');
        }

        return $payload;
    }
}
```

### `modules/Auth/app/Services/JwtService.php` (+metodo)

Aggiungere:

```php
public function generateInviteToken(int $coachId, string $email): string
{
    return $this->encode([
        'purpose'  => 'invite',
        'coach_id' => $coachId,
        'email'    => $email,
    ], 7 * 24 * 3600); // 7 days
}
```

### `modules/Users/app/Actions/SendInvitationAction.php`

- Iniettare `InviteTokenService` via costruttore
- Sostituire `Str::random(64)` con `$this->inviteToken->generate($coach->id, $email)`
- Rimangono: TTL `expires_at = now()->addDays(7)`, max pending check, revoca pending precedenti, notifica email

### `modules/Auth/app/Actions/RegisterViaInviteAction.php` (riscrittura)

Flusso:
1. `InviteTokenService::decode($token)` — fallisce → ValidationException
2. `CoachInvitation::where('token', $token)->where('status', 'pending')->lockForUpdate()->first()` — non trovato → 422
3. `User::where('email', $payload->email)->exists()` — esiste già → 422
4. Crea `User` (`name = "$firstName $lastName"`, `email_verified_at = now()`)
5. Assegna ruolo `Role::CLIENT`
6. Crea `Client` direttamente (no `CreateClientAction`) con tutti i campi profilo
7. `AttachAthleteToCoachAction::handle($coach, $client, 'active')`
8. `invitation->update(['status' => 'accepted', 'accepted_at' => now()])`
9. Audit log `CLIENT_CREATED`
10. Ritorna `$user`

> Non usa `CreateClientAction` per evitare il re-lookup email e il flusso di re-invito.

### `modules/Auth/app/Http/Controllers/Api/V1/InvitationController.php`

`register()` aggiornato:
- Chiama `$action->handle($token, $request->validated())` → ottiene `$user`
- Genera `$accessToken = $this->jwt->generateAccessToken($user)`
- Genera `$refreshToken = $this->refreshTokens->create($user, $request->input('device_name', 'unknown'))`
- Ritorna stessa struttura di `AuthController::tokenResponse()`

`validate()` aggiornato:
- Il `CoachInvitation` ora ha token TEXT (JWT). Lookup rimane `where('token', $token)`.
- `isPending()` controlla `status = pending AND expires_at > now()`.

### `modules/Auth/app/Http/Requests/RegisterViaInviteRequest.php`

```php
public function rules(): array
{
    return [
        'firstName'    => ['required', 'string', 'max:255'],
        'lastName'     => ['required', 'string', 'max:255'],
        'password'     => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()],
        'dateOfBirth'  => ['nullable', 'date', 'before:today'],
        'gender'       => ['nullable', 'in:male,female,other'],
        'heightCm'     => ['nullable', 'numeric', 'min:50', 'max:300'],
        'objective'    => ['nullable', 'string', 'max:255'],
        'fitnessLevel' => ['nullable', 'in:beginner,intermediate,advanced'],
    ];
}
```

### `modules/Auth/routes/api.php`

Aggiungere alias (il vecchio path rimane per backward compat):

```php
Route::post('accept-invite/{token}', [InvitationController::class, 'register'])
    ->middleware('throttle:3,60')
    ->name('invite.accept');
```

---

## API Contract

### `GET /api/v1/auth/invite/{token}` (validate — invariato)

Response `200`:
```json
{
  "valid": true,
  "email": "user@example.com",
  "coach_name": "Mario Rossi",
  "expires_at": "2026-05-28T08:00:00.000Z"
}
```

### `POST /api/v1/auth/accept-invite/{token}` (nuovo alias)

Request body:
```json
{
  "firstName": "Giulia",
  "lastName": "Bianchi",
  "password": "Secret123!",
  "password_confirmation": "Secret123!",
  "dateOfBirth": "1995-03-15",
  "gender": "female",
  "heightCm": 168,
  "objective": "Perdere peso e tonificare",
  "fitnessLevel": "beginner"
}
```

Response `201`:
```json
{ "access_token": "eyJ...", "token_type": "Bearer" }
+ Set-Cookie: refresh_token=...; HttpOnly; Secure; SameSite=Strict
```

Error `422` (token non valido / scaduto / già usato):
```json
{ "message": "Il link di invito non è valido o è scaduto.", "errors": { "token": ["..."] } }
```

---

## Error Handling

| Caso | HTTP | Messaggio |
|------|------|-----------|
| JWT malformato / firma invalida | 422 | "Il link di invito non è valido o è scaduto." |
| JWT scaduto | 422 | "Il link di invito non è valido o è scaduto." |
| JWT purpose != 'invite' | 422 | "Il link di invito non è valido o è scaduto." |
| DB record non trovato / status != pending | 422 | "Il link di invito non è valido o è scaduto." |
| Email già registrata | 422 | "Esiste già un account associato a questo invito." |
| Validation campi body | 422 | Standard Laravel validation errors |

---

## Testing

Ogni file modificato o creato richiede test. Copertura minima:

| Test | Tipo |
|------|------|
| `InviteTokenService::generate()` produce JWT con claims corretti | Unit |
| `InviteTokenService::decode()` fallisce su token non-invite | Unit |
| `SendInvitationAction` salva JWT in `coach_invitations.token` | Feature |
| `POST /accept-invite/{token}` → 201 + tokens, crea User+Client, segna invitation accepted | Feature |
| `POST /accept-invite/{token}` → 422 JWT scaduto | Feature |
| `POST /accept-invite/{token}` → 422 invitation già usata | Feature |
| `POST /accept-invite/{token}` → 422 email già registrata | Feature |
| `POST /accept-invite/{token}` → 422 validation campi | Feature |
| Revoca invitation → `POST /accept-invite/{token}` → 422 | Feature |

---

## Sequence (happy path)

```
Coach POST /coaches/me/clients { email: "new@example.com" }
  → SendInvitationAction
  → InviteTokenService::generate(coachId, email) → JWT
  → CoachInvitation::create({ token: JWT, expires_at: +7d })
  → CoachInvitationNotification → email con link /accept-invite/{JWT}

Cliente apre link → GET /api/v1/auth/invite/{JWT}
  → validate() → { valid: true, email, coach_name, expires_at }

Cliente compila form → POST /api/v1/auth/accept-invite/{JWT}
  { firstName, lastName, password, ... }
  → InviteTokenService::decode(JWT) → { coach_id, email }
  → CoachInvitation WHERE token = JWT AND status = pending ✓
  → User::create(email, password, email_verified_at=now())
  → Client::create(profile fields)
  → AttachAthleteToCoachAction(coach, client, 'active')
  → invitation.status = accepted
  → return { access_token } + refresh_token cookie
```
