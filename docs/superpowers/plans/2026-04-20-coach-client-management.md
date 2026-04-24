# Coach Client Management — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement all missing coach-side client management endpoints: list with filters, create (with email-lookup + invite), full client card, update, delete, tags, notes, AES-256-GCM anamnesis, and private file upload/download via R2 presigned URL.

**Architecture:** Action pattern (controllers → Actions → Services/Models). New models ClientNote, ClientAnamnesis, ClientFile. EncryptionService wraps OpenSSL AES-256-GCM. File uploads use existing ObjectStorageService with `'private'` visibility. All coach-side routes live under `/api/v1/coaches/me` with `role:coach` middleware. Tests use Storage::fake + Http::fake; anamnesis tests verify encrypted storage.

**Tech Stack:** Laravel 13, PHP 8.3, Spatie Permissions, league/flysystem-aws-s3-v3 (R2), OpenSSL AES-256-GCM, PHPUnit feature tests.

---

## File Map

### Migrations (app/Modules/Users/database/migrations/)
| File | Action |
|---|---|
| `2026_04_20_200000_add_fields_to_clients_table.php` | ADD tags, phone, avatar_url, subscription_expires_at, subscription_sessions_remaining |
| `2026_04_20_200001_create_client_notes_table.php` | CREATE client_notes (id, coach_id, client_id, content, created_at) |
| `2026_04_20_200002_create_client_anamnesis_table.php` | CREATE client_anamnesis (id, client_id UNIQUE, content_encrypted, iv, tag, timestamps) |
| `2026_04_20_200003_create_client_files_table.php` | CREATE client_files (id, coach_id, client_id, storage_key, name, mime_type, size, timestamps) |
| `2026_04_20_200004_drop_anamnesi_from_clients_table.php` | DROP clients.anamnesi (old Laravel-encrypted column) |

### Models
| File | Action |
|---|---|
| `src/Models/Client.php` | UPDATE fillable, casts, relations (notes, anamnesis, files) |
| `src/Models/ClientNote.php` | CREATE — immutable, no updated_at |
| `src/Models/ClientAnamnesis.php` | CREATE — hidden encrypted fields |
| `src/Models/ClientFile.php` | CREATE — private R2 storage |

### Services
| File | Action |
|---|---|
| `src/Services/EncryptionService.php` | CREATE — AES-256-GCM encrypt/decrypt |
| `src/Services/ObjectStorageService.php` | UPDATE — add `$visibility = 'public'` param |
| `src/Providers/UsersServiceProvider.php` | UPDATE — register EncryptionService |

### Actions
| File | Action |
|---|---|
| `src/Actions/CreateClientAction.php` | UPDATE — email lookup, SendInvitationAction for new emails, CLIENT_CREATED audit |
| `src/Actions/UpdateCoachClientAction.php` | CREATE — update client+user fields, CLIENT_UPDATED audit |
| `src/Actions/DeleteClientAction.php` | CREATE — soft delete, CLIENT_DELETED audit |
| `src/Actions/AddClientTagAction.php` | CREATE — JSON array push |
| `src/Actions/RemoveClientTagAction.php` | CREATE — JSON array filter |
| `src/Actions/AddClientNoteAction.php` | CREATE — create immutable note |
| `src/Actions/UpdateClientAnamnesisAction.php` | CREATE — AES-256-GCM encrypt, updateOrCreate |
| `src/Actions/StoreClientFileAction.php` | CREATE — private R2 upload |

### Requests
| File | Action |
|---|---|
| `src/Http/Requests/CreateClientRequest.php` | CREATE |
| `src/Http/Requests/UpdateCoachClientRequest.php` | CREATE |
| `src/Http/Requests/StoreClientNoteRequest.php` | CREATE |
| `src/Http/Requests/UpdateClientAnamnesisRequest.php` | CREATE |
| `src/Http/Requests/StoreClientFileRequest.php` | CREATE |

### Resources
| File | Action |
|---|---|
| `src/Http/Resources/ClientResource.php` | UPDATE — flatten user fields, add new columns |
| `src/Http/Resources/ClientDetailResource.php` | CREATE — full card with notes, anamnesis, files |
| `src/Http/Resources/ClientNoteResource.php` | CREATE |
| `src/Http/Resources/ClientFileResource.php` | CREATE |

### Controllers
| File | Action |
|---|---|
| `src/Http/Controllers/Api/V1/CoachController.php` | UPDATE — `clients()` with filters |
| `src/Http/Controllers/Api/V1/CoachClientController.php` | CREATE — show, update, destroy |
| `src/Http/Controllers/Api/V1/ClientTagController.php` | CREATE — store, destroy |
| `src/Http/Controllers/Api/V1/ClientNoteController.php` | CREATE — store |
| `src/Http/Controllers/Api/V1/ClientAnamnesisController.php` | CREATE — update |
| `src/Http/Controllers/Api/V1/ClientFileController.php` | CREATE — store, download |

### Routes / Config
| File | Action |
|---|---|
| `routes/api.php` | ADD new routes under `/coaches/me` |
| `config/app.php` | ADD `'encryption_key' => env('APP_ENCRYPTION_KEY', '')` |
| `.env.example` | ADD `APP_ENCRYPTION_KEY=` |

### Tests
| File | Action |
|---|---|
| `tests/Feature/Users/Concerns/WithCoachUser.php` | ADD `createClientForCoach()` helper |
| `tests/Feature/Users/CoachClientManagementTest.php` | CREATE |
| `tests/Feature/Users/CoachClientTagTest.php` | CREATE |
| `tests/Feature/Users/CoachClientNoteTest.php` | CREATE |
| `tests/Feature/Users/CoachClientAnamnesisTest.php` | CREATE |
| `tests/Feature/Users/CoachClientFileTest.php` | CREATE |

---

## Task 1: DB Migrations

**Files:**
- Create: `app/Modules/Users/database/migrations/2026_04_20_200000_add_fields_to_clients_table.php`
- Create: `app/Modules/Users/database/migrations/2026_04_20_200001_create_client_notes_table.php`
- Create: `app/Modules/Users/database/migrations/2026_04_20_200002_create_client_anamnesis_table.php`
- Create: `app/Modules/Users/database/migrations/2026_04_20_200003_create_client_files_table.php`
- Create: `app/Modules/Users/database/migrations/2026_04_20_200004_drop_anamnesi_from_clients_table.php`

- [ ] **Step 1: Create migration — add fields to clients**

```php
<?php
// 2026_04_20_200000_add_fields_to_clients_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('goals');
            $table->string('phone', 30)->nullable()->after('tags');
            $table->string('avatar_url')->nullable()->after('phone');
            $table->timestamp('subscription_expires_at')->nullable()->after('avatar_url');
            $table->unsignedSmallInteger('subscription_sessions_remaining')->nullable()->after('subscription_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['tags', 'phone', 'avatar_url', 'subscription_expires_at', 'subscription_sessions_remaining']);
        });
    }
};
```

- [ ] **Step 2: Create migration — client_notes**

```php
<?php
// 2026_04_20_200001_create_client_notes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamp('created_at'); // immutable — no updated_at
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_notes');
    }
};
```

- [ ] **Step 3: Create migration — client_anamnesis**

```php
<?php
// 2026_04_20_200002_create_client_anamnesis_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_anamnesis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete()->unique();
            $table->text('content_encrypted');
            $table->string('iv', 64);  // base64(12 random bytes) = 16 chars, 64 is safe
            $table->string('tag', 64); // base64(16-byte GCM auth tag) = 24 chars
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_anamnesis');
    }
};
```

- [ ] **Step 4: Create migration — client_files**

```php
<?php
// 2026_04_20_200003_create_client_files_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('storage_key');   // R2 path: client-files/{coachId}/{clientId}/{uuid}.{ext}
            $table->string('name');          // display name
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size'); // bytes
            $table->timestamps();
            $table->index('client_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_files');
    }
};
```

