# Invite Flow JWT Upgrade — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Upgrade the coach invite flow from plain random tokens (72h) to signed JWTs (7d), add profile fields to the accept-invite endpoint, and return access+refresh tokens on completion.

**Architecture:** A new cross-module `app/Services/InviteTokenService` wraps `JwtService` from the Auth module — this keeps module boundaries clean (modules never import each other directly). `SendInvitationAction` (Users) generates JWT tokens via it. `RegisterViaInviteAction` (Auth) is fully rewritten: decodes JWT, creates User+Client directly (no `CreateClientAction`), returns `User`. `InvitationController::register()` generates and returns access+refresh tokens like the login endpoint.

**Tech Stack:** Laravel 13, PHP 8.4, firebase/php-jwt (via existing `JwtService`), PHPUnit 12, Laravel Pint

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `app/Services/InviteTokenService.php` | CREATE | Generate + decode invite JWTs (cross-module wrapper) |
| `tests/Unit/Services/InviteTokenServiceTest.php` | CREATE | Unit tests for InviteTokenService (pure PHPUnit, mocked JwtService) |
| `modules/Auth/tests/Unit/Services/JwtServiceInviteTokenTest.php` | CREATE | Tests for new JwtService::generateInviteToken() |
| `modules/Auth/tests/Feature/InviteFlowTest.php` | CREATE | End-to-end accept-invite endpoint tests |
| `modules/Users/tests/Feature/SendInvitationJwtTest.php` | CREATE | Assert JWT token stored in DB after send |
| `modules/Users/database/migrations/2026_05_21_000001_change_token_to_text_in_coach_invitations_table.php` | CREATE | `coach_invitations.token` VARCHAR(64) → TEXT |
| `modules/Users/database/migrations/2026_05_21_000002_add_objective_and_fitness_level_to_clients_table.php` | CREATE | Add `objective` + `fitness_level` to clients |
| `modules/Auth/app/Services/JwtService.php` | MODIFY | +`generateInviteToken(int $coachId, string $email)` |
| `modules/Users/app/Actions/SendInvitationAction.php` | MODIFY | Use InviteTokenService; TTL 72h → 7d |
| `modules/Users/app/Models/Client.php` | MODIFY | Add `objective`, `fitness_level` to `$fillable` |
| `modules/Auth/app/Actions/RegisterViaInviteAction.php` | MODIFY | Rewrite: JWT decode, direct User+Client create, return User |
| `modules/Auth/app/Http/Controllers/Api/V1/InvitationController.php` | MODIFY | `register()` returns access+refresh tokens (201) |
| `modules/Auth/app/Http/Requests/RegisterViaInviteRequest.php` | MODIFY | Add `firstName`, `lastName`, `dateOfBirth`, `gender`, `heightCm`, `objective`, `fitnessLevel` |
| `modules/Auth/routes/api.php` | MODIFY | Add `accept-invite/{token}` alias route |

---

### Task 1: JwtService::generateInviteToken()

**Files:**
- Modify: `modules/Auth/app/Services/JwtService.php`
- Create: `modules/Auth/tests/Unit/Services/JwtServiceInviteTokenTest.php`

- [ ] **Step 1.1: Create failing test**

Create `modules/Auth/tests/Unit/Services/JwtServiceInviteTokenTest.php`:

```php
<?php

namespace Modules\Auth\Tests\Unit\Services;

use Modules\Auth\Services\JwtService;
use Tests\TestCase;

class JwtServiceInviteTokenTest extends TestCase
{
    public function test_generates_invite_token_with_correct_claims(): void
    {
        $service = app(JwtService::class);
        $token = $service->generateInviteToken(42, 'user@example.com');

        $this->assertIsString($token);
        $this->assertSame(2, substr_count($token, '.'), 'JWT must have 3 parts (header.payload.signature)');

        $payload = $service->decode($token);

        $this->assertSame('invite', $payload->purpose);
        $this->assertSame(42, $payload->coach_id);
        $this->assertSame('user@example.com', $payload->email);
    }

    public function test_invite_token_expires_in_seven_days(): void
    {
        $service = app(JwtService::class);
        $token = $service->generateInviteToken(1, 'test@test.com');
        $payload = $service->decode($token);

        $this->assertEqualsWithDelta(time() + 7 * 24 * 3600, $payload->exp, 60);
    }
}
```

