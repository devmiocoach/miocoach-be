# Client Discovery Flow — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable a client to register independently, browse and filter published coaches, and send a contact request; coach can accept (creating the coaching relationship) or decline.

**Architecture:** Client registration via existing `POST /auth/register/client` already works (Auth module). This plan adds: (1) automatic Client profile creation on registration via `Registered` event listener in UsersServiceProvider; (2) public paginated coach listing endpoint with filters; (3) CoachContactRequest model + contact/accept/decline flow. All new client-facing auth routes go through `role:client` middleware. Contact request acceptance calls `CreateClientAction` to bind the client to the coach.

**Tech Stack:** Laravel 13, PHP 8.3, Eloquent, Spatie Permissions, `Illuminate\Auth\Events\Registered`, PHPUnit feature tests.

---

## File Map

### Migrations (app/Modules/Users/database/migrations/)
| File | Action |
|---|---|
| `2026_04_20_300000_create_coach_contact_requests_table.php` | CREATE — id, coach_id, client_id, message, status, timestamps |

### Models
| File | Action |
|---|---|
| `src/Models/CoachContactRequest.php` | CREATE |
| `src/Models/Coach.php` | ADD contactRequests() relation |

### Notifications
| File | Action |
|---|---|
| `src/Notifications/CoachContactRequestNotification.php` | CREATE — email to coach when client contacts them |

### Actions
| File | Action |
|---|---|
| `src/Actions/SendContactRequestAction.php` | CREATE |
| `src/Actions/AcceptContactRequestAction.php` | CREATE — creates Client record |
| `src/Actions/DeclineContactRequestAction.php` | CREATE |

### Resources
| File | Action |
|---|---|
| `src/Http/Resources/CoachContactRequestResource.php` | CREATE |
| `src/Http/Resources/CoachListingResource.php` | CREATE — lighter than PublicCoachResource |

### Controllers
| File | Action |
|---|---|
| `src/Http/Controllers/Api/V1/CoachListingController.php` | CREATE — index() |
| `src/Http/Controllers/Api/V1/CoachContactRequestController.php` | CREATE — store, index (coach), update (coach) |

### Providers / Routes
| File | Action |
|---|---|
| `src/Providers/UsersServiceProvider.php` | ADD event listener for Registered |
| `routes/api.php` | ADD public GET /coaches route + authenticated contact request routes |

### Tests
| File | Action |
|---|---|
| `tests/Feature/Users/Concerns/WithClientUser.php` | CREATE — test helper |
| `tests/Feature/Users/CoachListingTest.php` | CREATE |
| `tests/Feature/Users/CoachContactRequestTest.php` | CREATE |

---

## Task 1: Client Profile Bootstrap on Registration

**Files:**
- Modify: `app/Modules/Users/src/Providers/UsersServiceProvider.php`
- Create: `tests/Feature/Users/Concerns/WithClientUser.php`
- Test: `tests/Feature/Users/CoachListingTest.php` (created below)

**Context:** When a user registers via `POST /auth/register/client` (Auth module), `RegisterAction` fires `Illuminate\Auth\Events\Registered`. At that point, no `Client` record exists. This plan adds a listener in UsersServiceProvider that creates the Client profile (with `coach_id = null`) so that `$request->user()->client` works immediately after registration. The `RegisterViaInviteAction` (Auth module) already creates a Client record with `coach_id` set, so the listener checks `if (!$user->client)` to avoid duplicates.

- [ ] **Step 1: Create WithClientUser test trait**