- [ ] **Step 5: Create migration — drop old anamnesi column**

```php
<?php
// 2026_04_20_200004_drop_anamnesi_from_clients_table.php
// NOTE: This drops the old Laravel-encrypted `anamnesi` JSON column.
// In production, migrate data to client_anamnesis before running this.
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('anamnesi');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('anamnesi')->nullable();
        });
    }
};
```

- [ ] **Step 6: Run migrations and confirm**

```bash
php artisan migrate
```
Expected: 5 new migrations run successfully.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Users/database/migrations/
git commit -m "feat(users): add client management migrations (notes, anamnesis, files, new fields)"
```

---

## Task 2: Models

**Files:**
- Modify: `app/Modules/Users/src/Models/Client.php`
- Create: `app/Modules/Users/src/Models/ClientNote.php`
- Create: `app/Modules/Users/src/Models/ClientAnamnesis.php`
- Create: `app/Modules/Users/src/Models/ClientFile.php`

- [ ] **Step 1: Write failing test for new Client model fields**

```php
// tests/Feature/Users/CoachClientManagementTest.php
<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachClientManagementTest extends TestCase
{
    use WithCoachUser;

    public function test_client_model_has_new_fields(): void
    {
        [$user, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $client->tags = ['premium', 'online'];
        $client->phone = '+39 333 1234567';
        $client->save();

        $fresh = $client->fresh();
        $this->assertEquals(['premium', 'online'], $fresh->tags);
        $this->assertEquals('+39 333 1234567', $fresh->phone);
    }
}
```

- [ ] **Step 2: Add `createClientForCoach` helper to WithCoachUser**

Open `tests/Feature/Users/Concerns/WithCoachUser.php` and add after the existing `actingAsCoach` method:

```php
protected function createClientForCoach(
    \App\Modules\Users\Models\Coach $coach,
    array $userAttrs = [],
    array $clientAttrs = []
): array {
    $clientUser = \App\Models\User::factory()->create(array_merge(
        ['email_verified_at' => now()],
        $userAttrs
    ));

    $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    $clientUser->assignRole($role);

    $client = new \App\Modules\Users\Models\Client();
    $client->user_id  = $clientUser->id;
    $client->coach_id = $coach->id;
    $client->joined_at = now();
    foreach ($clientAttrs as $key => $value) {
        $client->$key = $value;
    }
    $client->save();

    return [$clientUser, $client];
}
```

- [ ] **Step 3: Update Client model**

Replace the full content of `app/Modules/Users/src/Models/Client.php`:

```php
<?php

namespace App\Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // user_id, coach_id, status, joined_at: excluded — assigned by Actions only
        'goals',
        'birth_date',
        'gender',
        'height_cm',
        'weight_kg',
        // New fields
        'tags',
        'phone',
        'avatar_url',
        'subscription_expires_at',
        'subscription_sessions_remaining',
    ];

    protected function casts(): array
    {
        return [
            'goals'                          => 'array',
            'tags'                           => 'array',
            'birth_date'                     => 'date',
            'joined_at'                      => 'datetime',
            'subscription_expires_at'        => 'datetime',
            'height_cm'                      => 'decimal:1',
            'weight_kg'                      => 'decimal:2',
            'subscription_sessions_remaining' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class)->withDefault();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ClientNote::class)->latest('created_at');
    }

    public function anamnesis(): HasOne
    {
        return $this->hasOne(ClientAnamnesis::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(ClientFile::class)->latest();
    }
}
```

- [ ] **Step 4: Create ClientNote model**

```php
<?php
// app/Modules/Users/src/Models/ClientNote.php

namespace App\Modules\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientNote extends Model
{
    public $timestamps = false;

    protected $fillable = ['content'];

    protected $dates = ['created_at'];