- [ ] **Step 1.2: Run test to confirm it fails**

```bash
php -d memory_limit=2048M artisan test --testsuite=Modules --filter='JwtServiceInviteTokenTest' --compact
```

Expected: `Error: Call to undefined method generateInviteToken()`

- [ ] **Step 1.3: Add generateInviteToken() to JwtService**

In `modules/Auth/app/Services/JwtService.php`, add after `generateEmailVerifyToken()`:

```php
public function generateInviteToken(int $coachId, string $email): string
{
    return $this->encode([
        'purpose'  => 'invite',
        'coach_id' => $coachId,
        'email'    => $email,
    ], 7 * 24 * 3600);
}
```

- [ ] **Step 1.4: Run test to confirm it passes**

```bash
php -d memory_limit=2048M artisan test --testsuite=Modules --filter='JwtServiceInviteTokenTest' --compact
```

Expected: `PASS  2 / 2`

- [ ] **Step 1.5: Run Pint**

```bash
vendor/bin/pint modules/Auth/app/Services/JwtService.php --format agent
```

- [ ] **Step 1.6: Commit**

```bash
git add modules/Auth/app/Services/JwtService.php modules/Auth/tests/Unit/Services/JwtServiceInviteTokenTest.php
git commit -m "feat(auth): add generateInviteToken to JwtService"
```

---

### Task 2: InviteTokenService (cross-module service)

**Files:**
- Create: `app/Services/InviteTokenService.php`
- Create: `tests/Unit/Services/InviteTokenServiceTest.php`

- [ ] **Step 2.1: Create failing unit test**

Create `tests/Unit/Services/InviteTokenServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\InviteTokenService;
use Modules\Auth\Services\JwtService;
use PHPUnit\Framework\TestCase;
use stdClass;

class InviteTokenServiceTest extends TestCase
{
    public function test_generate_delegates_to_jwt_service(): void
    {
        $mockJwt = $this->createMock(JwtService::class);
        $mockJwt->expects($this->once())
            ->method('generateInviteToken')
            ->with(42, 'user@example.com')
            ->willReturn('header.payload.signature');

        $service = new InviteTokenService($mockJwt);

        $this->assertSame('header.payload.signature', $service->generate(42, 'user@example.com'));
    }

    public function test_decode_returns_payload_for_valid_invite_token(): void
    {
        $mockPayload = new stdClass();
        $mockPayload->purpose = 'invite';
        $mockPayload->coach_id = 42;
        $mockPayload->email = 'user@example.com';

        $mockJwt = $this->createMock(JwtService::class);
        $mockJwt->method('decode')->willReturn($mockPayload);

        $service = new InviteTokenService($mockJwt);
        $result = $service->decode('any.jwt.token');

        $this->assertSame(42, $result->coach_id);
        $this->assertSame('user@example.com', $result->email);
    }

    public function test_decode_throws_when_purpose_is_not_invite(): void
    {
        $mockPayload = new stdClass();
        $mockPayload->purpose = 'password_reset';

        $mockJwt = $this->createMock(JwtService::class);
        $mockJwt->method('decode')->willReturn($mockPayload);

        $service = new InviteTokenService($mockJwt);

        $this->expectException(\InvalidArgumentException::class);
        $service->decode('some.jwt.token');
    }

    public function test_decode_throws_when_purpose_is_missing(): void
    {
        $mockJwt = $this->createMock(JwtService::class);
        $mockJwt->method('decode')->willReturn(new stdClass());

        $service = new InviteTokenService($mockJwt);

        $this->expectException(\InvalidArgumentException::class);
        $service->decode('some.jwt.token');
    }
}
```

- [ ] **Step 2.2: Run test to confirm it fails**

```bash
php -d memory_limit=2048M artisan test tests/Unit/Services/InviteTokenServiceTest.php --compact
```

Expected: `Error: Class "App\Services\InviteTokenService" not found`

- [ ] **Step 2.3: Create InviteTokenService**

Create `app/Services/InviteTokenService.php`:

```php
<?php

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

    /**
     * @throws \InvalidArgumentException if token purpose is not 'invite'
     * @throws \Firebase\JWT\ExpiredException if token is expired
     * @throws \Firebase\JWT\SignatureInvalidException if signature is invalid
     */
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

- [ ] **Step 2.4: Run test to confirm it passes**

```bash
php -d memory_limit=2048M artisan test tests/Unit/Services/InviteTokenServiceTest.php --compact
```

Expected: `PASS  4 / 4`

- [ ] **Step 2.5: Run Pint**

```bash
vendor/bin/pint app/Services/InviteTokenService.php --format agent
```

- [ ] **Step 2.6: Commit**

```bash
git add app/Services/InviteTokenService.php tests/Unit/Services/InviteTokenServiceTest.php
git commit -m "feat: add InviteTokenService cross-module JWT wrapper"
```

---

### Task 3: Database Migrations + Client model

**Files:**
- Create: `modules/Users/database/migrations/2026_05_21_000001_change_token_to_text_in_coach_invitations_table.php`
- Create: `modules/Users/database/migrations/2026_05_21_000002_add_objective_and_fitness_level_to_clients_table.php`
- Modify: `modules/Users/app/Models/Client.php`

- [ ] **Step 3.1: Create migration for token column type change**

Create `modules/Users/database/migrations/2026_05_21_000001_change_token_to_text_in_coach_invitations_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coach_invitations', function (Blueprint $table) {
            $table->text('token')->change();
        });
    }

    public function down(): void
    {
        Schema::table('coach_invitations', function (Blueprint $table) {
            $table->string('token', 64)->change();
        });
    }
};
```

- [ ] **Step 3.2: Create migration for client profile fields**

Create `modules/Users/database/migrations/2026_05_21_000002_add_objective_and_fitness_level_to_clients_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('objective', 255)->nullable()->after('avatar_url');
            $table->string('fitness_level', 50)->nullable()->after('objective');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['objective', 'fitness_level']);
        });
    }
};
```

- [ ] **Step 3.3: Run migrations**

```bash
php artisan module:migrate Users
```

Expected: migrations run without error.

- [ ] **Step 3.4: Add new fields to Client fillable**

In `modules/Users/app/Models/Client.php`, update `$fillable` to:

```php
protected $fillable = [
    'goals',
    'birth_date',
    'gender',
    'height_cm',
    'weight_kg',
    'tags',
    'phone',
    'avatar_url',
    'objective',
    'fitness_level',
    'subscription_expires_at',
    'subscription_sessions_remaining',
];
```

- [ ] **Step 3.5: Run Pint**

```bash
vendor/bin/pint modules/Users/app/Models/Client.php modules/Users/database/migrations/2026_05_21_000001_change_token_to_text_in_coach_invitations_table.php modules/Users/database/migrations/2026_05_21_000002_add_objective_and_fitness_level_to_clients_table.php --format agent
```

- [ ] **Step 3.6: Commit**

```bash
git add modules/Users/database/migrations/2026_05_21_000001_change_token_to_text_in_coach_invitations_table.php modules/Users/database/migrations/2026_05_21_000002_add_objective_and_fitness_level_to_clients_table.php modules/Users/app/Models/Client.php
git commit -m "feat(users): add objective+fitness_level to clients; token column to text"
```

---

### Task 4: SendInvitationAction — JWT upgrade

**Files:**
- Modify: `modules/Users/app/Actions/SendInvitationAction.php`
- Create: `modules/Users/tests/Feature/SendInvitationJwtTest.php`

- [ ] **Step 4.1: Write failing feature test**

Create `modules/Users/tests/Feature/SendInvitationJwtTest.php`:

```php
<?php

namespace Modules\Users\Tests\Feature;

use App\Models\User;
use App\Modules\Users\Actions\SendInvitationAction;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SendInvitationJwtTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_token_is_a_jwt(): void
    {
        $user = User::factory()->create();
        $coach = Coach::factory()->create(['user_id' => $user->id]);

        $invitation = $this->app->make(SendInvitationAction::class)
            ->handle($coach, 'newclient@example.com');

        $this->assertSame(2, substr_count($invitation->token, '.'),
            'Token must be a JWT with 3 parts separated by 2 dots');
    }

    public function test_invitation_expires_in_seven_days(): void
    {
        $user = User::factory()->create();
        $coach = Coach::factory()->create(['user_id' => $user->id]);

        $invitation = $this->app->make(SendInvitationAction::class)
            ->handle($coach, 'newclient@example.com');

        $this->assertEqualsWithDelta(
            now()->addDays(7)->timestamp,
            $invitation->expires_at->timestamp,
            60
        );
    }

    public function test_existing_pending_invitation_is_revoked_before_new_one(): void
    {
        $user = User::factory()->create();
        $coach = Coach::factory()->create(['user_id' => $user->id]);
        $email = 'client@example.com';

        $this->app->make(SendInvitationAction::class)->handle($coach, $email);
        $first = CoachInvitation::where('email', $email)->first();

        $this->app->make(SendInvitationAction::class)->handle($coach, $email);

        $this->assertSame('revoked', $first->fresh()->status);
        $this->assertSame(1, CoachInvitation::where(['email' => $email, 'status' => 'pending'])->count());
    }
}
```

- [ ] **Step 4.2: Run test to confirm it fails**

```bash
php -d memory_limit=2048M artisan test --testsuite=Modules --filter='SendInvitationJwtTest' --compact
```

Expected: tests fail (token has 0 dots — still a plain string)

- [ ] **Step 4.3: Rewrite SendInvitationAction**

Replace `modules/Users/app/Actions/SendInvitationAction.php` entirely:

```php
<?php