```php
<?php
// tests/Feature/Users/Concerns/WithClientUser.php

namespace Tests\Feature\Users\Concerns;

use App\Models\User;
use App\Modules\Users\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

trait WithClientUser
{
    use RefreshDatabase;

    protected function createStandaloneClient(array $userAttrs = []): array
    {
        $user = User::factory()->create(array_merge(
            ['email_verified_at' => now()],
            $userAttrs
        ));

        $role = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user->assignRole($role);

        $client            = new Client();
        $client->user_id   = $user->id;
        $client->joined_at = now();
        $client->save();

        return [$user, $client];
    }

    protected function actingAsClient(User $user): static
    {
        return $this->actingAs($user)->withoutMiddleware([
            \App\Modules\Auth\Http\Middleware\JwtAuthenticate::class,
            \App\Modules\Auth\Http\Middleware\RequireVerified::class,
            \Spatie\Permission\Middleware\RoleMiddleware::class,
        ]);
    }
}
```

- [ ] **Step 2: Write failing test**

```php
<?php
// tests/Feature/Users/CoachListingTest.php

namespace Tests\Feature\Users;

use App\Models\User;
use App\Modules\Users\Models\Client;
use Illuminate\Auth\Events\Registered;
use Spatie\Permission\Models\Role;
use Tests\Feature\Users\Concerns\WithClientUser;
use Tests\TestCase;

class CoachListingTest extends TestCase
{
    use WithClientUser;

    public function test_client_profile_created_on_registration(): void
    {
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user->assignRole($role);

        $this->assertNull($user->fresh()->client);

        event(new Registered($user));

        $this->assertNotNull($user->fresh()->client);
        $this->assertNull($user->fresh()->client->coach_id);
    }

    public function test_registration_listener_does_not_duplicate_client(): void
    {
        // If client already exists (e.g., created by RegisterViaInviteAction), listener skips
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $user->assignRole($role);

        $existing            = new Client();
        $existing->user_id   = $user->id;
        $existing->joined_at = now();
        $existing->save();

        event(new Registered($user));

        $this->assertCount(1, Client::where('user_id', $user->id)->get());
    }
}
```

- [ ] **Step 3: Run to verify failure**

```bash
php artisan test tests/Feature/Users/CoachListingTest.php --filter="profile_created|does_not_duplicate"
```
Expected: FAIL — listener does not exist.

- [ ] **Step 4: Add event listener to UsersServiceProvider**

Replace `app/Modules/Users/src/Providers/UsersServiceProvider.php`:

```php
<?php

namespace App\Modules\Users\Providers;

use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Services\EncryptionService;
use App\Modules\Users\Services\GeocodingService;
use App\Modules\Users\Services\ObjectStorageService;
use App\Modules\Users\Services\SlugService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeocodingService::class);
        $this->app->singleton(SlugService::class);
        $this->app->singleton(ObjectStorageService::class);
        $this->app->singleton(EncryptionService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        Route::middleware('api')->group(__DIR__ . '/../../routes/api.php');

        // Auto-create Client profile when a client-role user registers independently
        Event::listen(Registered::class, function (Registered $event) {
            /** @var User $user */
            $user = $event->user;

            if (! $user->hasRole('client')) {
                return;
            }

            // Guard: RegisterViaInviteAction already creates the Client record
            if ($user->client !== null) {
                return;
            }

            $client            = new Client();
            $client->user_id   = $user->id;
            $client->joined_at = now();
            $client->save();
        });
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/Users/CoachListingTest.php --filter="profile_created|does_not_duplicate"
```
Expected: 2 PASS

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Users/src/Providers/ tests/Feature/Users/Concerns/
git commit -m "feat(users): auto-create Client profile on client registration via Registered event listener"
```

---

## Task 2: GET /api/v1/coaches — Public Coach Listing

**Files:**
- Create: `app/Modules/Users/src/Http/Resources/CoachListingResource.php`
- Create: `app/Modules/Users/src/Http/Controllers/Api/V1/CoachListingController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Test: `tests/Feature/Users/CoachListingTest.php`

**Context:** Returns only `is_published = true` coaches. No auth required. Same public-facing route group as `GET /coaches/{coach}/public`. Supports filters: `search`, `specialization[]`, `city`, `mode`, `price_max`, `languages[]`. Throttle: 120/min.

- [ ] **Step 1: Write failing tests**