    protected static function booted(): void
    {
        static::creating(fn (self $note) => $note->created_at = now());
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
```

- [ ] **Step 5: Create ClientAnamnesis model**

```php
<?php
// app/Modules/Users/src/Models/ClientAnamnesis.php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAnamnesis extends Model
{
    protected $fillable = ['content_encrypted', 'iv', 'tag'];

    protected $hidden = ['content_encrypted', 'iv', 'tag'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
```

- [ ] **Step 6: Create ClientFile model**

```php
<?php
// app/Modules/Users/src/Models/ClientFile.php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientFile extends Model
{
    protected $fillable = ['name', 'mime_type', 'size'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
```

- [ ] **Step 7: Run the failing test**

```bash
php artisan test tests/Feature/Users/CoachClientManagementTest.php --filter=test_client_model_has_new_fields
```
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Users/src/Models/ tests/Feature/Users/
git commit -m "feat(users): add ClientNote, ClientAnamnesis, ClientFile models; update Client model"
```

---

## Task 3: EncryptionService + ObjectStorageService update

**Files:**
- Create: `app/Modules/Users/src/Services/EncryptionService.php`
- Modify: `app/Modules/Users/src/Services/ObjectStorageService.php`
- Modify: `app/Modules/Users/src/Providers/UsersServiceProvider.php`
- Modify: `config/app.php`
- Modify: `.env.example`

- [ ] **Step 1: Write failing tests**

```php
// tests/Feature/Users/CoachClientAnamnesisTest.php
<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Services\EncryptionService;
use Tests\TestCase;

class CoachClientAnamnesisTest extends TestCase
{
    public function test_encryption_service_encrypts_and_decrypts(): void
    {
        config(['app.encryption_key' => 'test-secret-key-for-unit-tests-only']);

        $service   = new EncryptionService();
        $plaintext = 'Patient has knee pain on left side.';

        $encrypted = $service->encrypt($plaintext);

        $this->assertArrayHasKey('ciphertext', $encrypted);
        $this->assertArrayHasKey('iv', $encrypted);
        $this->assertArrayHasKey('tag', $encrypted);
        $this->assertNotEquals($plaintext, $encrypted['ciphertext']);

        $decrypted = $service->decrypt(
            $encrypted['ciphertext'],
            $encrypted['iv'],
            $encrypted['tag']
        );

        $this->assertEquals($plaintext, $decrypted);
    }

    public function test_each_encryption_produces_different_iv(): void
    {
        config(['app.encryption_key' => 'test-secret-key-for-unit-tests-only']);

        $service = new EncryptionService();
        $a       = $service->encrypt('same content');
        $b       = $service->encrypt('same content');

        $this->assertNotEquals($a['iv'], $b['iv']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Users/CoachClientAnamnesisTest.php
```
Expected: FAIL — class EncryptionService not found.

- [ ] **Step 3: Add config key to config/app.php**

Open `config/app.php`. Find the line with `'key' => env('APP_KEY', '')` and add below it:

```php
'encryption_key' => env('APP_ENCRYPTION_KEY', ''),
```

- [ ] **Step 4: Add to .env.example**

Append to `.env.example`:

```
# AES-256-GCM key for client anamnesis encryption (any string, hashed to 32 bytes)
APP_ENCRYPTION_KEY=
```

- [ ] **Step 5: Create EncryptionService**

```php
<?php
// app/Modules/Users/src/Services/EncryptionService.php

namespace App\Modules\Users\Services;

use RuntimeException;

class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LENGTH = 12;   // GCM recommended: 12 bytes
    private const TAG_LENGTH = 16;  // GCM auth tag: 16 bytes

    public function encrypt(string $plaintext): array
    {
        $key = $this->deriveKey();
        $iv  = random_bytes(self::IV_LENGTH);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed: ' . openssl_error_string());
        }

        return [
            'ciphertext' => base64_encode($ciphertext),
            'iv'         => base64_encode($iv),
            'tag'        => base64_encode($tag),
        ];
    }

    public function decrypt(string $ciphertext, string $iv, string $tag): string
    {
        $key = $this->deriveKey();

        $result = openssl_decrypt(
            base64_decode($ciphertext),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            base64_decode($iv),
            base64_decode($tag)
        );

        if ($result === false) {
            throw new RuntimeException('Decryption failed — data may be corrupt or key mismatch.');
        }

        return $result;
    }

    private function deriveKey(): string
    {
        // hash() with raw=true always returns exactly 32 bytes regardless of input length
        $raw = config('app.encryption_key', '');
        if ($raw === '') {
            throw new RuntimeException('APP_ENCRYPTION_KEY is not set.');
        }

        return hash('sha256', $raw, true);
    }
}
```

- [ ] **Step 6: Update ObjectStorageService — add visibility parameter**

Open `app/Modules/Users/src/Services/ObjectStorageService.php`. Replace the `upload` method signature and `put` call:

```php
public function upload(UploadedFile $file, string $folder, string $visibility = 'public'): array
{
    $uuid = (string) Str::uuid();
    $ext  = $file->getClientOriginalExtension();
    $path = $folder . '/' . $uuid . '.' . $ext;

    Storage::disk($this->disk)->put($path, $file->get(), $visibility);

    $cdnBase = rtrim(config('app.cdn_base_url', ''), '/');

    return [
        'storage_path' => $path,
        'cdn_url'      => $visibility === 'public' ? $cdnBase . '/' . $path : null,
    ];
}
```

- [ ] **Step 7: Register EncryptionService in UsersServiceProvider**

Open `app/Modules/Users/src/Providers/UsersServiceProvider.php`. Add:

```php
use App\Modules\Users\Services\EncryptionService;
```

And in `register()`:

```php
$this->app->singleton(EncryptionService::class);
```

- [ ] **Step 8: Run encryption tests**

```bash
php artisan test tests/Feature/Users/CoachClientAnamnesisTest.php --filter="test_encryption"
```
Expected: 2 PASS

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Users/src/Services/ app/Modules/Users/src/Providers/ config/app.php .env.example
git commit -m "feat(users): add EncryptionService (AES-256-GCM), update ObjectStorageService with visibility param"
```

---

## Task 4: Enhanced GET /coaches/me/clients

**Files:**
- Modify: `app/Modules/Users/src/Http/Controllers/Api/V1/CoachController.php`
- Modify: `app/Modules/Users/src/Http/Resources/ClientResource.php`
- Test: `tests/Feature/Users/CoachClientManagementTest.php`

- [ ] **Step 1: Write failing tests**

```php
// Append to tests/Feature/Users/CoachClientManagementTest.php

public function test_coach_can_list_clients_with_default_ordering(): void
{
    [$coachUser, $coach] = $this->createCoachUser();

    // Create two clients: one with earlier subscription expiry
    [$u1, $c1] = $this->createClientForCoach($coach, [], [
        'subscription_expires_at' => now()->addDays(30),
    ]);
    [$u2, $c2] = $this->createClientForCoach($coach, [], [
        'subscription_expires_at' => now()->addDays(5),
    ]);

    $response = $this->actingAsCoach($coachUser)
        ->getJson('/api/v1/coaches/me/clients');

    $response->assertOk();
    $data = $response->json('data');
    // c2 expires sooner → appears first
    $this->assertEquals($c2->id, $data[0]['id']);
    $this->assertEquals($c1->id, $data[1]['id']);
}

public function test_coach_can_filter_clients_by_status(): void
{
    [$coachUser, $coach] = $this->createCoachUser();
    [$u1, $active]   = $this->createClientForCoach($coach);
    [$u2, $inactive] = $this->createClientForCoach($coach);

    $inactive->status = 'inactive';
    $inactive->save();

    $response = $this->actingAsCoach($coachUser)
        ->getJson('/api/v1/coaches/me/clients?status=inactive');

    $response->assertOk();
    $data = $response->json('data');
    $this->assertCount(1, $data);
    $this->assertEquals($inactive->id, $data[0]['id']);
}

public function test_coach_can_search_clients_by_name(): void
{
    [$coachUser, $coach] = $this->createCoachUser();
    [$u1, $c1] = $this->createClientForCoach($coach, ['name' => 'Alice Rossi']);
    [$u2, $c2] = $this->createClientForCoach($coach, ['name' => 'Bob Bianchi']);

    $response = $this->actingAsCoach($coachUser)
        ->getJson('/api/v1/coaches/me/clients?search=Alice');

    $response->assertOk();
    $data = $response->json('data');
    $this->assertCount(1, $data);
    $this->assertEquals($c1->id, $data[0]['id']);
}

public function test_client_resource_exposes_flat_user_fields(): void
{
    [$coachUser, $coach] = $this->createCoachUser();
    [$clientUser, $client] = $this->createClientForCoach($coach, ['name' => 'Mario Rossi']);
    $client->phone = '+39 333 0000000';
    $client->tags  = ['vip'];
    $client->save();

    $response = $this->actingAsCoach($coachUser)
        ->getJson('/api/v1/coaches/me/clients');

    $response->assertOk()
        ->assertJsonPath('data.0.first_name', 'Mario')
        ->assertJsonPath('data.0.last_name', 'Rossi')
        ->assertJsonPath('data.0.phone', '+39 333 0000000')
        ->assertJsonPath('data.0.tags.0', 'vip');
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Users/CoachClientManagementTest.php
```
Expected: FAIL — wrong ordering, missing fields.

- [ ] **Step 3: Update ClientResource**

Replace `app/Modules/Users/src/Http/Resources/ClientResource.php`:

```php
<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nameParts = explode(' ', $this->user?->name ?? '', 2);

        return [
            'id'         => $this->id,
            'first_name' => $nameParts[0] ?? null,
            'last_name'  => $nameParts[1] ?? null,
            'email'      => $this->user?->email,
            'phone'      => $this->phone,
            'avatar_url' => $this->avatar_url,
            'tags'       => $this->tags ?? [],
            'status'     => $this->status,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender'     => $this->gender,
            'height_cm'  => $this->height_cm,
            'weight_kg'  => $this->weight_kg,
            'goals'      => $this->goals,
            'joined_at'  => $this->joined_at?->toISOString(),

            // Billing (populated by Billing module when available)
            'subscription_expires_at'          => $this->subscription_expires_at?->toISOString(),
            'subscription_sessions_remaining'  => $this->subscription_sessions_remaining,

            // Bookings (populated by Bookings module when available)
            'next_booking' => null,
        ];
    }
}
```

- [ ] **Step 4: Update CoachController::clients()**

Replace the `clients()` method in `app/Modules/Users/src/Http/Controllers/Api/V1/CoachController.php`:

```php
public function clients(Request $request): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach) {
        return response()->json(['message' => 'Profilo coach non trovato.'], 404);
    }

    $query = $coach->clients()->with('user');

    // Filter: status
    if ($request->filled('status')) {
        $query->where('status', $request->input('status'));
    }

    // Filter: tags[] — client must have ALL provided tags
    if ($request->filled('tags')) {
        foreach ((array) $request->input('tags') as $tag) {
            $query->whereJsonContains('tags', $tag);
        }
    }

    // Filter: expiresWithin (days)
    if ($request->filled('expiresWithin')) {
        $days = (int) $request->input('expiresWithin');
        $query->whereNotNull('subscription_expires_at')
              ->where('subscription_expires_at', '<=', now()->addDays($days));
    }

    // Search: name or email
    if ($request->filled('search')) {
        $term = '%' . $request->input('search') . '%';
        $query->whereHas('user', fn ($q) => $q->where('name', 'LIKE', $term)
                                               ->orWhere('email', 'LIKE', $term));
    }

    // Default order: subscription_expires_at ASC (expiring soonest first), nulls last
    $query->orderByRaw('subscription_expires_at IS NULL, subscription_expires_at ASC');

    $limit   = min((int) $request->input('limit', 20), 100);
    $clients = $query->paginate($limit);

    return response()->json(ClientResource::collection($clients)->response()->getData(true));
}
```

- [ ] **Step 5: Run failing tests**

```bash
php artisan test tests/Feature/Users/CoachClientManagementTest.php
```
Expected: all 4 new tests PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Users/src/Http/ tests/Feature/Users/
git commit -m "feat(users): enhance GET /coaches/me/clients — filters, search, subscription ordering, flat user fields"
```

---

## Task 5: POST /coaches/me/clients

**Files:**
- Create: `app/Modules/Users/src/Http/Requests/CreateClientRequest.php`
- Modify: `app/Modules/Users/src/Actions/CreateClientAction.php`
- Modify: `app/Modules/Users/src/Http/Controllers/Api/V1/CoachController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Test: `tests/Feature/Users/CoachClientManagementTest.php`

- [ ] **Step 1: Write failing tests**

```php
// Append to tests/Feature/Users/CoachClientManagementTest.php

public function test_coach_can_create_client_for_existing_user(): void
{
    [$coachUser, $coach] = $this->createCoachUser();

    // Pre-create a user (existing in system, no client profile for this coach)
    $existingUser = \App\Models\User::factory()->create([
        'name'               => 'Lucia Verdi',
        'email'              => 'lucia@test.com',
        'email_verified_at'  => now(),
    ]);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
    $existingUser->assignRole('client');
    // Create a Client record without a coach (standalone)
    $existingClient = new \App\Modules\Users\Models\Client();
    $existingClient->user_id  = $existingUser->id;
    $existingClient->joined_at = now();
    $existingClient->save();

    $response = $this->actingAsCoach($coachUser)
        ->postJson('/api/v1/coaches/me/clients', [
            'email'      => 'lucia@test.com',
            'first_name' => 'Lucia',
            'last_name'  => 'Verdi',
        ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('clients', [
        'user_id'  => $existingUser->id,
        'coach_id' => $coach->id,
    ]);
}

public function test_coach_create_client_with_new_email_sends_invitation(): void
{
    \Illuminate\Support\Facades\Notification::fake();

    [$coachUser, $coach] = $this->createCoachUser();

    $response = $this->actingAsCoach($coachUser)
        ->postJson('/api/v1/coaches/me/clients', [
            'email'      => 'newclient@test.com',
            'first_name' => 'Nuovo',
            'last_name'  => 'Cliente',
        ]);

    $response->assertStatus(202); // accepted — invite sent
    \Illuminate\Support\Facades\Notification::assertSentOnDemand(
        \App\Modules\Users\Notifications\CoachInvitationNotification::class
    );
    $this->assertDatabaseHas('coach_invitations', [
        'coach_id' => $coach->id,
        'email'    => 'newclient@test.com',
        'status'   => 'pending',
    ]);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Users/CoachClientManagementTest.php --filter="create"
```
Expected: FAIL — route does not exist.

- [ ] **Step 3: Create CreateClientRequest**

```php
<?php
// app/Modules/Users/src/Http/Requests/CreateClientRequest.php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'       => ['required', 'email', 'max:255'],
            'first_name'  => ['required', 'string', 'max:255'],
            'last_name'   => ['required', 'string', 'max:255'],
            'phone'       => ['sometimes', 'nullable', 'regex:/^[+]?[\d\s\-()\]{7,20}$/'],
            'tags'        => ['sometimes', 'nullable', 'array'],
            'tags.*'      => ['string', 'max:50'],
            'birth_date'  => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender'      => ['sometimes', 'nullable', 'in:male,female,other'],
            'height_cm'   => ['sometimes', 'nullable', 'numeric', 'min:50', 'max:300'],
            'weight_kg'   => ['sometimes', 'nullable', 'numeric', 'min:20', 'max:500'],
        ];
    }
}
```

- [ ] **Step 4: Update CreateClientAction**

Replace `app/Modules/Users/src/Actions/CreateClientAction.php`:

```php
<?php

namespace App\Modules\Users\Actions;

use App\Models\User;
use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreateClientAction
{
    public function __construct(
        private readonly SendInvitationAction $invite,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Returns ['status' => 'created', 'client' => Client]
     *      or ['status' => 'invited']
     */
    public function handle(Coach $coach, array $data): array
    {
        $email      = mb_strtolower($data['email']);
        $firstName  = $data['first_name'];
        $lastName   = $data['last_name'];
        $fullName   = trim("{$firstName} {$lastName}");

        $existingUser = User::where('email', $email)->first();

        if ($existingUser) {
            $client = DB::transaction(function () use ($existingUser, $coach, $fullName, $data) {
                // If user already has a client profile for THIS coach, it's a duplicate
                $alreadyClient = $existingUser->client?->coach_id === $coach->id;
                if ($alreadyClient) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'email' => ['Questo utente è già un tuo cliente.'],
                    ]);
                }

                // Update user name if they don't have one yet
                if (! $existingUser->name) {
                    $existingUser->update(['name' => $fullName]);
                }

                $clientRole = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
                $existingUser->assignRole($clientRole);

                // If user has a Client record without a coach, adopt it
                // Otherwise create a fresh one
                $client = $existingUser->client;
                if ($client && $client->coach_id === null) {
                    $client->coach_id  = $coach->id;
                    $client->joined_at = now();
                    $client->fill(array_intersect_key($data, array_flip([
                        'tags', 'phone', 'birth_date', 'gender', 'height_cm', 'weight_kg',
                    ])));
                    $client->save();
                } else {
                    $client            = new Client();
                    $client->user_id   = $existingUser->id;
                    $client->coach_id  = $coach->id;
                    $client->joined_at = now();
                    $client->fill(array_intersect_key($data, array_flip([
                        'tags', 'phone', 'birth_date', 'gender', 'height_cm', 'weight_kg',
                    ])));
                    $client->save();
                }

                return $client;
            });

            $this->audit->log('CLIENT_CREATED', $coach->user_id, ['client_id' => $client->id]);

            return ['status' => 'created', 'client' => $client->load('user')];
        }

        // New email: send invitation (creates CoachInvitation, sends email)
        $this->invite->handle($coach, $email);
        $this->audit->log('CLIENT_INVITED', $coach->user_id, ['email' => $email]);

        return ['status' => 'invited'];
    }
}
```

- [ ] **Step 5: Add store method to CoachController**

Add the following `store()` method to `app/Modules/Users/src/Http/Controllers/Api/V1/CoachController.php`:

```php
// Add to imports:
// use App\Modules\Users\Http\Requests\CreateClientRequest;

public function store(CreateClientRequest $request, CreateClientAction $action): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach) {
        return response()->json(['message' => 'Profilo coach non trovato.'], 404);
    }

    $result = $action->handle($coach, $request->validated());

    if ($result['status'] === 'invited') {
        return response()->json([
            'message' => 'Invito inviato. Il cliente riceverà un\'email per completare la registrazione.',
        ], 202);
    }

    return response()->json(['data' => new ClientResource($result['client'])], 201);
}
```

- [ ] **Step 6: Add route**

In `app/Modules/Users/routes/api.php`, inside the `coaches/me` prefix group, add after the existing `GET /clients` route:

```php
Route::post('clients', [CoachController::class, 'store'])->middleware('throttle:30,1')->name('clients.store');
```

Also add the missing import if not present:
```php
use App\Modules\Users\Actions\CreateClientAction;
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Users/CoachClientManagementTest.php --filter="create"
```
Expected: 2 PASS

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Users/src/ app/Modules/Users/routes/
git commit -m "feat(users): POST /coaches/me/clients — email lookup, direct link or invitation flow"
```