namespace App\Modules\Users\Actions;

use App\Services\InviteTokenService;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachInvitation;
use App\Modules\Users\Notifications\CoachInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SendInvitationAction
{
    private const MAX_PENDING = 20;
    private const EXPIRY_DAYS = 7;

    public function __construct(private readonly InviteTokenService $inviteToken) {}

    public function handle(Coach $coach, string $email): CoachInvitation
    {
        $invitation = DB::transaction(function () use ($coach, $email) {
            $pendingCount = $coach->pendingInvitations()->lockForUpdate()->count();

            if ($pendingCount >= self::MAX_PENDING) {
                throw ValidationException::withMessages([
                    'email' => ['Hai raggiunto il limite di inviti pendenti. Revoca quelli non utilizzati prima di inviarne altri.'],
                ]);
            }

            $coach->invitations()
                ->where('email', mb_strtolower($email))
                ->where('status', 'pending')
                ->update(['status' => 'revoked']);

            return CoachInvitation::create([
                'coach_id'   => $coach->id,
                'email'      => mb_strtolower($email),
                'token'      => $this->inviteToken->generate($coach->id, mb_strtolower($email)),
                'status'     => 'pending',
                'expires_at' => now()->addDays(self::EXPIRY_DAYS),
            ]);
        });

        Notification::route('mail', $invitation->email)
            ->notify(new CoachInvitationNotification($invitation, $coach));

        return $invitation;
    }
}
```

- [ ] **Step 4.4: Run test to confirm it passes**

```bash
php -d memory_limit=2048M artisan test --testsuite=Modules --filter='SendInvitationJwtTest' --compact
```

Expected: `PASS  3 / 3`

- [ ] **Step 4.5: Run Pint**

```bash
vendor/bin/pint modules/Users/app/Actions/SendInvitationAction.php --format agent
```

- [ ] **Step 4.6: Commit**

```bash
git add modules/Users/app/Actions/SendInvitationAction.php modules/Users/tests/Feature/SendInvitationJwtTest.php
git commit -m "feat(users): upgrade invite token to JWT 7d via InviteTokenService"
```

---

### Task 5: RegisterViaInviteRequest — profile fields

**Files:**
- Modify: `modules/Auth/app/Http/Requests/RegisterViaInviteRequest.php`

- [ ] **Step 5.1: Replace RegisterViaInviteRequest**

Replace `modules/Auth/app/Http/Requests/RegisterViaInviteRequest.php` entirely:

```php
<?php

namespace Modules\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterViaInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstName'    => ['required', 'string', 'max:255'],
            'lastName'     => ['required', 'string', 'max:255'],
            'password'     => [
                'required',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised(),
            ],
            'dateOfBirth'  => ['nullable', 'date', 'before:today'],
            'gender'       => ['nullable', 'string', 'in:male,female,other'],
            'heightCm'     => ['nullable', 'numeric', 'min:50', 'max:300'],
            'objective'    => ['nullable', 'string', 'max:255'],
            'fitnessLevel' => ['nullable', 'string', 'in:beginner,intermediate,advanced'],
        ];
    }
}
```

- [ ] **Step 5.2: Run Pint**

```bash
vendor/bin/pint modules/Auth/app/Http/Requests/RegisterViaInviteRequest.php --format agent
```

- [ ] **Step 5.3: Commit**

```bash
git add modules/Auth/app/Http/Requests/RegisterViaInviteRequest.php
git commit -m "feat(auth): add profile fields to RegisterViaInviteRequest"
```

---

### Task 6: RegisterViaInviteAction rewrite + InvitationController + route alias

**Files:**
- Modify: `modules/Auth/app/Actions/RegisterViaInviteAction.php`
- Modify: `modules/Auth/app/Http/Controllers/Api/V1/InvitationController.php`
- Modify: `modules/Auth/routes/api.php`
- Create: `modules/Auth/tests/Feature/InviteFlowTest.php`

- [ ] **Step 6.1: Write failing feature tests**

Create `modules/Auth/tests/Feature/InviteFlowTest.php`:

```php
<?php