```php
// Append to tests/Feature/Users/CoachListingTest.php

use App\Modules\Users\Models\Coach;
use Tests\Feature\Users\Concerns\WithCoachUser;

// Add trait to class:
// use WithCoachUser; (add alongside WithClientUser)

public function test_public_can_list_published_coaches(): void
{
    [$u1, $c1] = $this->createCoachUser(['name' => 'Marta Coach'], ['is_published' => true, 'city' => 'Milano']);
    [$u2, $c2] = $this->createCoachUser(['name' => 'Luca Coach'], ['is_published' => false]);

    $response = $this->getJson('/api/v1/coaches');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    $this->assertContains($c1->id, $ids);
    $this->assertNotContains($c2->id, $ids);
}

public function test_coach_listing_filters_by_city(): void
{
    [$u1, $milan]  = $this->createCoachUser([], ['is_published' => true, 'city' => 'Milano']);
    [$u2, $rome]   = $this->createCoachUser([], ['is_published' => true, 'city' => 'Roma']);

    $response = $this->getJson('/api/v1/coaches?city=Milano');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    $this->assertContains($milan->id, $ids);
    $this->assertNotContains($rome->id, $ids);
}

public function test_coach_listing_filters_by_mode(): void
{
    [$u1, $online]  = $this->createCoachUser([], ['is_published' => true, 'mode' => 'online']);
    [$u2, $person]  = $this->createCoachUser([], ['is_published' => true, 'mode' => 'in_person']);

    $response = $this->getJson('/api/v1/coaches?mode=online');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    $this->assertContains($online->id, $ids);
    $this->assertNotContains($person->id, $ids);
}

public function test_coach_listing_filters_by_max_price(): void
{
    [$u1, $cheap]     = $this->createCoachUser([], ['is_published' => true, 'price_per_session' => 30]);
    [$u2, $expensive] = $this->createCoachUser([], ['is_published' => true, 'price_per_session' => 100]);

    $response = $this->getJson('/api/v1/coaches?price_max=50');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    $this->assertContains($cheap->id, $ids);
    $this->assertNotContains($expensive->id, $ids);
}

public function test_coach_listing_search_by_name(): void
{
    [$u1, $c1] = $this->createCoachUser(['name' => 'Federica Neri'], ['is_published' => true]);
    [$u2, $c2] = $this->createCoachUser(['name' => 'Paolo Bianchi'], ['is_published' => true]);

    $response = $this->getJson('/api/v1/coaches?search=Federica');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id');
    $this->assertContains($c1->id, $ids);
    $this->assertNotContains($c2->id, $ids);
}
```

Update `CoachListingTest` class to use both traits:
```php
class CoachListingTest extends TestCase
{
    use WithClientUser, WithCoachUser;
    // ...
}
```

- [ ] **Step 2: Run to verify failure**

```bash
php artisan test tests/Feature/Users/CoachListingTest.php --filter="public_can_list|filters_by"
```
Expected: FAIL — route does not exist.

- [ ] **Step 3: Create CoachListingResource**

```php
<?php
// app/Modules/Users/src/Http/Resources/CoachListingResource.php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'display_name'       => $this->user?->name ?? $this->slug,
            'tagline'            => $this->tagline,
            'bio_excerpt'        => mb_substr($this->bio ?? '', 0, 120),
            'city'               => $this->city,
            'mode'               => $this->mode,
            'specializations'    => $this->specializations,
            'languages'          => $this->languages,
            'price_per_session'  => $this->price_per_session,
            'hourly_rate'        => $this->hourly_rate,
            'years_of_experience' => $this->years_of_experience,
            'is_verified'        => $this->is_verified,
            'avatar_url'         => null, // Coach avatar — future enhancement
            // Placeholders — Reviews module
            'rating'             => null,
            'reviews_count'      => 0,
        ];
    }
}
```

- [ ] **Step 4: Create CoachListingController**