---

## Task 6: GET /coaches/me/clients/:id — Full Client Card

**Files:**
- Create: `app/Modules/Users/src/Http/Resources/ClientDetailResource.php`
- Create: `app/Modules/Users/src/Http/Resources/ClientNoteResource.php`
- Create: `app/Modules/Users/src/Http/Controllers/Api/V1/CoachClientController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Test: `tests/Feature/Users/CoachClientManagementTest.php`

- [ ] **Step 1: Write failing test**

```php
// Append to tests/Feature/Users/CoachClientManagementTest.php

public function test_coach_can_get_full_client_card(): void
{
    [$coachUser, $coach] = $this->createCoachUser();
    [$clientUser, $client] = $this->createClientForCoach($coach, ['name' => 'Sara Neri'], [
        'phone' => '+39 340 1234567',
        'tags'  => ['premium'],
    ]);

    $response = $this->actingAsCoach($coachUser)
        ->getJson("/api/v1/coaches/me/clients/{$client->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $client->id)
        ->assertJsonPath('data.first_name', 'Sara')
        ->assertJsonPath('data.phone', '+39 340 1234567')
        ->assertJsonPath('data.tags.0', 'premium')
        ->assertJsonPath('data.anamnesis', null)  // no anamnesis yet
        ->assertJsonPath('data.notes', [])
        ->assertJsonPath('data.files', []);
}

public function test_coach_cannot_get_another_coachs_client(): void
{
    [$coachUser, $coach]   = $this->createCoachUser();
    [$coach2User, $coach2] = $this->createCoachUser();
    [$clientUser, $client] = $this->createClientForCoach($coach2);

    $response = $this->actingAsCoach($coachUser)
        ->getJson("/api/v1/coaches/me/clients/{$client->id}");

    $response->assertForbidden();
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Users/CoachClientManagementTest.php --filter="client_card|another_coachs"
```
Expected: FAIL — 404 (route doesn't exist).

- [ ] **Step 3: Create ClientNoteResource**

```php
<?php
// app/Modules/Users/src/Http/Resources/ClientNoteResource.php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'content'    => $this->content,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 4: Create ClientDetailResource**

```php
<?php
// app/Modules/Users/src/Http/Resources/ClientDetailResource.php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientDetailResource extends JsonResource
{
    public function __construct($resource, private readonly ?string $decryptedAnamnesis = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $nameParts = explode(' ', $this->user?->name ?? '', 2);

        return [
            'id'         => $this->id,
            'first_name' => $nameParts[0] ?? null,
            'last_name'  => $nameParts[1] ?? null,
            'email'      => $this->user?->email,
            'phone'      => $this->phone,
            'avatar_url' => $this->avatar_url,
            'tags'       => $this->tags ?? [],
            'status'     => $this->status,
            'birth_date' => $this->birth_date?->toDateString(),
            'gender'     => $this->gender,
            'height_cm'  => $this->height_cm,
            'weight_kg'  => $this->weight_kg,
            'goals'      => $this->goals,
            'joined_at'  => $this->joined_at?->toISOString(),

            'subscription_expires_at'         => $this->subscription_expires_at?->toISOString(),
            'subscription_sessions_remaining' => $this->subscription_sessions_remaining,

            'anamnesis' => $this->decryptedAnamnesis,

            // Internal notes (latest 10)
            'notes' => ClientNoteResource::collection(
                $this->whenLoaded('notes', fn () => $this->notes->take(10))
            ),

            // Files
            'files' => ClientFileResource::collection(
                $this->whenLoaded('files')
            ),

            // Placeholders — populated by future modules
            'sessions'        => [],   // Bookings module
            'next_session'    => null, // Bookings module
            'payment_history' => [],   // Billing module
            'next_booking'    => null, // Bookings module
        ];
    }
}
```

- [ ] **Step 5: Create ClientFileResource**

```php
<?php
// app/Modules/Users/src/Http/Resources/ClientFileResource.php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientFileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'mime_type'  => $this->mime_type,
            'size'       => $this->size,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

- [ ] **Step 6: Create CoachClientController**

```php
<?php
// app/Modules/Users/src/Http/Controllers/Api/V1/CoachClientController.php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Http\Resources\ClientDetailResource;
use App\Modules\Users\Services\EncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoachClientController extends Controller
{
    public function show(Request $request, Client $client, EncryptionService $encryption): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $client->load(['user', 'notes', 'files']);

        $decryptedAnamnesis = null;
        $anamnesis = $client->anamnesis;
        if ($anamnesis) {
            try {
                $decryptedAnamnesis = $encryption->decrypt(
                    $anamnesis->content_encrypted,
                    $anamnesis->iv,
                    $anamnesis->tag
                );
            } catch (\RuntimeException) {
                $decryptedAnamnesis = null; // key mismatch or corrupt data
            }
        }

        return response()->json(['data' => new ClientDetailResource($client, $decryptedAnamnesis)]);
    }
}
```

- [ ] **Step 7: Add route**

In `app/Modules/Users/routes/api.php`, inside the `coaches/me` prefix group, add:

```php
Route::get('clients/{client}', [CoachClientController::class, 'show'])->middleware('throttle:60,1')->name('clients.show');
```

Also add import:
```php
use App\Modules\Users\Http\Controllers\Api\V1\CoachClientController;
```

- [ ] **Step 8: Run tests**

```bash
php artisan test tests/Feature/Users/CoachClientManagementTest.php --filter="client_card|another_coachs"
```
Expected: 2 PASS

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Users/src/ app/Modules/Users/routes/
git commit -m "feat(users): GET /coaches/me/clients/:id — full client card with notes, files, anamnesis placeholder"
```

---

## Task 7: PUT + DELETE /coaches/me/clients/:id

**Files:**
- Create: `app/Modules/Users/src/Http/Requests/UpdateCoachClientRequest.php`
- Create: `app/Modules/Users/src/Actions/UpdateCoachClientAction.php`
- Create: `app/Modules/Users/src/Actions/DeleteClientAction.php`
- Modify: `app/Modules/Users/src/Http/Controllers/Api/V1/CoachClientController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Test: `tests/Feature/Users/CoachClientManagementTest.php`

- [ ] **Step 1: Write failing tests**

```php
// Append to tests/Feature/Users/CoachClientManagementTest.php

public function test_coach_can_update_client(): void
{
    [$coachUser, $coach] = $this->createCoachUser();
    [$clientUser, $client] = $this->createClientForCoach($coach);

    $response = $this->actingAsCoach($coachUser)
        ->putJson("/api/v1/coaches/me/clients/{$client->id}", [
            'phone'     => '+39 333 9999999',
            'tags'      => ['vip', 'online'],
            'weight_kg' => 75.5,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.phone', '+39 333 9999999')
        ->assertJsonPath('data.tags.0', 'vip')
        ->assertJsonPath('data.weight_kg', '75.50');
}

public function test_coach_can_soft_delete_client(): void
{
    [$coachUser, $coach] = $this->createCoachUser();
    [$clientUser, $client] = $this->createClientForCoach($coach);

    $response = $this->actingAsCoach($coachUser)
        ->deleteJson("/api/v1/coaches/me/clients/{$client->id}");

    $response->assertNoContent();
    $this->assertSoftDeleted('clients', ['id' => $client->id]);
}
```

- [ ] **Step 2: Run to verify failure**

```bash
php artisan test tests/Feature/Users/CoachClientManagementTest.php --filter="update_client|soft_delete"
```
Expected: FAIL — route does not exist.

- [ ] **Step 3: Create UpdateCoachClientRequest**

```php
<?php
// app/Modules/Users/src/Http/Requests/UpdateCoachClientRequest.php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCoachClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone'      => ['sometimes', 'nullable', 'regex:/^[+]?[\d\s\-()\]{7,20}$/'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'tags'       => ['sometimes', 'nullable', 'array'],
            'tags.*'     => ['string', 'max:50'],
            'status'     => ['sometimes', 'in:active,inactive,suspended'],
            'birth_date' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender'     => ['sometimes', 'nullable', 'in:male,female,other'],
            'height_cm'  => ['sometimes', 'nullable', 'numeric', 'min:50', 'max:300'],
            'weight_kg'  => ['sometimes', 'nullable', 'numeric', 'min:20', 'max:500'],
            'goals'      => ['sometimes', 'nullable', 'array'],
            'goals.*'    => ['string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Create UpdateCoachClientAction**

```php
<?php
// app/Modules/Users/src/Actions/UpdateCoachClientAction.php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;

class UpdateCoachClientAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Client $client, Coach $coach, array $data): Client
    {
        // Update user name if provided
        if (isset($data['first_name']) || isset($data['last_name'])) {
            $nameParts = explode(' ', $client->user->name ?? '', 2);
            $firstName = $data['first_name'] ?? ($nameParts[0] ?? '');
            $lastName  = $data['last_name']  ?? ($nameParts[1] ?? '');
            $client->user->update(['name' => trim("{$firstName} {$lastName}")]);
        }

        $clientFields = array_intersect_key($data, array_flip([
            'phone', 'avatar_url', 'tags', 'status',
            'birth_date', 'gender', 'height_cm', 'weight_kg', 'goals',
        ]));

        $client->update($clientFields);

        $this->audit->log('CLIENT_UPDATED', $coach->user_id, ['client_id' => $client->id]);

        return $client->fresh(['user']);
    }
}
```

- [ ] **Step 5: Create DeleteClientAction**

```php
<?php
// app/Modules/Users/src/Actions/DeleteClientAction.php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;

class DeleteClientAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Client $client, Coach $coach): void
    {
        $clientId = $client->id;
        $client->delete(); // SoftDeletes
        $this->audit->log('CLIENT_DELETED', $coach->user_id, ['client_id' => $clientId]);
    }
}
```

- [ ] **Step 6: Add update() and destroy() to CoachClientController**

Add to `app/Modules/Users/src/Http/Controllers/Api/V1/CoachClientController.php`:

```php
// Add to imports:
// use App\Modules\Users\Http\Requests\UpdateCoachClientRequest;
// use App\Modules\Users\Actions\UpdateCoachClientAction;
// use App\Modules\Users\Actions\DeleteClientAction;
// use App\Modules\Users\Http\Resources\ClientResource;

public function update(UpdateCoachClientRequest $request, Client $client, UpdateCoachClientAction $action): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach || $client->coach_id !== $coach->id) {
        return response()->json(['message' => 'Non autorizzato.'], 403);
    }

    $updated = $action->handle($client, $coach, $request->validated());

    return response()->json(['data' => new ClientResource($updated)]);
}

public function destroy(Request $request, Client $client, DeleteClientAction $action): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach || $client->coach_id !== $coach->id) {
        return response()->json(['message' => 'Non autorizzato.'], 403);
    }

    $action->handle($client, $coach);

    return response()->json(null, 204);
}
```

- [ ] **Step 7: Add routes**

In `app/Modules/Users/routes/api.php`, inside the `coaches/me` prefix group:

```php
Route::put('clients/{client}', [CoachClientController::class, 'update'])->middleware('throttle:30,1')->name('clients.update');
Route::delete('clients/{client}', [CoachClientController::class, 'destroy'])->middleware('throttle:20,1')->name('clients.destroy');
```

- [ ] **Step 8: Run tests**

```bash
php artisan test tests/Feature/Users/CoachClientManagementTest.php --filter="update_client|soft_delete"
```
Expected: 2 PASS

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Users/src/ app/Modules/Users/routes/
git commit -m "feat(users): PUT/DELETE /coaches/me/clients/:id — update client, soft delete, audit logs"
```

---

## Task 8: POST/DELETE /coaches/me/clients/:id/tags

**Files:**
- Create: `app/Modules/Users/src/Actions/AddClientTagAction.php`
- Create: `app/Modules/Users/src/Actions/RemoveClientTagAction.php`
- Create: `app/Modules/Users/src/Http/Controllers/Api/V1/ClientTagController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Test: `tests/Feature/Users/CoachClientTagTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Users/CoachClientTagTest.php

namespace Tests\Feature\Users;

use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachClientTagTest extends TestCase
{
    use WithCoachUser;

    public function test_coach_can_add_tag_to_client(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach, [], ['tags' => ['existing']]);

        $response = $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/tags", [
                'tag' => 'premium',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.tags.0', 'existing')
            ->assertJsonPath('data.tags.1', 'premium');
    }

    public function test_add_tag_is_idempotent(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach, [], ['tags' => ['premium']]);

        $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/tags", ['tag' => 'premium']);

        $this->assertCount(1, $client->fresh()->tags);
    }

    public function test_coach_can_remove_tag_from_client(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach, [], ['tags' => ['vip', 'online']]);

        $response = $this->actingAsCoach($coachUser)
            ->deleteJson("/api/v1/coaches/me/clients/{$client->id}/tags/vip");

        $response->assertNoContent();
        $this->assertEquals(['online'], array_values($client->fresh()->tags));
    }
}
```

- [ ] **Step 2: Run to verify failure**

```bash
php artisan test tests/Feature/Users/CoachClientTagTest.php
```
Expected: FAIL — route does not exist.

- [ ] **Step 3: Create AddClientTagAction**

```php
<?php
// app/Modules/Users/src/Actions/AddClientTagAction.php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;

class AddClientTagAction
{
    public function handle(Client $client, string $tag): Client
    {
        $tags = $client->tags ?? [];

        if (! in_array($tag, $tags, true)) {
            $tags[] = $tag;
            $client->update(['tags' => array_values($tags)]);
        }

        return $client->fresh();
    }
}
```

- [ ] **Step 4: Create RemoveClientTagAction**

```php
<?php
// app/Modules/Users/src/Actions/RemoveClientTagAction.php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;

class RemoveClientTagAction
{
    public function handle(Client $client, string $tag): void
    {
        $tags = array_values(array_filter($client->tags ?? [], fn ($t) => $t !== $tag));
        $client->update(['tags' => $tags]);
    }
}
```

- [ ] **Step 5: Create ClientTagController**

```php
<?php
// app/Modules/Users/src/Http/Controllers/Api/V1/ClientTagController.php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\AddClientTagAction;
use App\Modules\Users\Actions\RemoveClientTagAction;
use App\Modules\Users\Http\Resources\ClientResource;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientTagController extends Controller
{
    public function store(Request $request, Client $client, AddClientTagAction $action): JsonResponse
    {
        $request->validate(['tag' => ['required', 'string', 'max:50']]);

        $coach = $request->user()->coach;
        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $client = $action->handle($client, $request->input('tag'));

        return response()->json(['data' => new ClientResource($client->load('user'))]);
    }

    public function destroy(Request $request, Client $client, string $tag, RemoveClientTagAction $action): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $action->handle($client, $tag);

        return response()->json(null, 204);
    }
}
```

- [ ] **Step 6: Add routes**

In `app/Modules/Users/routes/api.php`, inside the `coaches/me` prefix group:

```php
Route::post('clients/{client}/tags', [ClientTagController::class, 'store'])->middleware('throttle:60,1')->name('clients.tags.store');
Route::delete('clients/{client}/tags/{tag}', [ClientTagController::class, 'destroy'])->middleware('throttle:60,1')->name('clients.tags.destroy');
```

Add import:
```php
use App\Modules\Users\Http\Controllers\Api\V1\ClientTagController;
```

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Users/CoachClientTagTest.php
```
Expected: 3 PASS

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Users/src/ app/Modules/Users/routes/ tests/
git commit -m "feat(users): POST/DELETE /coaches/me/clients/:id/tags — idempotent tag management"
```

---

## Task 9: POST /coaches/me/clients/:id/notes

**Files:**
- Create: `app/Modules/Users/src/Http/Requests/StoreClientNoteRequest.php`
- Create: `app/Modules/Users/src/Actions/AddClientNoteAction.php`
- Create: `app/Modules/Users/src/Http/Controllers/Api/V1/ClientNoteController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Test: `tests/Feature/Users/CoachClientNoteTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Users/CoachClientNoteTest.php

namespace Tests\Feature\Users;

use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachClientNoteTest extends TestCase
{
    use WithCoachUser;

    public function test_coach_can_add_note_to_client(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $response = $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/notes", [
                'content' => 'Il cliente ha problemi alla spalla sinistra.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'Il cliente ha problemi alla spalla sinistra.');

        $this->assertDatabaseHas('client_notes', [
            'client_id' => $client->id,
            'coach_id'  => $coach->id,
        ]);
    }

    public function test_note_is_immutable_after_creation(): void
    {
        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/notes", [
                'content' => 'Original note',
            ]);

        $note = \App\Modules\Users\Models\ClientNote::first();
        $this->assertNotNull($note->created_at);
        // The model has no updated_at column
        $this->assertArrayNotHasKey('updated_at', $note->toArray());
    }
}
```

- [ ] **Step 2: Run to verify failure**

```bash
php artisan test tests/Feature/Users/CoachClientNoteTest.php
```
Expected: FAIL — route does not exist.

- [ ] **Step 3: Create StoreClientNoteRequest**

```php
<?php
// app/Modules/Users/src/Http/Requests/StoreClientNoteRequest.php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:5000'],
        ];
    }
}
```

- [ ] **Step 4: Create AddClientNoteAction**

```php
<?php
// app/Modules/Users/src/Actions/AddClientNoteAction.php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientNote;
use App\Modules\Users\Models\Coach;

