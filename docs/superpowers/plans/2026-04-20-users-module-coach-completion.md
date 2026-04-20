# Users Module — Coach Profile Completion

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the 7 missing coach endpoints in the Users module: `GET /coaches/me`, `PUT /coaches/me` (geocoding + slug + audit), `PUT /coaches/me/publish`, `PUT /coaches/me/availability`, `POST/DELETE /coaches/me/certifications/:id`, `GET /coaches/:id/public`.

**Architecture:** Follow the existing Action pattern — controllers receive FormRequests, delegate to Action classes, return Resources. New services (GeocodingService, SlugService, ObjectStorageService) are registered as singletons in UsersServiceProvider. Feature tests use `RefreshDatabase` and disable the `JwtAuthenticate` middleware with `actingAs()` to focus on feature logic.

**Tech Stack:** Laravel 13, PHP 8.3, Spatie Permissions, `Illuminate\Support\Facades\Http` (Nominatim), `league/flysystem-aws-s3-v3` (Cloudflare R2 S3-compatible).

---

## File Map

### New migrations
- `app/Modules/Users/database/migrations/2026_04_20_100000_add_profile_fields_to_coaches_table.php`
- `app/Modules/Users/database/migrations/2026_04_20_100001_create_coach_availabilities_table.php`
- `app/Modules/Users/database/migrations/2026_04_20_100002_create_certifications_table.php`

### New models
- `app/Modules/Users/src/Models/CoachAvailability.php`
- `app/Modules/Users/src/Models/Certification.php`

### New services
- `app/Modules/Users/src/Services/GeocodingService.php`
- `app/Modules/Users/src/Services/SlugService.php`
- `app/Modules/Users/src/Services/ObjectStorageService.php`

### New actions
- `app/Modules/Users/src/Actions/PublishCoachProfileAction.php`
- `app/Modules/Users/src/Actions/ReplaceAvailabilityAction.php`
- `app/Modules/Users/src/Actions/StoreCertificationAction.php`
- `app/Modules/Users/src/Actions/DeleteCertificationAction.php`

### New requests
- `app/Modules/Users/src/Http/Requests/UpdateAvailabilityRequest.php`
- `app/Modules/Users/src/Http/Requests/StoreCertificationRequest.php`

### New resources
- `app/Modules/Users/src/Http/Resources/CoachAvailabilityResource.php`
- `app/Modules/Users/src/Http/Resources/CertificationResource.php`
- `app/Modules/Users/src/Http/Resources/PublicCoachResource.php`

### New controllers
- `app/Modules/Users/src/Http/Controllers/Api/V1/AvailabilityController.php`
- `app/Modules/Users/src/Http/Controllers/Api/V1/CertificationController.php`
- `app/Modules/Users/src/Http/Controllers/Api/V1/PublicCoachController.php`

### Modified files
- `app/Modules/Users/src/Models/Coach.php` — new fillable fields, casts, hasMany relationships
- `app/Modules/Users/src/Http/Controllers/Api/V1/CoachController.php` — add `show()`, `publish()`
- `app/Modules/Users/src/Http/Requests/UpdateCoachProfileRequest.php` — new fields validation
- `app/Modules/Users/src/Actions/UpdateCoachProfileAction.php` — geocoding + slug + audit
- `app/Modules/Users/src/Http/Resources/CoachResource.php` — new fields
- `app/Modules/Users/src/Providers/UsersServiceProvider.php` — register new services
- `app/Modules/Users/routes/api.php` — all new routes
- `config/filesystems.php` — add r2_docs disk

### New tests
- `tests/Feature/Users/CoachProfileTest.php`
- `tests/Feature/Users/CoachAvailabilityTest.php`
- `tests/Feature/Users/CoachCertificationTest.php`
- `tests/Feature/Users/PublicCoachProfileTest.php`

### Test trait (shared helper)
- `tests/Feature/Users/Concerns/WithCoachUser.php`

---

## Task 1: Install S3 Package + Configure Cloudflare R2 Disk

**Files:**
- Modify: `config/filesystems.php`
- Modify: `.env` (add new env vars)

- [ ] **Step 1: Install flysystem-aws-s3-v3**

```bash
composer require league/flysystem-aws-s3-v3 "^3.0"
```

Expected: package installs without errors.

- [ ] **Step 2: Add R2 disk to `config/filesystems.php`**

In the `disks` array, add after the `s3` entry:

```php
'r2_docs' => [
    'driver'                  => 's3',
    'key'                     => env('R2_ACCESS_KEY_ID'),
    'secret'                  => env('R2_SECRET_ACCESS_KEY'),
    'region'                  => 'auto',
    'bucket'                  => env('R2_BUCKET', 'documents-prod'),
    'endpoint'                => env('R2_ENDPOINT'), // https://{account_id}.r2.cloudflarestorage.com
    'use_path_style_endpoint' => false,
    'throw'                   => true,
    'report'                  => false,
],
```

- [ ] **Step 3: Add env vars to `.env`**

Append to `.env`:

```
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_ENDPOINT=
R2_BUCKET=documents-prod
CDN_BASE_URL=
```

- [ ] **Step 4: Commit**

```bash
git add config/filesystems.php .env composer.json composer.lock
git commit -m "chore: add league/flysystem-aws-s3-v3 and configure Cloudflare R2 storage disk"
```

---

## Task 2: DB Migration — coaches table new columns

**Files:**
- Create: `app/Modules/Users/database/migrations/2026_04_20_100000_add_profile_fields_to_coaches_table.php`