namespace Modules\Auth\Tests\Feature;

use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachInvitation;
use App\Services\InviteTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeCoach(): Coach
    {
        $user = User::factory()->create();

        return Coach::factory()->create(['user_id' => $user->id]);
    }

    private function makeInvitation(Coach $coach, string $email, string $status = 'pending'): CoachInvitation
    {
        $token = app(InviteTokenService::class)->generate($coach->id, $email);

        return CoachInvitation::create([
            'coach_id'   => $coach->id,
            'email'      => $email,
            'token'      => $token,
            'status'     => $status,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_client_can_register_and_receives_tokens(): void
    {
        $coach = $this->makeCoach();
        $invitation = $this->makeInvitation($coach, 'newclient@example.com');

        $response = $this->postJson("/api/v1/auth/accept-invite/{$invitation->token}", [
            'firstName'             => 'Giulia',
            'lastName'              => 'Bianchi',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['access_token', 'token_type']);
        $response->assertJsonFragment(['token_type' => 'Bearer']);
        $this->assertNotEmpty($response->headers->getCookies());
    }

    public function test_user_and_client_profile_are_created(): void
    {
        $coach = $this->makeCoach();
        $invitation = $this->makeInvitation($coach, 'profile@example.com');

        $this->postJson("/api/v1/auth/accept-invite/{$invitation->token}", [
            'firstName'             => 'Giulia',
            'lastName'              => 'Bianchi',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
            'fitnessLevel'          => 'beginner',
            'objective'             => 'Perdere peso',
            'heightCm'              => 168,
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'profile@example.com',
            'name'  => 'Giulia Bianchi',
        ]);

        $user = User::where('email', 'profile@example.com')->first();
        $this->assertNotNull($user->email_verified_at, 'Invited user must be auto-verified');

        $this->assertDatabaseHas('clients', [
            'user_id'       => $user->id,
            'objective'     => 'Perdere peso',
            'fitness_level' => 'beginner',
            'height_cm'     => 168,
        ]);
    }

    public function test_invitation_is_marked_accepted(): void
    {
        $coach = $this->makeCoach();
        $invitation = $this->makeInvitation($coach, 'accept@example.com');

        $this->postJson("/api/v1/auth/accept-invite/{$invitation->token}", [
            'firstName'             => 'Mario',
            'lastName'              => 'Rossi',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $invitation->refresh();
        $this->assertSame('accepted', $invitation->status);
        $this->assertNotNull($invitation->accepted_at);
    }

    public function test_client_is_attached_to_coach(): void
    {
        $coach = $this->makeCoach();
        $invitation = $this->makeInvitation($coach, 'attached@example.com');

        $this->postJson("/api/v1/auth/accept-invite/{$invitation->token}", [
            'firstName'             => 'Anna',
            'lastName'              => 'Verdi',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $user = User::where('email', 'attached@example.com')->first();
        $client = Client::where('user_id', $user->id)->first();

        $this->assertTrue(
            $coach->athletes()->where('athlete_id', $client->id)->exists(),
            'Client must be linked to the coach with active status'
        );
    }

    public function test_returns_422_for_invalid_jwt(): void
    {
        $response = $this->postJson('/api/v1/auth/accept-invite/not-a-valid-jwt', [
            'firstName'             => 'Mario',
            'lastName'              => 'Rossi',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.token.0', 'Il link di invito non è valido o è scaduto.');
    }

    public function test_returns_422_for_already_accepted_invitation(): void
    {
        $coach = $this->makeCoach();
        $invitation = $this->makeInvitation($coach, 'used@example.com', 'accepted');

        $response = $this->postJson("/api/v1/auth/accept-invite/{$invitation->token}", [
            'firstName'             => 'Mario',
            'lastName'              => 'Rossi',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_returns_422_when_email_already_registered(): void
    {
        $coach = $this->makeCoach();
        $email = 'existing@example.com';
        User::factory()->create(['email' => $email]);
        $invitation = $this->makeInvitation($coach, $email);

        $response = $this->postJson("/api/v1/auth/accept-invite/{$invitation->token}", [
            'firstName'             => 'Mario',
            'lastName'              => 'Rossi',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('errors.token.0', 'Esiste già un account associato a questo invito.');
    }

    public function test_old_invite_route_still_works(): void
    {
        $coach = $this->makeCoach();
        $invitation = $this->makeInvitation($coach, 'oldroute@example.com');

        $response = $this->postJson("/api/v1/auth/invite/{$invitation->token}/register", [
            'firstName'             => 'Mario',
            'lastName'              => 'Rossi',
            'password'              => 'Secret123!',
            'password_confirmation' => 'Secret123!',
        ]);

        $response->assertStatus(201);
    }

    public function test_validate_endpoint_returns_correct_data(): void
    {
        $coach = $this->makeCoach();
        $invitation = $this->makeInvitation($coach, 'validate@example.com');

        $response = $this->getJson("/api/v1/auth/invite/{$invitation->token}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['valid' => true, 'email' => 'validate@example.com']);
    }
}
```

- [ ] **Step 6.2: Run tests to confirm they fail**

```bash
php -d memory_limit=2048M artisan test --testsuite=Modules --filter='InviteFlowTest' --compact
```

Expected: multiple failures (route 404, controller returns wrong response)

- [ ] **Step 6.3: Rewrite RegisterViaInviteAction**

Replace `modules/Auth/app/Actions/RegisterViaInviteAction.php` entirely:

```php
<?php

namespace Modules\Auth\Actions;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Actions\AttachAthleteToCoachAction;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachInvitation;
use App\Services\InviteTokenService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegisterViaInviteAction
{
    public function __construct(
        private readonly InviteTokenService $inviteToken,
        private readonly AttachAthleteToCoachAction $attach,
        private readonly AuditLogService $audit,
    ) {}

    public function handle(string $token, array $data): User
    {
        try {
            $payload = $this->inviteToken->decode($token);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'token' => ['Il link di invito non è valido o è scaduto.'],
            ]);
        }

        return DB::transaction(function () use ($token, $payload, $data) {
            $invitation = CoachInvitation::where('token', $token)
                ->lockForUpdate()
                ->first();

            if (! $invitation || ! $invitation->isPending()) {
                throw ValidationException::withMessages([
                    'token' => ['Il link di invito non è valido o è scaduto.'],
                ]);
            }

            if (User::where('email', $payload->email)->exists()) {
                throw ValidationException::withMessages([
                    'token' => ['Esiste già un account associato a questo invito.'],
                ]);
            }

            $user = User::create([
                'name'              => trim($data['firstName'] . ' ' . $data['lastName']),
                'email'             => $payload->email,
                'password'          => Hash::make($data['password']),
                'email_verified_at' => now(),
            ]);

            $user->assignRole(Role::CLIENT->value);

            $coach = Coach::findOrFail((int) $payload->coach_id);

            $client = Client::create([
                'user_id'       => $user->id,
                'joined_at'     => now(),
                'birth_date'    => $data['dateOfBirth'] ?? null,
                'gender'        => $data['gender'] ?? null,
                'height_cm'     => $data['heightCm'] ?? null,
                'objective'     => $data['objective'] ?? null,
                'fitness_level' => $data['fitnessLevel'] ?? null,
            ]);

            $this->attach->handle($coach, $client, 'active');

            $invitation->update([
                'status'      => 'accepted',
                'accepted_at' => now(),
            ]);

            $this->audit->log('CLIENT_CREATED', $coach->user_id, ['client_id' => $client->id]);

            return $user;
        });
    }
}
```

> **Note on namespace:** `AuditLogService` is imported as `App\Modules\Auth\Services\AuditLogService` — this matches the convention used in other modules when importing from Auth. If you get a class-not-found error, run `grep -r "class AuditLogService" modules/Auth/` to find the actual namespace and update the import.

- [ ] **Step 6.4: Update InvitationController**

Replace `modules/Auth/app/Http/Controllers/Api/V1/InvitationController.php` entirely:

```php
<?php

namespace Modules\Auth\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\CoachInvitation;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Actions\RegisterViaInviteAction;
use Modules\Auth\Http\Requests\RegisterViaInviteRequest;
use Modules\Auth\Services\JwtService;
use Modules\Auth\Services\RefreshTokenService;

class InvitationController extends Controller
{
    public function __construct(
        private readonly JwtService $jwt,
        private readonly RefreshTokenService $refreshTokens,
    ) {}

    public function validate(string $token): JsonResponse
    {
        $invitation = CoachInvitation::with('coach.user')->where('token', $token)->first();

        if (! $invitation || ! $invitation->isPending()) {
            return response()->json(['valid' => false, 'message' => 'Il link di invito non è valido o è scaduto.'], 422);
        }

        return response()->json([
            'valid'      => true,
            'email'      => $invitation->email,
            'coach_name' => $invitation->coach->user->name,
            'expires_at' => $invitation->expires_at->toISOString(),
        ]);
    }

    public function register(string $token, RegisterViaInviteRequest $request, RegisterViaInviteAction $action): JsonResponse
    {
        $user = $action->handle($token, $request->validated());

        $accessToken  = $this->jwt->generateAccessToken($user);
        $refreshToken = $this->refreshTokens->create($user, $request->input('device_name', 'invite'));

        return response()->json([
            'access_token' => $accessToken,
            'token_type'   => 'Bearer',
        ], 201)->withCookie(
            cookie(
                name: 'refresh_token',
                value: $refreshToken,
                minutes: (int) (config('auth.modules.jwt.refresh_ttl') / 60),
                path: '/',
                secure: true,
                httpOnly: true,
                sameSite: 'strict',
            )
        );
    }
}
```

- [ ] **Step 6.5: Add accept-invite route alias in api.php**

In `modules/Auth/routes/api.php`, find the existing `invite/{token}/register` route and add the new alias immediately after it:

```php
Route::post('invite/{token}/register', [InvitationController::class, 'register'])
    ->middleware('throttle:3,60')
    ->name('invite.register');

// Spec alias — same action, canonical path
Route::post('accept-invite/{token}', [InvitationController::class, 'register'])
    ->middleware('throttle:3,60')
    ->name('invite.accept');
```

- [ ] **Step 6.6: Run tests**

```bash
php -d memory_limit=2048M artisan test --testsuite=Modules --filter='InviteFlowTest' --compact
```

Expected: `PASS  9 / 9`

If any test fails with `Call to undefined method RefreshTokenService::create()`, check the actual method name with:

```bash
grep -n "public function" modules/Auth/app/Services/RefreshTokenService.php
```

Update the call in `InvitationController` to match the real method name.

- [ ] **Step 6.7: Run Pint**

```bash
vendor/bin/pint modules/Auth/app/Actions/RegisterViaInviteAction.php modules/Auth/app/Http/Controllers/Api/V1/InvitationController.php modules/Auth/routes/api.php --format agent
```

- [ ] **Step 6.8: Commit**

```bash
git add modules/Auth/app/Actions/RegisterViaInviteAction.php modules/Auth/app/Http/Controllers/Api/V1/InvitationController.php modules/Auth/app/Http/Requests/RegisterViaInviteRequest.php modules/Auth/routes/api.php modules/Auth/tests/Feature/InviteFlowTest.php
git commit -m "feat(auth): rewrite accept-invite — JWT validation, profile fields, direct login"
```

---

### Task 7: Full regression

- [ ] **Step 7.1: Run all module tests**

```bash
php -d memory_limit=2048M artisan test --testsuite=Modules --compact
```

Expected: all green. If pre-existing tests fail due to `SendInvitationAction` constructor change (it now requires `InviteTokenService`), find the test, and bind the service in the test:

```php
$action = new SendInvitationAction(app(\App\Services\InviteTokenService::class));
```

Or let Laravel container resolve it via `$this->app->make(SendInvitationAction::class)`.

- [ ] **Step 7.2: Run core tests**

```bash
php -d memory_limit=2048M artisan test --testsuite=Unit --compact
```

Expected: all green.

- [ ] **Step 7.3: Run full Pint pass**

```bash
vendor/bin/pint --dirty --format agent
```

- [ ] **Step 7.4: Commit any Pint fixes**

If Pint changed files:

```bash
git add -p
git commit -m "style: pint formatting"
```