```php
<?php
// app/Modules/Users/src/Http/Controllers/Api/V1/CoachListingController.php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Http\Resources\CoachListingResource;
use App\Modules\Users\Models\Coach;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachListingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Coach::query()
            ->where('is_published', true)
            ->with('user');

        // Search: display name, tagline, or bio
        if ($request->filled('search')) {
            $term = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($term) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'LIKE', $term))
                  ->orWhere('tagline', 'LIKE', $term)
                  ->orWhere('bio', 'LIKE', $term);
            });
        }

        // Filter: city (case-insensitive contains)
        if ($request->filled('city')) {
            $query->where('city', 'ILIKE', '%' . $request->input('city') . '%');
        }

        // Filter: mode
        if ($request->filled('mode')) {
            $query->where('mode', $request->input('mode'));
        }

        // Filter: max price per session
        if ($request->filled('price_max')) {
            $query->where('price_per_session', '<=', (float) $request->input('price_max'));
        }

        // Filter: specialization[] — coach must have ALL provided
        if ($request->filled('specialization')) {
            foreach ((array) $request->input('specialization') as $spec) {
                $query->whereJsonContains('specializations', $spec);
            }
        }

        // Filter: languages[] — coach must speak ALL provided
        if ($request->filled('languages')) {
            foreach ((array) $request->input('languages') as $lang) {
                $query->whereJsonContains('languages', $lang);
            }
        }

        $limit   = min((int) $request->input('limit', 20), 50);
        $coaches = $query->latest('id')->paginate($limit);

        return response()->json(CoachListingResource::collection($coaches)->response()->getData(true));
    }
}
```

- [ ] **Step 5: Add route to the unauthenticated group**

In `app/Modules/Users/routes/api.php`, find the unauthenticated group at the bottom and add:

```php
Route::prefix('api/v1')->middleware('api')->group(function () {
    Route::get('coaches', [CoachListingController::class, 'index'])
        ->middleware('throttle:120,1')
        ->name('coaches.index');

    Route::get('coaches/{coach}/public', [PublicCoachController::class, 'show'])
        ->middleware('throttle:120,1')
        ->name('coaches.public');
});
```

Add import: `use App\Modules\Users\Http\Controllers\Api\V1\CoachListingController;`

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Users/CoachListingTest.php --filter="public_can_list|filters_by|search_by"
```
Expected: 5 PASS

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Users/src/ app/Modules/Users/routes/ tests/
git commit -m "feat(users): GET /api/v1/coaches — public coach listing with search, city, mode, price, specialization, language filters"
```

---

## Task 3: CoachContactRequest Model + Migration

**Files:**
- Create: `app/Modules/Users/database/migrations/2026_04_20_300000_create_coach_contact_requests_table.php`
- Create: `app/Modules/Users/src/Models/CoachContactRequest.php`
- Modify: `app/Modules/Users/src/Models/Coach.php`

- [ ] **Step 1: Write failing model test**

```php
// Append to tests/Feature/Users/CoachContactRequestTest.php (file created in Task 4)
// For now just confirm the migration runs correctly — done via artisan migrate
```

- [ ] **Step 2: Create migration**

```php
<?php
// 2026_04_20_300000_create_coach_contact_requests_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('coach_contact_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'accepted', 'declined'])->default('pending');
            $table->timestamps();

            // A client can only have one pending request to the same coach
            $table->unique(['coach_id', 'client_id']);
            $table->index('coach_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_contact_requests');
    }
};
```

- [ ] **Step 3: Create CoachContactRequest model**

```php
<?php
// app/Modules/Users/src/Models/CoachContactRequest.php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachContactRequest extends Model
{
    protected $fillable = ['message', 'status'];

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
```

- [ ] **Step 4: Add relation to Coach model**

Open `app/Modules/Users/src/Models/Coach.php` and add inside the class:

```php
public function contactRequests(): HasMany
{
    return $this->hasMany(CoachContactRequest::class);
}

public function pendingContactRequests(): HasMany
{
    return $this->hasMany(CoachContactRequest::class)->where('status', 'pending');
}
```