class AddClientNoteAction
{
    public function handle(Client $client, Coach $coach, string $content): ClientNote
    {
        $note            = new ClientNote();
        $note->client_id = $client->id;
        $note->coach_id  = $coach->id;
        $note->content   = $content;
        $note->save();

        return $note;
    }
}
```

- [ ] **Step 5: Create ClientNoteController**

```php
<?php
// app/Modules/Users/src/Http/Controllers/Api/V1/ClientNoteController.php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\AddClientNoteAction;
use App\Modules\Users\Http\Requests\StoreClientNoteRequest;
use App\Modules\Users\Http\Resources\ClientNoteResource;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientNoteController extends Controller
{
    public function store(StoreClientNoteRequest $request, Client $client, AddClientNoteAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $note = $action->handle($client, $coach, $request->validated('content'));

        return response()->json(['data' => new ClientNoteResource($note)], 201);
    }
}
```

- [ ] **Step 6: Add route**

```php
Route::post('clients/{client}/notes', [ClientNoteController::class, 'store'])->middleware('throttle:60,1')->name('clients.notes.store');
```

Add import: `use App\Modules\Users\Http\Controllers\Api\V1\ClientNoteController;`

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Users/CoachClientNoteTest.php
```
Expected: 2 PASS

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Users/src/ app/Modules/Users/routes/ tests/
git commit -m "feat(users): POST /coaches/me/clients/:id/notes — immutable coach notes"
```

---

## Task 10: PUT /coaches/me/clients/:id/anamnesis

**Files:**
- Create: `app/Modules/Users/src/Http/Requests/UpdateClientAnamnesisRequest.php`
- Create: `app/Modules/Users/src/Actions/UpdateClientAnamnesisAction.php`
- Create: `app/Modules/Users/src/Http/Controllers/Api/V1/ClientAnamnesisController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Test: `tests/Feature/Users/CoachClientAnamnesisTest.php`