- [ ] **Step 1: Create migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->string('tagline', 150)->nullable()->after('bio');
            $table->string('mode')->nullable()->after('tagline'); // online|in_person|hybrid
            $table->json('languages')->nullable()->after('specializations');
            $table->string('intro_video_url')->nullable()->after('website_url');
            $table->decimal('price_per_session', 8, 2)->nullable()->after('hourly_rate');
            $table->unsignedTinyInteger('cancellation_window_hours')->nullable()->after('price_per_session');
            $table->decimal('lat', 10, 7)->nullable()->after('city');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->boolean('is_published')->default(false)->after('is_visible');
        });
    }

    public function down(): void
    {
        Schema::table('coaches', function (Blueprint $table) {
            $table->dropColumn([
                'tagline', 'mode', 'languages', 'intro_video_url',
                'price_per_session', 'cancellation_window_hours',
                'lat', 'lng', 'is_published',
            ]);
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_04_20_100000_add_profile_fields_to_coaches_table` then `Migrated`.

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Users/database/migrations/2026_04_20_100000_add_profile_fields_to_coaches_table.php
git commit -m "feat(users): add tagline, mode, languages, geocoords, is_published, price_per_session to coaches table"
```

---

## Task 3: DB Migration — coach_availabilities table

**Files:**
- Create: `app/Modules/Users/database/migrations/2026_04_20_100001_create_coach_availabilities_table.php`

- [ ] **Step 1: Create migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=Sunday … 6=Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_recurring')->default(true);
            $table->timestamps();

            $table->index('coach_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_availabilities');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected: `Migrated: 2026_04_20_100001_create_coach_availabilities_table`.

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Users/database/migrations/2026_04_20_100001_create_coach_availabilities_table.php
git commit -m "feat(users): create coach_availabilities table"
```

---

## Task 4: DB Migration — certifications table

**Files:**
- Create: `app/Modules/Users/database/migrations/2026_04_20_100002_create_certifications_table.php`

- [ ] **Step 1: Create migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('issuer')->nullable();
            $table->date('issued_at')->nullable();
            $table->string('file_url'); // CDN URL (Cloudflare)
            $table->string('storage_path'); // internal path on Aruba Object Storage
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index('coach_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certifications');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected: `Migrated: 2026_04_20_100002_create_certifications_table`.

- [ ] **Step 3: Commit**

```bash
git add app/Modules/Users/database/migrations/2026_04_20_100002_create_certifications_table.php
git commit -m "feat(users): create certifications table with CDN url and storage path"
```

---

## Task 5: Models — update Coach, create CoachAvailability and Certification

**Files:**
- Modify: `app/Modules/Users/src/Models/Coach.php`
- Create: `app/Modules/Users/src/Models/CoachAvailability.php`
- Create: `app/Modules/Users/src/Models/Certification.php`

- [ ] **Step 1: Update `Coach.php`**

Replace the entire file content:

```php
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
        // Profilo pubblico
        'bio',
        'tagline',
        'description',
        'website_url',
        'intro_video_url',
        'specializations',
        'languages',
        'certifications',
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
            'certifications'              => 'array',
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
```

- [ ] **Step 2: Create `CoachAvailability.php`**

```php
<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachAvailability extends Model
{
    protected $fillable = [
        'coach_id',
        'day_of_week',
        'start_time',
        'end_time',
        'duration_minutes',
        'is_recurring',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week'      => 'integer',
            'duration_minutes' => 'integer',
            'is_recurring'     => 'boolean',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
```

- [ ] **Step 3: Create `Certification.php`**

```php
<?php

namespace App\Modules\Users\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Certification extends Model
{
    protected $fillable = [
        'coach_id',
        'name',
        'issuer',
        'issued_at',
        'file_url',
        'storage_path',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'   => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Modules/Users/src/Models/
git commit -m "feat(users): add CoachAvailability and Certification models, update Coach with new fields and relationships"
```

---

## Task 6: Services — GeocodingService and SlugService

**Files:**
- Create: `app/Modules/Users/src/Services/GeocodingService.php`
- Create: `app/Modules/Users/src/Services/SlugService.php`
- Modify: `app/Modules/Users/src/Providers/UsersServiceProvider.php`

- [ ] **Step 1: Write the failing test for GeocodingService**

Create `tests/Feature/Users/CoachProfileTest.php`:

```php
<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Services\GeocodingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoachProfileTest extends TestCase
{
    public function test_geocoding_service_returns_lat_lng_for_city(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                ['lat' => '45.4654219', 'lon' => '9.1859243'],
            ], 200),
        ]);

        $service = new GeocodingService();
        $result  = $service->geocode('Milano');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('lat', $result);
        $this->assertArrayHasKey('lng', $result);
        $this->assertEquals('45.4654219', $result['lat']);
        $this->assertEquals('9.1859243', $result['lng']);
    }

    public function test_geocoding_service_returns_null_when_city_not_found(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([], 200),
        ]);

        $service = new GeocodingService();
        $result  = $service->geocode('CittàInesistente12345');

        $this->assertNull($result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Users/CoachProfileTest.php --filter test_geocoding_service_returns_lat_lng_for_city
```

Expected: FAIL with `Class "App\Modules\Users\Services\GeocodingService" not found`.

- [ ] **Step 3: Create `GeocodingService.php`**

```php
<?php

namespace App\Modules\Users\Services;

use Illuminate\Support\Facades\Http;

class GeocodingService
{
    public function geocode(string $city): ?array
    {
        $response = Http::withHeaders([
            'User-Agent' => config('app.name') . '/1.0',
        ])->get('https://nominatim.openstreetmap.org/search', [
            'q'      => $city,
            'format' => 'json',
            'limit'  => 1,
        ]);

        $results = $response->json();

        if (empty($results)) {
            return null;
        }

        return [
            'lat' => $results[0]['lat'],
            'lng' => $results[0]['lon'],
        ];
    }
}
```

- [ ] **Step 4: Write failing test for SlugService**

Add to `tests/Feature/Users/CoachProfileTest.php`:

```php
use App\Modules\Users\Services\SlugService;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Add RefreshDatabase to the class and add this test:

public function test_slug_service_generates_slug_from_name_and_city(): void
{
    $service = new SlugService();
    $slug    = $service->generate('Mario Bianchi', 'Milano');

    $this->assertEquals('mario-bianchi-milano', $slug);
}

public function test_slug_service_deduplicates_with_counter(): void
{
    // Create a coach with the base slug so dedup is triggered
    \App\Models\User::factory()->create(['name' => 'Mario Bianchi']);
    $coach = \App\Modules\Users\Models\Coach::factory()->create(['slug' => 'mario-bianchi-milano']);

    $service = new SlugService();
    $slug    = $service->generate('Mario Bianchi', 'Milano');

    $this->assertEquals('mario-bianchi-milano-1', $slug);
}
```

> **Note:** `Coach::factory()` and `User::factory()` must exist. If factories are not yet defined, skip these tests and return to them after factories are created. The core SlugService logic can be verified manually.

- [ ] **Step 5: Create `SlugService.php`**

```php
<?php

namespace App\Modules\Users\Services;

use App\Modules\Users\Models\Coach;
use Illuminate\Support\Str;

class SlugService
{
    public function generate(string $displayName, string $city, ?int $excludeCoachId = null): string
    {
        $base = Str::slug($displayName . '-' . $city);
        $slug = $base;
        $i    = 1;

        while ($this->slugExists($slug, $excludeCoachId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeCoachId): bool
    {
        return Coach::where('slug', $slug)
            ->when($excludeCoachId, fn ($q) => $q->where('id', '!=', $excludeCoachId))
            ->exists();
    }
}
```

- [ ] **Step 6: Register services in `UsersServiceProvider.php`**

Replace the file content:

```php
<?php

namespace App\Modules\Users\Providers;

use App\Modules\Users\Services\GeocodingService;
use App\Modules\Users\Services\ObjectStorageService;
use App\Modules\Users\Services\SlugService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(GeocodingService::class);
        $this->app->singleton(SlugService::class);
        $this->app->singleton(ObjectStorageService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        Route::middleware('api')->group(__DIR__ . '/../../routes/api.php');
    }
}
```

- [ ] **Step 7: Run geocoding tests**

```bash
php artisan test tests/Feature/Users/CoachProfileTest.php
```

Expected: tests for GeocodingService pass.

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Users/src/Services/ app/Modules/Users/src/Providers/ tests/Feature/Users/
git commit -m "feat(users): add GeocodingService (Nominatim) and SlugService with dedup"
```

---

## Task 7: ObjectStorageService

**Files:**
- Create: `app/Modules/Users/src/Services/ObjectStorageService.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Users/CoachCertificationTest.php`:

```php
<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Services\ObjectStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoachCertificationTest extends TestCase
{
    public function test_object_storage_service_uploads_file_and_returns_cdn_url(): void
    {
        Storage::fake('r2_docs');
        config(['app.cdn_base_url' => 'https://cdn.example.com']);

        $file    = UploadedFile::fake()->create('cert.pdf', 500, 'application/pdf');
        $service = new ObjectStorageService();
        $result  = $service->upload($file, 'certifications/1');

        $this->assertArrayHasKey('cdn_url', $result);
        $this->assertArrayHasKey('storage_path', $result);
        $this->assertStringStartsWith('certifications/1/', $result['storage_path']);
        $this->assertStringStartsWith('https://cdn.example.com/', $result['cdn_url']);
    }

    public function test_object_storage_service_deletes_file(): void
    {
        Storage::fake('r2_docs');

        $file = UploadedFile::fake()->create('cert.pdf', 500, 'application/pdf');
        Storage::disk('r2_docs')->put('certifications/1/test.pdf', $file->get());

        $service = new ObjectStorageService();
        $service->delete('certifications/1/test.pdf');

        Storage::disk('r2_docs')->assertMissing('certifications/1/test.pdf');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/Users/CoachCertificationTest.php --filter test_object_storage_service_uploads_file_and_returns_cdn_url
```

Expected: FAIL with `Class "App\Modules\Users\Services\ObjectStorageService" not found`.

- [ ] **Step 3: Create `ObjectStorageService.php`**

```php
<?php

namespace App\Modules\Users\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ObjectStorageService
{
    private string $disk = 'r2_docs';

    public function upload(UploadedFile $file, string $folder): array
    {
        $uuid      = (string) Str::uuid();
        $ext       = $file->getClientOriginalExtension();
        $path      = $folder . '/' . $uuid . '.' . $ext;

        Storage::disk($this->disk)->put($path, $file->get());

        $cdnBase = rtrim(config('app.cdn_base_url', ''), '/');

        return [
            'storage_path' => $path,
            'cdn_url'      => $cdnBase . '/' . $path,
        ];
    }

    public function delete(string $storagePath): void
    {
        Storage::disk($this->disk)->delete($storagePath);
    }
}
```

- [ ] **Step 4: Add `CDN_BASE_URL` to config `app.php`**

Open `config/app.php`, add inside the array:

```php
'cdn_base_url' => env('CDN_BASE_URL', ''),
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/Users/CoachCertificationTest.php
```

Expected: both tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Users/src/Services/ObjectStorageService.php config/app.php
git commit -m "feat(users): add ObjectStorageService for Aruba S3-compatible storage with CDN URL"
```

---

## Task 8: Test helper trait + GET /coaches/me

**Files:**
- Create: `tests/Feature/Users/Concerns/WithCoachUser.php`
- Modify: `app/Modules/Users/src/Http/Resources/CoachResource.php`
- Modify: `app/Modules/Users/src/Http/Controllers/Api/V1/CoachController.php`
- Modify: `app/Modules/Users/routes/api.php`
- Modify: `tests/Feature/Users/CoachProfileTest.php`

- [ ] **Step 1: Create `WithCoachUser` trait**

```php
<?php

namespace Tests\Feature\Users\Concerns;

use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait WithCoachUser
{
    use RefreshDatabase;

    protected function createCoachUser(array $coachAttributes = []): array
    {
        // Create user with coach role
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        // Spatie permissions: ensure 'coach' role exists
        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'coach', 'guard_name' => 'web']);
        $user->assignRole($role);

        $coach = Coach::create(array_merge([
            'user_id' => $user->id,
            'slug'    => 'test-coach-' . $user->id,
        ], $coachAttributes));

        return [$user, $coach];
    }

    protected function actingAsCoach(User $user): static
    {
        return $this->actingAs($user)->withoutMiddleware([
            \App\Modules\Auth\Http\Middleware\JwtAuthenticate::class,
            \App\Modules\Auth\Http\Middleware\RequireVerified::class,
        ]);
    }
}
```

- [ ] **Step 2: Write failing test for `GET /coaches/me`**

Add to `tests/Feature/Users/CoachProfileTest.php`:

```php
<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Services\GeocodingService;
use App\Modules\Users\Services\SlugService;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachProfileTest extends TestCase
{
    use WithCoachUser;

    public function test_coach_can_get_own_profile(): void
    {
        [$user, $coach] = $this->createCoachUser([
            'bio'     => 'Professional coach',
            'city'    => 'Milano',
            'mode'    => 'online',
            'tagline' => 'Your best coach',
        ]);

        $response = $this->actingAsCoach($user)
            ->getJson('/api/v1/coaches/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $coach->id)
            ->assertJsonPath('data.bio', 'Professional coach')
            ->assertJsonPath('data.tagline', 'Your best coach')
            ->assertJsonPath('data.mode', 'online');
    }

    // ... (geocoding tests from Task 6 go here too)
}
```

- [ ] **Step 3: Run test to verify it fails**

```bash
php artisan test tests/Feature/Users/CoachProfileTest.php --filter test_coach_can_get_own_profile
```

Expected: FAIL with `404` — route not found.

- [ ] **Step 4: Update `CoachResource.php` to include new fields**

Replace the `toArray` return array with:

```php
public function toArray(Request $request): array
{
    $isOwner = $request->user()?->id === $this->user_id;

    return [
        // Campi pubblici
        'id'                      => $this->id,
        'slug'                    => $this->slug,
        'bio'                     => $this->bio,
        'tagline'                 => $this->tagline,
        'description'             => $this->description,
        'website_url'             => $this->website_url,
        'intro_video_url'         => $this->intro_video_url,
        'specializations'         => $this->specializations,
        'languages'               => $this->languages,
        'certifications'          => $this->certifications,
        'social_links'            => $this->social_links,
        'years_of_experience'     => $this->years_of_experience,
        'hourly_rate'             => $this->hourly_rate,
        'price_per_session'       => $this->price_per_session,
        'cancellation_window_hours' => $this->cancellation_window_hours,
        'city'                    => $this->city,
        'mode'                    => $this->mode,
        'lat'                     => $this->lat,
        'lng'                     => $this->lng,
        'is_verified'             => $this->is_verified,
        'is_visible'              => $this->is_visible,
        'is_published'            => $this->is_published,
        'user'                    => $this->whenLoaded('user', fn () => new UserResource($this->user)),

        // Campi visibili solo al proprietario
        'phone'                       => $this->when($isOwner, $this->phone),
        'address'                     => $this->when($isOwner, $this->address),
        'province'                    => $this->when($isOwner, $this->province),
        'max_clients'                 => $this->when($isOwner, $this->max_clients),
        'ragione_sociale'             => $this->when($isOwner, $this->ragione_sociale),
        'p_iva'                       => $this->when($isOwner, $this->p_iva),
        'codice_fiscale'              => $this->when($isOwner, $this->codice_fiscale),
        'tax_regime'                  => $this->when($isOwner, $this->tax_regime),
        'sdi_code'                    => $this->when($isOwner, $this->sdi_code),
        'pec'                         => $this->when($isOwner, $this->pec),
        'stripe_connect_id'           => $this->when($isOwner, $this->stripe_connect_id),
        'stripe_onboarding_completed' => $this->when($isOwner, $this->stripe_onboarding_completed),
    ];
}
```

- [ ] **Step 5: Add `show()` to `CoachController.php`**

Add this method before `update()`:

```php
public function show(Request $request): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach) {
        return response()->json(['message' => 'Profilo coach non trovato.'], 404);
    }

    return response()->json(['data' => new CoachResource($coach->load('user'))]);
}
```

- [ ] **Step 6: Add route to `routes/api.php`**

Inside the `coaches/me` prefix group, add the GET route before `patch`:

```php
Route::get('', [CoachController::class, 'show'])->middleware('throttle:60,1')->name('show');
```

- [ ] **Step 7: Run test**

```bash
php artisan test tests/Feature/Users/CoachProfileTest.php --filter test_coach_can_get_own_profile
```

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Modules/Users/src/Http/Controllers/Api/V1/CoachController.php \
        app/Modules/Users/src/Http/Resources/CoachResource.php \
        app/Modules/Users/routes/api.php \
        tests/Feature/Users/
git commit -m "feat(users): add GET /coaches/me endpoint and update CoachResource with new fields"
```

---

## Task 9: PUT /coaches/me — new fields + geocoding + slug + audit

**Files:**
- Modify: `app/Modules/Users/src/Http/Requests/UpdateCoachProfileRequest.php`
- Modify: `app/Modules/Users/src/Actions/UpdateCoachProfileAction.php`

- [ ] **Step 1: Write failing tests**

Add to `tests/Feature/Users/CoachProfileTest.php`:

```php
public function test_coach_can_update_profile_with_new_fields(): void
{
    [$user, $coach] = $this->createCoachUser();

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            ['lat' => '45.4654219', 'lon' => '9.1859243'],
        ], 200),
    ]);

    $response = $this->actingAsCoach($user)
        ->patchJson('/api/v1/coaches/me', [
            'tagline'                   => 'Top coach Milano',
            'mode'                      => 'in_person',
            'languages'                 => ['it', 'en'],
            'city'                      => 'Milano',
            'price_per_session'         => 60.00,
            'cancellation_window_hours' => 24,
            'instagram_url'             => 'https://instagram.com/coach',
        ]);

    $response->assertOk()
        ->assertJsonPath('data.tagline', 'Top coach Milano')
        ->assertJsonPath('data.mode', 'in_person')
        ->assertJsonPath('data.price_per_session', 60.00)
        ->assertJsonPath('data.lat', 45.4654219)
        ->assertJsonPath('data.lng', 9.1859243);
}

public function test_profile_update_logs_audit_event(): void
{
    [$user, $coach] = $this->createCoachUser();

    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

    $this->actingAsCoach($user)
        ->patchJson('/api/v1/coaches/me', ['bio' => 'Updated bio']);

    $this->assertDatabaseHas('audit_logs', [
        'user_id' => $user->id,
        'event'   => 'PROFILE_UPDATED',
    ]);
}

public function test_profile_update_generates_slug_if_missing(): void
{
    [$user, $coach] = $this->createCoachUser();
    $coach->update(['slug' => '']);

    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

    $this->actingAsCoach($user)
        ->patchJson('/api/v1/coaches/me', [
            'display_name' => 'Mario Bianchi',
            'city'         => 'Roma',
        ]);

    $coach->refresh();
    $this->assertEquals('mario-bianchi-roma', $coach->slug);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Users/CoachProfileTest.php --filter "test_coach_can_update_profile_with_new_fields|test_profile_update_logs_audit_event|test_profile_update_generates_slug_if_missing"
```

Expected: multiple FAILs.

- [ ] **Step 3: Update `UpdateCoachProfileRequest.php`**

Replace the `rules()` method:

```php
public function rules(): array
{
    $coachId = $this->user()->coach?->id;

    return [
        // Profilo pubblico
        'display_name'               => ['sometimes', 'nullable', 'string', 'max:255'],
        'bio'                        => ['sometimes', 'nullable', 'string', 'max:500'],
        'tagline'                    => ['sometimes', 'nullable', 'string', 'max:150'],
        'description'                => ['sometimes', 'nullable', 'string', 'max:3000'],
        'website_url'                => ['sometimes', 'nullable', 'url', 'max:255'],
        'intro_video_url'            => ['sometimes', 'nullable', 'url', 'max:255'],
        'instagram_url'              => ['sometimes', 'nullable', 'url', 'max:255'],
        'specializations'            => ['sometimes', 'nullable', 'array'],
        'specializations.*'          => ['string', 'max:100'],
        'languages'                  => ['sometimes', 'nullable', 'array'],
        'languages.*'                => ['string', 'max:10'],
        'certifications'             => ['sometimes', 'nullable', 'array'],
        'certifications.*.name'      => ['required_with:certifications', 'string', 'max:255'],
        'certifications.*.issuer'    => ['sometimes', 'nullable', 'string', 'max:255'],
        'certifications.*.year'      => ['sometimes', 'nullable', 'integer', 'min:1900', 'max:2100'],
        'social_links'               => ['sometimes', 'nullable', 'array'],
        'social_links.instagram'     => ['sometimes', 'nullable', 'url', 'max:255'],
        'social_links.facebook'      => ['sometimes', 'nullable', 'url', 'max:255'],
        'social_links.youtube'       => ['sometimes', 'nullable', 'url', 'max:255'],
        'social_links.linkedin'      => ['sometimes', 'nullable', 'url', 'max:255'],
        'social_links.twitter'       => ['sometimes', 'nullable', 'url', 'max:255'],
        'years_of_experience'        => ['sometimes', 'nullable', 'integer', 'min:0', 'max:60'],
        'mode'                       => ['sometimes', 'nullable', 'in:online,in_person,hybrid'],

        // Contatti e sede
        'phone'    => ['sometimes', 'nullable', 'regex:/^[+]?[\d\s\-()]{7,20}$/'],
        'address'  => ['sometimes', 'nullable', 'string', 'max:255'],
        'city'     => ['sometimes', 'nullable', 'string', 'max:100'],
        'province' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],

        // Tariffe e capacità
        'hourly_rate'                => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999.99'],
        'price_per_session'          => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999.99'],
        'cancellation_window_hours'  => ['sometimes', 'nullable', 'integer', 'min:0', 'max:168'],
        'max_clients'                => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
        'is_visible'                 => ['sometimes', 'boolean'],

        // Dati fiscali
        'ragione_sociale' => ['sometimes', 'nullable', 'string', 'max:255'],
        'p_iva'           => ['sometimes', 'nullable', 'regex:/^\d{11}$/', Rule::unique('coaches', 'p_iva')->ignore($coachId)],
        'codice_fiscale'  => ['sometimes', 'nullable', 'regex:/^[A-Z]{6}\d{2}[A-Z]\d{2}[A-Z]\d{3}[A-Z]$/i', Rule::unique('coaches', 'codice_fiscale')->ignore($coachId)],
        'tax_regime'      => ['sometimes', 'nullable', 'in:forfettario,ordinario,semplificato'],
        'sdi_code'        => ['sometimes', 'nullable', 'string', 'size:7', 'regex:/^[A-Z0-9]{7}$/i'],
        'pec'             => ['sometimes', 'nullable', 'email:rfc,dns'],
    ];
}
```

- [ ] **Step 4: Update `UpdateCoachProfileAction.php`**

Replace the entire file:

```php
<?php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Services\GeocodingService;
use App\Modules\Users\Services\SlugService;

class UpdateCoachProfileAction
{
    public function __construct(
        private readonly GeocodingService $geocoding,
        private readonly SlugService $slug,
        private readonly AuditLogService $audit,
    ) {}

    public function handle(Coach $coach, array $data): Coach
    {
        // Update user.name if display_name is provided
        if (isset($data['display_name'])) {
            $coach->user->update(['name' => $data['display_name']]);
            unset($data['display_name']);
        }

        // Map instagram_url → social_links.instagram
        if (array_key_exists('instagram_url', $data)) {
            $socialLinks                = $coach->social_links ?? [];
            $socialLinks['instagram']   = $data['instagram_url'];
            $data['social_links']       = $socialLinks;
            unset($data['instagram_url']);
        }

        // Geocode city if it changed
        if (isset($data['city']) && $data['city'] !== $coach->city) {
            $coords = $this->geocoding->geocode($data['city']);
            if ($coords) {
                $data['lat'] = $coords['lat'];
                $data['lng'] = $coords['lng'];
            }
        }

        $coach->update($data);
        $coach->refresh();

        // Auto-generate slug if missing
        if (empty($coach->slug)) {
            $displayName   = $coach->user->name;
            $city          = $coach->city ?? '';
            $coach->slug   = $this->slug->generate($displayName, $city, $coach->id);
            $coach->saveQuietly();
        }

        $this->audit->log('PROFILE_UPDATED', $coach->user_id);

        return $coach;
    }
}
```

- [ ] **Step 5: Run tests**

```bash
php artisan test tests/Feature/Users/CoachProfileTest.php
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Modules/Users/src/Http/Requests/UpdateCoachProfileRequest.php \
        app/Modules/Users/src/Actions/UpdateCoachProfileAction.php
git commit -m "feat(users): PUT /coaches/me — geocoding, slug auto-gen, PROFILE_UPDATED audit log, new fields"
```

---

## Task 10: PUT /coaches/me/publish

**Files:**
- Create: `app/Modules/Users/src/Actions/PublishCoachProfileAction.php`
- Modify: `app/Modules/Users/src/Http/Controllers/Api/V1/CoachController.php`
- Modify: `app/Modules/Users/routes/api.php`

- [ ] **Step 1: Write failing tests**

Add to `tests/Feature/Users/CoachProfileTest.php`:

```php
public function test_coach_can_publish_profile_when_all_required_fields_are_set(): void
{
    [$user, $coach] = $this->createCoachUser([
        'bio'               => 'Professional coach',
        'specializations'   => ['crossfit'],
        'city'              => 'Milano',
        'price_per_session' => 50.00,
    ]);

    $response = $this->actingAsCoach($user)
        ->putJson('/api/v1/coaches/me/publish');

    $response->assertOk()->assertJsonPath('data.is_published', true);
    $this->assertDatabaseHas('coaches', ['id' => $coach->id, 'is_published' => true]);
}

public function test_coach_cannot_publish_without_required_fields(): void
{
    [$user, $coach] = $this->createCoachUser(); // missing bio, specializations, city, price_per_session

    $response = $this->actingAsCoach($user)
        ->putJson('/api/v1/coaches/me/publish');

    $response->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors' => ['missing_fields']]);
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Users/CoachProfileTest.php --filter "test_coach_can_publish|test_coach_cannot_publish"
```

Expected: FAILs with 404.

- [ ] **Step 3: Create `PublishCoachProfileAction.php`**

```php
<?php

namespace App\Modules\Users\Actions;

use App\Modules\Auth\Services\AuditLogService;
use App\Modules\Users\Models\Coach;

class PublishCoachProfileAction
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function handle(Coach $coach): array
    {
        $missing = [];

        if (empty($coach->bio)) {
            $missing[] = 'bio';
        }
        if (empty($coach->specializations)) {
            $missing[] = 'specializations';
        }
        if (empty($coach->city)) {
            $missing[] = 'city';
        }
        if (is_null($coach->price_per_session)) {
            $missing[] = 'price_per_session';
        }

        if (! empty($missing)) {
            return ['ok' => false, 'missing' => $missing];
        }

        $coach->update(['is_published' => true]);
        $this->audit->log('PROFILE_PUBLISHED', $coach->user_id);

        return ['ok' => true, 'coach' => $coach->fresh()];
    }
}
```

- [ ] **Step 4: Add `publish()` to `CoachController.php`**

Add this method after `show()`:

```php
public function publish(Request $request, PublishCoachProfileAction $action): JsonResponse
{
    $coach = $request->user()->coach;

    if (! $coach) {
        return response()->json(['message' => 'Profilo coach non trovato.'], 404);
    }

    $result = $action->handle($coach);

    if (! $result['ok']) {
        return response()->json([
            'message' => 'Il profilo non soddisfa i requisiti minimi per la pubblicazione.',
            'errors'  => ['missing_fields' => $result['missing']],
        ], 422);
    }

    return response()->json(['data' => new CoachResource($result['coach']->load('user'))]);
}
```

Add the import at the top of the controller:

```php
use App\Modules\Users\Actions\PublishCoachProfileAction;
```

- [ ] **Step 5: Add route to `routes/api.php`**

Inside the `coaches/me` prefix group:

```php
Route::put('publish', [CoachController::class, 'publish'])->middleware('throttle:10,1')->name('publish');
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Users/CoachProfileTest.php --filter "test_coach_can_publish|test_coach_cannot_publish"
```

Expected: both pass.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Users/src/Actions/PublishCoachProfileAction.php \
        app/Modules/Users/src/Http/Controllers/Api/V1/CoachController.php \
        app/Modules/Users/routes/api.php
git commit -m "feat(users): add PUT /coaches/me/publish with minimum fields validation"
```

---

## Task 11: PUT /coaches/me/availability

**Files:**
- Create: `app/Modules/Users/src/Http/Requests/UpdateAvailabilityRequest.php`
- Create: `app/Modules/Users/src/Http/Resources/CoachAvailabilityResource.php`
- Create: `app/Modules/Users/src/Actions/ReplaceAvailabilityAction.php`
- Create: `app/Modules/Users/src/Http/Controllers/Api/V1/AvailabilityController.php`
- Modify: `app/Modules/Users/routes/api.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Users/CoachAvailabilityTest.php`:

```php
<?php

namespace Tests\Feature\Users;

use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class CoachAvailabilityTest extends TestCase
{
    use WithCoachUser;

    public function test_coach_can_set_availability_slots(): void
    {
        [$user, $coach] = $this->createCoachUser();

        $response = $this->actingAsCoach($user)
            ->putJson('/api/v1/coaches/me/availability', [
                'slots' => [
                    [
                        'day_of_week'      => 1,
                        'start_time'       => '09:00',
                        'end_time'         => '12:00',
                        'duration_minutes' => 60,
                        'is_recurring'     => true,
                    ],
                    [
                        'day_of_week'      => 3,
                        'start_time'       => '14:00',
                        'end_time'         => '18:00',
                        'duration_minutes' => 45,
                        'is_recurring'     => true,
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseCount('coach_availabilities', 2);
    }

    public function test_availability_replace_deletes_existing_slots(): void
    {
        [$user, $coach] = $this->createCoachUser();

        // Create initial slot
        $coach->availabilities()->create([
            'day_of_week'      => 1,
            'start_time'       => '09:00',
            'end_time'         => '12:00',
            'duration_minutes' => 60,
            'is_recurring'     => true,
        ]);

        // Replace with completely different slot
        $this->actingAsCoach($user)
            ->putJson('/api/v1/coaches/me/availability', [
                'slots' => [
                    [
                        'day_of_week'      => 5,
                        'start_time'       => '10:00',
                        'end_time'         => '13:00',
                        'duration_minutes' => 30,
                        'is_recurring'     => false,
                    ],
                ],
            ]);

        $this->assertDatabaseCount('coach_availabilities', 1);
        $this->assertDatabaseHas('coach_availabilities', ['day_of_week' => 5]);
        $this->assertDatabaseMissing('coach_availabilities', ['day_of_week' => 1]);
    }

    public function test_availability_validation_rejects_invalid_day(): void
    {
        [$user, $coach] = $this->createCoachUser();

        $response = $this->actingAsCoach($user)
            ->putJson('/api/v1/coaches/me/availability', [
                'slots' => [
                    [
                        'day_of_week'      => 9, // invalid: must be 0-6
                        'start_time'       => '09:00',
                        'end_time'         => '12:00',
                        'duration_minutes' => 60,
                        'is_recurring'     => true,
                    ],
                ],
            ]);

        $response->assertUnprocessable();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Users/CoachAvailabilityTest.php
```

Expected: FAILs with 404.

- [ ] **Step 3: Create `UpdateAvailabilityRequest.php`**

```php
<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slots'                    => ['required', 'array'],
            'slots.*.day_of_week'      => ['required', 'integer', 'between:0,6'],
            'slots.*.start_time'       => ['required', 'date_format:H:i'],
            'slots.*.end_time'         => ['required', 'date_format:H:i'],
            'slots.*.duration_minutes' => ['required', 'integer', 'min:15', 'max:240'],
            'slots.*.is_recurring'     => ['required', 'boolean'],
        ];
    }
}
```

- [ ] **Step 4: Create `CoachAvailabilityResource.php`**

```php
<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachAvailabilityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'day_of_week'      => $this->day_of_week,
            'start_time'       => $this->start_time,
            'end_time'         => $this->end_time,
            'duration_minutes' => $this->duration_minutes,
            'is_recurring'     => $this->is_recurring,
        ];
    }
}
```

- [ ] **Step 5: Create `ReplaceAvailabilityAction.php`**

```php
<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Coach;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReplaceAvailabilityAction
{
    public function handle(Coach $coach, array $slots): Collection
    {
        return DB::transaction(function () use ($coach, $slots) {
            $coach->availabilities()->delete();

            $records = array_map(fn ($slot) => array_merge($slot, ['coach_id' => $coach->id]), $slots);
            $coach->availabilities()->createMany($records);

            return $coach->availabilities()->get();
        });
    }
}
```

- [ ] **Step 6: Create `AvailabilityController.php`**

```php
<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\ReplaceAvailabilityAction;
use App\Modules\Users\Http\Requests\UpdateAvailabilityRequest;
use App\Modules\Users\Http\Resources\CoachAvailabilityResource;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function replace(UpdateAvailabilityRequest $request, ReplaceAvailabilityAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $slots = $action->handle($coach, $request->validated()['slots']);

        return response()->json(['data' => CoachAvailabilityResource::collection($slots)]);
    }
}
```

- [ ] **Step 7: Add route to `routes/api.php`**

Inside the `coaches/me` prefix group:

```php
Route::put('availability', [AvailabilityController::class, 'replace'])->middleware('throttle:20,1')->name('availability');
```

Add import at the top:

```php
use App\Modules\Users\Http\Controllers\Api\V1\AvailabilityController;
```

- [ ] **Step 8: Run tests**

```bash
php artisan test tests/Feature/Users/CoachAvailabilityTest.php
```

Expected: all pass.

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Users/src/Http/Requests/UpdateAvailabilityRequest.php \
        app/Modules/Users/src/Http/Resources/CoachAvailabilityResource.php \
        app/Modules/Users/src/Actions/ReplaceAvailabilityAction.php \
        app/Modules/Users/src/Http/Controllers/Api/V1/AvailabilityController.php \
        app/Modules/Users/routes/api.php \
        tests/Feature/Users/CoachAvailabilityTest.php
git commit -m "feat(users): add PUT /coaches/me/availability with full slot replace"
```

---

## Task 12: POST /coaches/me/certifications

**Files:**
- Create: `app/Modules/Users/src/Http/Requests/StoreCertificationRequest.php`
- Create: `app/Modules/Users/src/Http/Resources/CertificationResource.php`
- Create: `app/Modules/Users/src/Actions/StoreCertificationAction.php`
- Create: `app/Modules/Users/src/Http/Controllers/Api/V1/CertificationController.php`
- Modify: `app/Modules/Users/routes/api.php`

- [ ] **Step 1: Write failing tests**

Add to `tests/Feature/Users/CoachCertificationTest.php`:

```php
use App\Modules\Users\Models\Certification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Users\Concerns\WithCoachUser;

// Add WithCoachUser to the class. Add the following tests:

public function test_coach_can_upload_certification(): void
{
    [$user, $coach] = $this->createCoachUser();
    Storage::fake('r2_docs');
    config(['app.cdn_base_url' => 'https://cdn.example.com']);

    $file = UploadedFile::fake()->create('cert.pdf', 500, 'application/pdf');

    $response = $this->actingAsCoach($user)
        ->postJson('/api/v1/coaches/me/certifications', [
            'file'   => $file,
            'name'   => 'ISSA Personal Trainer',
            'issuer' => 'ISSA',
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'ISSA Personal Trainer')
        ->assertJsonPath('data.issuer', 'ISSA');

    $this->assertDatabaseCount('certifications', 1);
}

public function test_certification_upload_rejects_non_pdf_jpg_png(): void
{
    [$user, $coach] = $this->createCoachUser();
    Storage::fake('r2_docs');

    $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/octet-stream');

    $response = $this->actingAsCoach($user)
        ->postJson('/api/v1/coaches/me/certifications', [
            'file' => $file,
            'name' => 'Fake cert',
        ]);

    $response->assertUnprocessable();
}

public function test_certification_upload_rejects_files_over_10mb(): void
{
    [$user, $coach] = $this->createCoachUser();
    Storage::fake('r2_docs');

    $file = UploadedFile::fake()->create('big.pdf', 11000, 'application/pdf'); // 11MB

    $response = $this->actingAsCoach($user)
        ->postJson('/api/v1/coaches/me/certifications', [
            'file' => $file,
            'name' => 'Big cert',
        ]);

    $response->assertUnprocessable();
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Users/CoachCertificationTest.php --filter "test_coach_can_upload|test_certification_upload"
```

Expected: FAILs.

- [ ] **Step 3: Create `StoreCertificationRequest.php`**

```php
<?php

namespace App\Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'      => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'name'      => ['required', 'string', 'max:255'],
            'issuer'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'issued_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
```

- [ ] **Step 4: Create `CertificationResource.php`**

```php
<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'issuer'      => $this->issuer,
            'issued_at'   => $this->issued_at?->toDateString(),
            'file_url'    => $this->file_url,
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5: Create `StoreCertificationAction.php`**

```php
<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Certification;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Services\ObjectStorageService;
use Illuminate\Http\UploadedFile;

class StoreCertificationAction
{
    public function __construct(private readonly ObjectStorageService $storage) {}

    public function handle(Coach $coach, UploadedFile $file, array $data): Certification
    {
        $uploaded = $this->storage->upload($file, 'certifications/' . $coach->id);

        return $coach->certificationRecords()->create([
            'name'         => $data['name'],
            'issuer'       => $data['issuer'] ?? null,
            'issued_at'    => $data['issued_at'] ?? null,
            'file_url'     => $uploaded['cdn_url'],
            'storage_path' => $uploaded['storage_path'],
        ]);
    }
}
```

- [ ] **Step 6: Create `CertificationController.php`**

```php
<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Actions\DeleteCertificationAction;
use App\Modules\Users\Actions\StoreCertificationAction;
use App\Modules\Users\Http\Requests\StoreCertificationRequest;
use App\Modules\Users\Http\Resources\CertificationResource;
use App\Modules\Users\Models\Certification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    public function store(StoreCertificationRequest $request, StoreCertificationAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $certification = $action->handle($coach, $request->file('file'), $request->validated());

        return response()->json(['data' => new CertificationResource($certification)], 201);
    }

    public function destroy(Request $request, Certification $certification, DeleteCertificationAction $action): JsonResponse
    {
        $coach = $request->user()->coach;

        if (! $coach || $certification->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $action->handle($certification);

        return response()->json(null, 204);
    }
}
```

- [ ] **Step 7: Add routes to `routes/api.php`**

Inside the `coaches/me` prefix group:

```php
Route::post('certifications', [CertificationController::class, 'store'])->middleware('throttle:20,1')->name('certifications.store');
Route::delete('certifications/{certification}', [CertificationController::class, 'destroy'])->middleware('throttle:20,1')->name('certifications.destroy');
```

Add import at the top:

```php
use App\Modules\Users\Http\Controllers\Api\V1\CertificationController;
```

- [ ] **Step 8: Run tests**

```bash
php artisan test tests/Feature/Users/CoachCertificationTest.php --filter "test_coach_can_upload|test_certification_upload"
```

Expected: pass.

- [ ] **Step 9: Commit**

```bash
git add app/Modules/Users/src/Http/Requests/StoreCertificationRequest.php \
        app/Modules/Users/src/Http/Resources/CertificationResource.php \
        app/Modules/Users/src/Actions/StoreCertificationAction.php \
        app/Modules/Users/src/Http/Controllers/Api/V1/CertificationController.php \
        app/Modules/Users/routes/api.php
git commit -m "feat(users): add POST /coaches/me/certifications with file upload to Aruba Object Storage"
```

---

## Task 13: DELETE /coaches/me/certifications/:id

**Files:**
- Create: `app/Modules/Users/src/Actions/DeleteCertificationAction.php`

- [ ] **Step 1: Write failing test**

Add to `tests/Feature/Users/CoachCertificationTest.php`:

```php
public function test_coach_can_delete_own_certification(): void
{
    [$user, $coach] = $this->createCoachUser();
    Storage::fake('r2_docs');
    config(['app.cdn_base_url' => 'https://cdn.example.com']);

    // Seed a file in fake storage
    Storage::disk('r2_docs')->put('certifications/1/test.pdf', 'content');

    $cert = Certification::create([
        'coach_id'     => $coach->id,
        'name'         => 'Test Cert',
        'file_url'     => 'https://cdn.example.com/certifications/1/test.pdf',
        'storage_path' => 'certifications/1/test.pdf',
    ]);

    $response = $this->actingAsCoach($user)
        ->deleteJson('/api/v1/coaches/me/certifications/' . $cert->id);

    $response->assertNoContent();
    $this->assertDatabaseMissing('certifications', ['id' => $cert->id]);
    Storage::disk('r2_docs')->assertMissing('certifications/1/test.pdf');
}

public function test_coach_cannot_delete_another_coachs_certification(): void
{
    [$user, $coach]     = $this->createCoachUser();
    [$user2, $coach2]   = $this->createCoachUser();

    $cert = Certification::create([
        'coach_id'     => $coach2->id,
        'name'         => 'Other cert',
        'file_url'     => 'https://cdn.example.com/cert.pdf',
        'storage_path' => 'certifications/2/cert.pdf',
    ]);

    $response = $this->actingAsCoach($user)
        ->deleteJson('/api/v1/coaches/me/certifications/' . $cert->id);

    $response->assertForbidden();
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Users/CoachCertificationTest.php --filter "test_coach_can_delete|test_coach_cannot_delete"
```

Expected: FAILs.

- [ ] **Step 3: Create `DeleteCertificationAction.php`**

```php
<?php

namespace App\Modules\Users\Actions;

use App\Modules\Users\Models\Certification;
use App\Modules\Users\Services\ObjectStorageService;

class DeleteCertificationAction
{
    public function __construct(private readonly ObjectStorageService $storage) {}

    public function handle(Certification $certification): void
    {
        $this->storage->delete($certification->storage_path);
        $certification->delete();
    }
}
```

- [ ] **Step 4: Run tests**

```bash
php artisan test tests/Feature/Users/CoachCertificationTest.php
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Modules/Users/src/Actions/DeleteCertificationAction.php \
        tests/Feature/Users/CoachCertificationTest.php
git commit -m "feat(users): add DELETE /coaches/me/certifications/:id with Object Storage cleanup"
```

---

## Task 14: GET /coaches/:id/public

**Files:**
- Create: `app/Modules/Users/src/Http/Resources/PublicCoachResource.php`
- Create: `app/Modules/Users/src/Http/Controllers/Api/V1/PublicCoachController.php`
- Modify: `app/Modules/Users/routes/api.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Users/PublicCoachProfileTest.php`:

```php
<?php

namespace Tests\Feature\Users;

use App\Modules\Users\Models\Certification;
use Tests\Feature\Users\Concerns\WithCoachUser;
use Tests\TestCase;

class PublicCoachProfileTest extends TestCase
{
    use WithCoachUser;

    public function test_anyone_can_view_published_coach_public_profile(): void
    {
        [$user, $coach] = $this->createCoachUser([
            'bio'               => 'Pro coach',
            'tagline'           => 'Transform yourself',
            'specializations'   => ['yoga'],
            'city'              => 'Roma',
            'mode'              => 'online',
            'price_per_session' => 45.00,
            'is_published'      => true,
        ]);

        $response = $this->getJson('/api/v1/coaches/' . $coach->id . '/public');

        $response->assertOk()
            ->assertJsonPath('data.bio', 'Pro coach')
            ->assertJsonPath('data.city', 'Roma')
            ->assertJsonStructure(['data' => ['seo' => ['title', 'description', 'og_image']]]);
    }

    public function test_public_profile_returns_only_verified_certifications(): void
    {
        [$user, $coach] = $this->createCoachUser(['is_published' => true]);

        Certification::create([
            'coach_id'     => $coach->id,
            'name'         => 'Verified Cert',
            'file_url'     => 'https://cdn.example.com/cert1.pdf',
            'storage_path' => 'certifications/1/cert1.pdf',
            'verified_at'  => now(),
        ]);
        Certification::create([
            'coach_id'     => $coach->id,
            'name'         => 'Unverified Cert',
            'file_url'     => 'https://cdn.example.com/cert2.pdf',
            'storage_path' => 'certifications/1/cert2.pdf',
            'verified_at'  => null,
        ]);

        $response = $this->getJson('/api/v1/coaches/' . $coach->id . '/public');

        $response->assertOk()
            ->assertJsonCount(1, 'data.certifications')
            ->assertJsonPath('data.certifications.0.name', 'Verified Cert');
    }

    public function test_public_profile_returns_next_5_availability_slots(): void
    {
        [$user, $coach] = $this->createCoachUser(['is_published' => true]);

        for ($i = 0; $i < 7; $i++) {
            $coach->availabilities()->create([
                'day_of_week'      => $i % 7,
                'start_time'       => '09:00',
                'end_time'         => '10:00',
                'duration_minutes' => 60,
                'is_recurring'     => true,
            ]);
        }

        $response = $this->getJson('/api/v1/coaches/' . $coach->id . '/public');

        $response->assertOk()
            ->assertJsonCount(5, 'data.next_slots');
    }

    public function test_unpublished_coach_returns_404_on_public_profile(): void
    {
        [$user, $coach] = $this->createCoachUser(['is_published' => false]);

        $response = $this->getJson('/api/v1/coaches/' . $coach->id . '/public');

        $response->assertNotFound();
    }

    public function test_public_profile_does_not_expose_private_fields(): void
    {
        [$user, $coach] = $this->createCoachUser([
            'is_published' => true,
            'p_iva'        => '12345678901',
            'pec'          => 'coach@pec.it',
        ]);

        $response = $this->getJson('/api/v1/coaches/' . $coach->id . '/public');

        $data = $response->json('data');
        $this->assertArrayNotHasKey('p_iva', $data);
        $this->assertArrayNotHasKey('pec', $data);
        $this->assertArrayNotHasKey('stripe_connect_id', $data);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/Users/PublicCoachProfileTest.php
```

Expected: all FAILs with 404.

- [ ] **Step 3: Create `PublicCoachResource.php`**

```php
<?php

namespace App\Modules\Users\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCoachResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $displayName = $this->user?->name ?? $this->slug;
        $city        = $this->city ?? '';

        return [
            'id'                      => $this->id,
            'slug'                    => $this->slug,
            'display_name'            => $displayName,
            'bio'                     => $this->bio,
            'tagline'                 => $this->tagline,
            'specializations'         => $this->specializations,
            'languages'               => $this->languages,
            'mode'                    => $this->mode,
            'city'                    => $city,
            'hourly_rate'             => $this->hourly_rate,
            'price_per_session'       => $this->price_per_session,
            'cancellation_window_hours' => $this->cancellation_window_hours,
            'years_of_experience'     => $this->years_of_experience,
            'is_verified'             => $this->is_verified,

            // Rating — populated by Reviews module when available
            'rating'                  => null,
            'reviews_count'           => 0,

            // Verified certifications only
            'certifications'          => CertificationResource::collection(
                $this->whenLoaded('verifiedCertifications')
            ),

            // Next 5 availability slots
            'next_slots'              => CoachAvailabilityResource::collection(
                $this->whenLoaded('nextSlots')
            ),

            // SEO metadata
            'seo' => [
                'title'       => $displayName . ' — Coach ' . ($this->tagline ?? 'Personal Trainer'),
                'description' => mb_substr($this->bio ?? '', 0, 160),
                'og_image'    => $this->user?->avatar ?? null,
            ],
        ];
    }
}
```

- [ ] **Step 4: Create `PublicCoachController.php`**

```php
<?php

namespace App\Modules\Users\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Coach;
use App\Modules\Users\Http\Resources\PublicCoachResource;
use Illuminate\Http\JsonResponse;

class PublicCoachController extends Controller
{
    public function show(Coach $coach): JsonResponse
    {
        if (! $coach->is_published) {
            return response()->json(['message' => 'Profilo coach non trovato.'], 404);
        }

        $coach->load([
            'user',
            'verifiedCertifications',
        ]);

        // Load only the next 5 availability slots ordered by day_of_week
        $coach->setRelation(
            'nextSlots',
            $coach->availabilities()->orderBy('day_of_week')->orderBy('start_time')->limit(5)->get()
        );

        return response()->json(['data' => new PublicCoachResource($coach)]);
    }
}
```

- [ ] **Step 5: Add route to `routes/api.php`**

Outside the authenticated middleware group (this route requires no auth), add at the end of the file:

```php
Route::prefix('api/v1')->middleware('api')->group(function () {
    Route::get('coaches/{coach}/public', [PublicCoachController::class, 'show'])
        ->middleware('throttle:120,1')
        ->name('coaches.public');
});
```

Add import at the top:

```php
use App\Modules\Users\Http\Controllers\Api\V1\PublicCoachController;
```

- [ ] **Step 6: Run tests**

```bash
php artisan test tests/Feature/Users/PublicCoachProfileTest.php
```

Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add app/Modules/Users/src/Http/Resources/PublicCoachResource.php \
        app/Modules/Users/src/Http/Controllers/Api/V1/PublicCoachController.php \
        app/Modules/Users/routes/api.php \
        tests/Feature/Users/PublicCoachProfileTest.php
git commit -m "feat(users): add GET /coaches/:id/public with verified certs, availability slots, and SEO metadata"
```

---

## Task 15: Run full test suite + final cleanup

- [ ] **Step 1: Run all Users module tests**

```bash
php artisan test tests/Feature/Users/
```

Expected: all pass.

- [ ] **Step 2: Run full test suite to check for regressions**

```bash
php artisan test
```

Expected: no regressions.

- [ ] **Step 3: Update `app/Modules/Users/README.md`**

Mark all implemented items as done (✅) in the gap analysis table.

- [ ] **Step 4: Final commit**

```bash
git add tests/ app/Modules/Users/README.md
git commit -m "test(users): complete feature test coverage for all new coach endpoints"
```

---

## Spec Coverage Checklist

| Spec requirement | Task |
|---|---|
| `GET /coaches/me` — full profile | Task 8 |
| `PUT /coaches/me` — displayName, bio, tagline, specializations, languages, mode, city, instagramUrl, websiteUrl, introVideoUrl, pricePerSession, cancellationWindowHours | Tasks 2 + 9 |
| Geocoding Nominatim city → lat/lng | Tasks 6 + 9 |
| Slug auto-generate if missing | Tasks 6 + 9 |
| PROFILE_UPDATED audit log | Task 9 |
| `PUT /coaches/me/publish` — min fields validation | Task 10 |
| `PUT /coaches/me/availability` — full slot replace | Task 11 |
| `POST /coaches/me/certifications` — Aruba upload, CDN URL, 10MB, PDF/JPG/PNG | Tasks 7 + 12 |
| `DELETE /coaches/me/certifications/:id` — DB + storage cleanup | Task 13 |
| `GET /coaches/:id/public` — no auth, verified certs only, next 5 slots, SEO metadata | Task 14 |