Also add the import at the top of the file:
```php
use Illuminate\Database\Eloquent\Relations\HasMany; // already present
// No new import needed — CoachContactRequest is in same namespace
```

- [ ] **Step 5: Run migration**

```bash
php artisan migrate
```
Expected: 1 new migration runs.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Users/database/migrations/ app/Modules/Users/src/Models/
git commit -m "feat(users): add CoachContactRequest model and migration"
```

---

## Task 4: POST /api/v1/coaches/:id/contact-request

**Files:**
- Create: `app/Modules/Users/src/Notifications/CoachContactRequestNotification.php`
- Create: `app/Modules/Users/src/Actions/SendContactRequestAction.php`
- Create: `app/Modules/Users/src/Http/Resources/CoachContactRequestResource.php`
- Create: `app/Modules/Users/src/Http/Controllers/Api/V1/CoachContactRequestController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Test: `tests/Feature/Users/CoachContactRequestTest.php`

**Context:** An authenticated client (role:client) sends a contact request to a coach. The coach receives an email notification. A client can only have one active request per coach (unique constraint on coach_id + client_id).

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Users/CoachContactRequestTest.php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\CoachContactRequest;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Users\Concerns\WithClientUser;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachContactRequestTest extends TestCase
{
    use WithClientUser, WithCoachUser;

    public function test_client_can_send_contact_request_to_coach(): void
    {
        Notification::fake();

        [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => true]);
        [$clientUser, $client] = $this->createStandaloneClient();

        $response = $this->actingAsClient($clientUser)
            ->postJson("/api/v1/coaches/{$coach->id}/contact-request", [
                'message' => 'Vorrei iniziare un percorso di allenamento.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('coach_contact_requests', [
            'coach_id'  => $coach->id,
            'client_id' => $client->id,
            'status'    => 'pending',
        ]);

        Notification::assertSentOnDemand(
            \App\Modules\Users\Notifications\CoachContactRequestNotification::class
        );
    }

    public function test_client_cannot_send_duplicate_contact_request(): void
    {
        [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => true]);
        [$clientUser, $client] = $this->createStandaloneClient();

        $this->actingAsClient($clientUser)
            ->postJson("/api/v1/coaches/{$coach->id}/contact-request", ['message' => 'First']);

        $response = $this->actingAsClient($clientUser)
            ->postJson("/api/v1/coaches/{$coach->id}/contact-request", ['message' => 'Second']);

        $response->assertUnprocessable();
    }

    public function test_client_cannot_contact_unpublished_coach(): void
    {
        [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => false]);
        [$clientUser, $client] = $this->createStandaloneClient();

        $response = $this->actingAsClient($clientUser)
            ->postJson("/api/v1/coaches/{$coach->id}/contact-request");

        $response->assertNotFound();
    }
}
```

- [ ] **Step 2: Run to verify failure**

```bash
php artisan test tests/Feature/Users/CoachContactRequestTest.php --filter="send_contact|duplicate|unpublished"
```
Expected: FAIL — route does not exist.

- [ ] **Step 3: Create CoachContactRequestNotification**

```php
<?php
// app/Modules/Users/src/Notifications/CoachContactRequestNotification.php