- [ ] **Step 1: Add anamnesis endpoint tests**

```php
// Append to tests/Feature/Users/CoachClientAnamnesisTest.php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

// Add at class level:
// use WithCoachUser;
// use RefreshDatabase; (already in WithCoachUser)

public function test_coach_can_update_client_anamnesis(): void
{
    [$coachUser, $coach] = $this->createCoachUser();
    [$clientUser, $client] = $this->createClientForCoach($coach);

    config(['app.encryption_key' => 'test-secret-key-for-unit-tests-only']);

    $response = $this->actingAsCoach($coachUser)
        ->putJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis", [
            'content' => 'Paziente con lombalgia cronica.',
        ]);

    $response->assertOk()->assertJsonPath('data.message', 'Anamnesi aggiornata.');

    // Verify it's encrypted in DB (not plaintext)
    $record = \App\Modules\Users\Models\ClientAnamnesis::where('client_id', $client->id)->first();
    $this->assertNotNull($record);
    $this->assertNotEquals('Paziente con lombalgia cronica.', $record->content_encrypted);
    $this->assertNotEmpty($record->iv);
    $this->assertNotEmpty($record->tag);
}

public function test_anamnesis_is_decrypted_in_client_detail(): void
{
    [$coachUser, $coach] = $this->createCoachUser();
    [$clientUser, $client] = $this->createClientForCoach($coach);

    config(['app.encryption_key' => 'test-secret-key-for-unit-tests-only']);

    $this->actingAsCoach($coachUser)
        ->putJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis", [
            'content' => 'Allergia ai FANS.',
        ]);

    $response = $this->actingAsCoach($coachUser)
        ->getJson("/api/v1/coaches/me/clients/{$client->id}");

    $response->assertOk()
        ->assertJsonPath('data.anamnesis', 'Allergia ai FANS.');
}

public function test_anamnesis_update_is_overwrite_not_append(): void
{
    [$coachUser, $coach] = $this->createCoachUser();
    [$clientUser, $client] = $this->createClientForCoach($coach);

    config(['app.encryption_key' => 'test-secret-key-for-unit-tests-only']);

    $this->actingAsCoach($coachUser)->putJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis", ['content' => 'Version 1']);
    $this->actingAsCoach($coachUser)->putJson("/api/v1/coaches/me/clients/{$client->id}/anamnesis", ['content' => 'Version 2']);

    $this->assertDatabaseCount('client_anamnesis', 1);

    $response = $this->actingAsCoach($coachUser)->getJson("/api/v1/coaches/me/clients/{$client->id}");
    $response->assertJsonPath('data.anamnesis', 'Version 2');
}
```