namespace App\Modules\Users\Notifications;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CoachContactRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Client $client,
        private readonly Coach $coach,
        private readonly ?string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $clientName  = $this->client->user?->name ?? 'Un cliente';
        $frontendUrl = rtrim(config('app.frontend_url', config('app.url')), '/');
        $requestsUrl = $frontendUrl . '/coach/contact-requests';

        $mail = (new MailMessage)
            ->subject("{$clientName} vuole lavorare con te")
            ->greeting("Ciao {$this->coach->user?->name}!")
            ->line("{$clientName} ti ha inviato una richiesta di coaching.");

        if ($this->message) {
            $mail->line("Messaggio: \"{$this->message}\"");
        }

        return $mail
            ->action('Visualizza richiesta', $requestsUrl)
            ->line('Puoi accettare o rifiutare la richiesta dalla tua dashboard.');
    }
}
```

- [ ] **Step 4: Create SendContactRequestAction**

```php
<?php
// app/Modules/Users/src/Actions/SendContactRequestAction.php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachContactRequest;
use App\Modules\Users\Notifications\CoachContactRequestNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SendContactRequestAction
{
    public function handle(Coach $coach, Client $client, ?string $message): CoachContactRequest
    {
        if (! $coach->is_published) {
            abort(404, 'Coach not found.');
        }

        $alreadyExists = CoachContactRequest::where('coach_id', $coach->id)
            ->where('client_id', $client->id)
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'coach_id' => ['Hai già una richiesta in corso con questo coach.'],
            ]);
        }

        $contactRequest = new CoachContactRequest();
        $contactRequest->coach_id  = $coach->id;
        $contactRequest->client_id = $client->id;
        $contactRequest->message   = $message;
        $contactRequest->status    = 'pending';
        $contactRequest->save();

        // Send email notification to coach
        Notification::route('mail', $coach->user->email)
            ->notify(new CoachContactRequestNotification($client, $coach, $message));

        return $contactRequest;
    }
}
```

- [ ] **Step 5: Create CoachContactRequestResource**

```php
<?php
// app/Modules/Users/src/Http/Resources/CoachContactRequestResource.php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachContactRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'status'     => $this->status,
            'message'    => $this->message,
            'created_at' => $this->created_at->toISOString(),
            'client'     => $this->whenLoaded('client', fn () => [
                'id'         => $this->client->id,
                'name'       => $this->client->user?->name,
                'email'      => $this->client->user?->email,
                'avatar_url' => $this->client->avatar_url,
            ]),
        ];
    }
}
```

- [ ] **Step 6: Create CoachContactRequestController::store()**

```php
<?php
// app/Modules/Users/src/Http/Controllers/Api/V1/CoachContactRequestController.php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\AcceptContactRequestAction;
use App\Modules\Users\Actions\DeclineContactRequestAction;
use App\Modules\Users\Actions\SendContactRequestAction;
use App\Modules\Users\Http\Resources\CoachContactRequestResource;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachContactRequestController extends Controller
{
    public function store(Request $request, Coach $coach, SendContactRequestAction $action): JsonResponse
    {
        $request->validate([
            'message' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $client = $request->user()->client;

        if (! $client) {
            return response()->json(['message' => 'Profilo cliente non trovato.'], 404);
        }

        $contactRequest = $action->handle($coach, $client, $request->input('message'));

        return response()->json(['data' => new CoachContactRequestResource($contactRequest)], 201);
    }

    public function index(Request $request): JsonResponse
    {
        // Implemented in Task 5
        return response()->json(['data' => []]);
    }

    public function update(Request $request, CoachContactRequest $contactRequest): JsonResponse
    {
        // Implemented in Task 5
        return response()->json(['data' => []]);
    }
}
```

- [ ] **Step 7: Add client-facing route**

In `app/Modules/Users/routes/api.php`, inside the authenticated group, add a new sub-group for client role:

```php
// Client-facing routes
Route::prefix('coaches')->middleware('role:client')->group(function () {
    Route::post('{coach}/contact-request', [CoachContactRequestController::class, 'store'])
        ->middleware('throttle:10,60')
        ->name('coaches.contact-request.store');
});
```

Add import: `use App\Modules\Users\Http\Controllers\Api\V1\CoachContactRequestController;`

- [ ] **Step 8: Run tests**

```bash
php artisan test tests/Feature/Users/CoachContactRequestTest.php --filter="send_contact|duplicate|unpublished"
```
Expected: 3 PASS

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Users/src/ app/Modules/Users/routes/ tests/
git commit -m "feat(users): POST /coaches/:id/contact-request — client initiates coaching relationship"
```

---

## Task 5: GET + PUT /coaches/me/contact-requests — Coach Manages Requests

**Files:**
- Create: `app/Modules/Users/src/Actions/AcceptContactRequestAction.php`
- Create: `app/Modules/Users/src/Actions/DeclineContactRequestAction.php`
- Modify: `app/Modules/Users/src/Http/Controllers/Api/V1/CoachContactRequestController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Test: `tests/Feature/Users/CoachContactRequestTest.php`

**Context:** The coach sees a list of incoming contact requests and can accept or decline. Accepting calls `CreateClientAction` to establish the coaching relationship (sets `client.coach_id`). Declining just marks status = 'declined'. Both actions are idempotent — operating on an already-processed request returns 422.

- [ ] **Step 1: Write failing tests**

```php
// Append to tests/Feature/Users/CoachContactRequestTest.php

public function test_coach_can_list_contact_requests(): void
{
    [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => true]);
    [$clientUser, $client] = $this->createStandaloneClient();

    $req = new CoachContactRequest();
    $req->coach_id  = $coach->id;
    $req->client_id = $client->id;
    $req->message   = 'Voglio allenarti';
    $req->status    = 'pending';
    $req->save();

    $response = $this->actingAsCoach($coachUser)
        ->getJson('/api/v1/coaches/me/contact-requests');

    $response->assertOk();
    $this->assertCount(1, $response->json('data'));
}

public function test_coach_can_accept_contact_request(): void
{
    [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => true]);
    [$clientUser, $client] = $this->createStandaloneClient();

    $req = new CoachContactRequest();
    $req->coach_id  = $coach->id;
    $req->client_id = $client->id;
    $req->status    = 'pending';
    $req->save();

    $response = $this->actingAsCoach($coachUser)
        ->putJson("/api/v1/coaches/me/contact-requests/{$req->id}", [
            'action' => 'accept',
        ]);

    $response->assertOk()->assertJsonPath('data.status', 'accepted');

    // The client now belongs to the coach
    $this->assertDatabaseHas('clients', [
        'id'       => $client->id,
        'coach_id' => $coach->id,
    ]);
}

public function test_coach_can_decline_contact_request(): void
{
    [$coachUser, $coach] = $this->createCoachUser([], ['is_published' => true]);
    [$clientUser, $client] = $this->createStandaloneClient();

    $req = new CoachContactRequest();
    $req->coach_id  = $coach->id;
    $req->client_id = $client->id;
    $req->status    = 'pending';
    $req->save();

    $response = $this->actingAsCoach($coachUser)
        ->putJson("/api/v1/coaches/me/contact-requests/{$req->id}", [
            'action' => 'decline',
        ]);

    $response->assertOk()->assertJsonPath('data.status', 'declined');

    // Client NOT linked to coach
    $this->assertDatabaseMissing('clients', [
        'id'       => $client->id,
        'coach_id' => $coach->id,
    ]);
}
```

- [ ] **Step 2: Run to verify failure**

```bash
php artisan test tests/Feature/Users/CoachContactRequestTest.php --filter="list_contact|accept|decline"
```
Expected: FAIL — route stubs return empty.

- [ ] **Step 3: Create AcceptContactRequestAction**

```php
<?php
// app/Modules/Users/src/Actions/AcceptContactRequestAction.php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\CoachContactRequest;
use Illuminate\Validation\ValidationException;

class AcceptContactRequestAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(CoachContactRequest $contactRequest): CoachContactRequest
    {
        if (! $contactRequest->isPending()) {
            throw ValidationException::withMessages([
                'action' => ['Questa richiesta è già stata elaborata.'],
            ]);
        }

        $client = $contactRequest->client;
        $coach  = $contactRequest->coach;

        // Bind client to coach
        $client->coach_id  = $coach->id;
        $client->joined_at = now();
        $client->save();

        $contactRequest->update(['status' => 'accepted']);

        $this->audit->log('CLIENT_CREATED', $coach->user_id, [
            'client_id' => $client->id,
            'via'       => 'contact_request',
        ]);

        return $contactRequest->fresh();
    }
}
```

- [ ] **Step 4: Create DeclineContactRequestAction**

```php
<?php
// app/Modules/Users/src/Actions/DeclineContactRequestAction.php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\CoachContactRequest;
use Illuminate\Validation\ValidationException;