Make sure to add the trait to the `CoachClientAnamnesisTest` class:

```php
class CoachClientAnamnesisTest extends TestCase
{
    use WithCoachUser;
    // ...
}
```

- [ ] **Step 2: Run to verify failure**

```bash
php artisan test tests/Feature/Users/CoachClientAnamnesisTest.php --filter="coach_can_update|decrypted_in|overwrite"
```
Expected: FAIL — route does not exist.

- [ ] **Step 3: Create UpdateClientAnamnesisRequest**

```php
<?php
// app/Modules/Users/src/Http/Requests/UpdateClientAnamnesisRequest.php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateClientAnamnesisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:50000'],
        ];
    }
}
```

- [ ] **Step 4: Create UpdateClientAnamnesisAction**

```php
<?php
// app/Modules/Users/src/Actions/UpdateClientAnamnesisAction.php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientAnamnesis;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Services\EncryptionService;

class UpdateClientAnamnesisAction
{
    public function __construct(
        private readonly EncryptionService $encryption,
        private readonly AuditLogService $audit,
    ) {}

    public function handle(Client $client, Coach $coach, string $content): void
    {
        $encrypted = $this->encryption->encrypt($content);

        ClientAnamnesis::updateOrCreate(
            ['client_id' => $client->id],
            [
                'content_encrypted' => $encrypted['ciphertext'],
                'iv'                => $encrypted['iv'],
                'tag'               => $encrypted['tag'],
            ]
        );

        // NEVER log the content — only the action
        $this->audit->log('ANAMNESIS_UPDATED', $coach->user_id, ['client_id' => $client->id]);
    }
}
```

- [ ] **Step 5: Create ClientAnamnesisController**

```php
<?php
// app/Modules/Users/src/Http/Controllers/Api/V1/ClientAnamnesisController.php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\UpdateClientAnamnesisAction;
use App\Modules\Users\Http\Requests\UpdateClientAnamnesisRequest;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;

class ClientAnamnesisController extends Controller
{
    public function update(UpdateClientAnamnesisRequest $request, Client $client, UpdateClientAnamnesisAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $action->handle($client, $coach, $request->validated('content'));

        return response()->json(['data' => ['message' => 'Anamnesi aggiornata.']]);
    }
}
```

- [ ] **Step 6: Add route**

```php
Route::put('clients/{client}/anamnesis', [ClientAnamnesisController::class, 'update'])->middleware('throttle:20,1')->name('clients.anamnesis.update');
```

Add import: `use App\Modules\Users\Http\Controllers\Api\V1\ClientAnamnesisController;`

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Users/CoachClientAnamnesisTest.php
```
Expected: all 5 PASS (2 encryption unit tests + 3 endpoint tests)

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Users/src/ app/Modules/Users/routes/ tests/
git commit -m "feat(users): PUT /coaches/me/clients/:id/anamnesis — AES-256-GCM encryption, decrypted in client detail"
```

---

## Task 11: POST /coaches/me/clients/:id/files + GET download

**Files:**
- Create: `app/Modules/Users/src/Http/Requests/StoreClientFileRequest.php`
- Create: `app/Modules/Users/src/Actions/StoreClientFileAction.php`
- Create: `app/Modules/Users/src/Http/Controllers/Api/V1/ClientFileController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Test: `tests/Feature/Users/CoachClientFileTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php
// tests/Feature/Users/CoachClientFileTest.php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\ClientFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachClientFileTest extends TestCase
{
    use WithCoachUser;

    public function test_coach_can_upload_file_for_client(): void
    {
        Storage::fake('r2_docs');

        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $file = UploadedFile::fake()->create('report.pdf', 500, 'application/pdf');

        $response = $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/files", [
                'file' => $file,
                'name' => 'Report Visita Medica',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Report Visita Medica')
            ->assertJsonPath('data.mime_type', 'application/pdf');

        $this->assertDatabaseHas('client_files', [
            'client_id' => $client->id,
            'coach_id'  => $coach->id,
            'name'      => 'Report Visita Medica',
        ]);
    }

    public function test_file_upload_rejects_unsupported_type(): void
    {
        Storage::fake('r2_docs');

        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        $file = UploadedFile::fake()->create('script.exe', 100, 'application/octet-stream');

        $this->actingAsCoach($coachUser)
            ->postJson("/api/v1/coaches/me/clients/{$client->id}/files", [
                'file' => $file,
            ])
            ->assertUnprocessable();
    }

    public function test_coach_can_get_presigned_download_url(): void
    {
        Storage::fake('r2_docs');

        [$coachUser, $coach] = $this->createCoachUser();
        [$clientUser, $client] = $this->createClientForCoach($coach);

        // Create a file record directly
        $clientFile = new ClientFile();
        $clientFile->coach_id    = $coach->id;
        $clientFile->client_id   = $client->id;
        $clientFile->storage_key = 'client-files/1/1/test-uuid.pdf';
        $clientFile->name        = 'Test File';
        $clientFile->mime_type   = 'application/pdf';
        $clientFile->size        = 1024;
        $clientFile->save();

        // Mock temporaryUrl since Storage::fake doesn't support it
        Storage::shouldReceive('disk')
            ->with('r2_docs')
            ->andReturn(\Mockery::mock([
                'temporaryUrl' => 'https://r2.example.com/signed?token=abc',
            ]));

        $response = $this->actingAsCoach($coachUser)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/files/{$clientFile->id}/download");

        $response->assertOk()
            ->assertJsonPath('data.url', 'https://r2.example.com/signed?token=abc');
    }
}
```

- [ ] **Step 2: Run to verify failure**

```bash
php artisan test tests/Feature/Users/CoachClientFileTest.php
```
Expected: FAIL — route does not exist.

- [ ] **Step 3: Create StoreClientFileRequest**

```php
<?php
// app/Modules/Users/src/Http/Requests/StoreClientFileRequest.php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,mp4', 'max:20480'],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
```

- [ ] **Step 4: Create StoreClientFileAction**

```php
<?php
// app/Modules/Users/src/Actions/StoreClientFileAction.php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientFile;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Services\ObjectStorageService;
use Illuminate\Http\UploadedFile;

class StoreClientFileAction
{
    public function __construct(private readonly ObjectStorageService $storage) {}

    public function handle(Client $client, Coach $coach, UploadedFile $file, ?string $displayName = null): ClientFile
    {
        // Private upload — no public CDN URL
        $result = $this->storage->upload(
            $file,
            "client-files/{$coach->id}/{$client->id}",
            'private'
        );

        $record              = new ClientFile();
        $record->coach_id    = $coach->id;
        $record->client_id   = $client->id;
        $record->storage_key = $result['storage_path'];
        $record->name        = $displayName ?? $file->getClientOriginalName();
        $record->mime_type   = $file->getMimeType() ?? $file->getClientMimeType();
        $record->size        = $file->getSize();
        $record->save();

        return $record;
    }
}
```

- [ ] **Step 5: Create ClientFileController**

```php
<?php
// app/Modules/Users/src/Http/Controllers/Api/V1/ClientFileController.php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\StoreClientFileAction;
use App\Modules\Users\Http\Requests\StoreClientFileRequest;
use App\Modules\Users\Http\Resources\ClientFileResource;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\ClientFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ClientFileController extends Controller
{
    public function store(StoreClientFileRequest $request, Client $client, StoreClientFileAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $client->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $record = $action->handle(
            $client,
            $coach,
            $request->file('file'),
            $request->input('name')
        );

        return response()->json(['data' => new ClientFileResource($record)], 201);
    }

    public function download(Request $request, Client $client, ClientFile $clientFile): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $client->coach_id !== $coach->id || $clientFile->client_id !== $client->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $expiresAt  = now()->addMinutes(15);
        $signedUrl  = Storage::disk('r2_docs')->temporaryUrl($clientFile->storage_key, $expiresAt);

        return response()->json([
            'data' => [
                'url'        => $signedUrl,
                'expires_at' => $expiresAt->toISOString(),
            ],
        ]);
    }
}
```

- [ ] **Step 6: Add routes**

```php
Route::post('clients/{client}/files', [ClientFileController::class, 'store'])->middleware('throttle:20,1')->name('clients.files.store');
Route::get('clients/{client}/files/{clientFile}/download', [ClientFileController::class, 'download'])->middleware('throttle:60,1')->name('clients.files.download');
```

Add import: `use App\Modules\Users\Http\Controllers\Api\V1\ClientFileController;`

- [ ] **Step 7: Run tests**

```bash
php artisan test tests/Feature/Users/CoachClientFileTest.php
```
Expected: 3 PASS

- [ ] **Step 8: Run full test suite**

```bash
php artisan test --filter=Users
```
Expected: all tests pass.

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Users/src/ app/Modules/Users/routes/ tests/
git commit -m "feat(users): POST /coaches/me/clients/:id/files (private R2 upload) + GET presigned download URL"
```

---

## Final: Full test run + cleanup

- [ ] **Run all tests**

```bash
php artisan test
```
Expected: all pass, 0 failures.

- [ ] **Commit if any fixes needed, then final commit**

```bash
git commit -m "test(users): finalize coach client management test suite"
```