class DeclineContactRequestAction
{
    public function handle(CoachContactRequest $contactRequest): CoachContactRequest
    {
        if (! $contactRequest->isPending()) {
            throw ValidationException::withMessages([
                'action' => ['Questa richiesta è già stata elaborata.'],
            ]);
        }

        $contactRequest->update(['status' => 'declined']);

        return $contactRequest->fresh();
    }
}
```

- [ ] **Step 5: Replace CoachContactRequestController with full implementation**

Replace the full content of `app/Modules/Users/src/Http/Controllers/Api/V1/CoachContactRequestController.php`:

```php
<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\AcceptContactRequestAction;
use App\Modules\Users\Actions\DeclineContactRequestAction;
use App\Modules\Users\Actions\SendContactRequestAction;
use App\Modules\Users\Http\Resources\CoachContactRequestResource;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachContactRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachContactRequestController extends Controller
{
    // Called by client: POST /api/v1/coaches/:id/contact-request
    public function store(Request $request, Coach $coach, SendContactRequestAction $action): JsonResponse
    {
        $request->validate([
            'message' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ]);

        $client = $request->user()->client;

        if (! $client) {
            return response()->json(['message' => 'Profilo cliente non trovato.'], 404);
        }

        $contactRequest = $action->handle($coach, $client, $request->input('message'));

        return response()->json(['data' => new CoachContactRequestResource($contactRequest)], 201);
    }

    // Called by coach: GET /api/v1/coaches/me/contact-requests
    public function index(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $status = $request->input('status', 'pending');

        $requests = $coach->contactRequests()
            ->with('client.user')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20);

        return response()->json(CoachContactRequestResource::collection($requests)->response()->getData(true));
    }

    // Called by coach: PUT /api/v1/coaches/me/contact-requests/:id
    public function update(
        Request $request,
        CoachContactRequest $contactRequest,
        AcceptContactRequestAction $accept,
        DeclineContactRequestAction $decline,
    ): JsonResponse {
        $request->validate([
            'action' => ['required', 'in:accept,decline'],
        ]);

        $coach = $request->user()->coach;

        if (! $coach || $contactRequest->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $updated = $request->input('action') === 'accept'
            ? $accept->handle($contactRequest)
            : $decline->handle($contactRequest);

        return response()->json(['data' => new CoachContactRequestResource($updated->load('client.user'))]);
    }
}
```

- [ ] **Step 6: Add coach-side routes**

In `app/Modules/Users/routes/api.php`, inside the `coaches/me` prefix group, add:

```php
Route::get('contact-requests', [CoachContactRequestController::class, 'index'])->middleware('throttle:60,1')->name('contact-requests.index');
Route::put('contact-requests/{contactRequest}', [CoachContactRequestController::class, 'update'])->middleware('throttle:30,1')->name('contact-requests.update');
```

- [ ] **Step 7: Run all contact request tests**

```bash
php artisan test tests/Feature/Users/CoachContactRequestTest.php
```
Expected: all 6 PASS

- [ ] **Step 8: Run full test suite**

```bash
php artisan test --filter=Users
```
Expected: all tests pass, 0 failures.

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Users/src/ app/Modules/Users/routes/ tests/
git commit -m "feat(users): GET/PUT /coaches/me/contact-requests — coach accepts/declines, accept binds client"
```

---

## Final: Full suite + cleanup

- [ ] **Run all tests**

```bash
php artisan test
```
Expected: all pass, 0 failures.

- [ ] **Final commit**

```bash
git commit --allow-empty -m "test(users): finalize client discovery flow test suite"
```
