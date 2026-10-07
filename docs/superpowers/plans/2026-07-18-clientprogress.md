# ClientProgress Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the `ClientProgress` Laravel module — time-series body metrics + progress photos + coach-authored recurring check-in forms — exposed via `api/v1` (JWT) and a Filament admin surface.

**Architecture:** New `nwidart/laravel-modules` module `Modules\ClientProgress`, mirroring the `WorkoutPlans` module. Coach↔client access enforced by inline ownership checks (a shared guard trait), not Policies. Photos use `spatie/laravel-medialibrary` with a 400×400 `thumb` conversion; signed URLs via `temporaryUrl()` when the media disk is s3, else plain `getUrl()`. Check-in submission notifies the coach via a DB notification + a module-owned stub push contract. Minimal hourly scheduled command dispatches check-in reminders; deferred concerns tracked in `modules/ClientProgress/MODULE_TODO.md`.

**Tech Stack:** Laravel 13, PHP 8.4, nwidart/laravel-modules, spatie/laravel-medialibrary, spatie/laravel-permission, Filament v5, PHPUnit 12. Custom JWT auth middleware (`jwt.auth`).

## Global Constraints

- Module namespace: `Modules\ClientProgress\...`. Models under `Modules\ClientProgress\Models`. Controllers under `Modules\ClientProgress\Http\Controllers\Api\V1`.
- Client model: `App\Modules\Users\Models\Client`. Coach model: `App\Modules\Users\Models\Coach`. User: `App\Models\User` (`->coach`, `->client`, `isCoach()`).
- API prefix `api/v1`; group middleware `['jwt.auth','verified.email']`; role gating `role:coach` / `role:client`; per-route `throttle:N,1`; named routes `clientprogress.api.*`.
- Controllers extend `App\Http\Controllers\Controller`. Responses: `response()->json(['data' => XResource...], 201?)`. Create returns 201.
- FormRequests validate **camelCase** input keys. Do not override `authorize()` (defaults true; authorization is done inline in controllers).
- API Resources are plain `Illuminate\Http\Resources\Json\JsonResource`, snake_case-ish output but camelCase where existing code does (follow field names in this plan verbatim).
- Enums: `#[Spatie\TypeScriptTransformer\Attributes\TypeScript]` + `implements Filament\Support\Contracts\HasLabel` with `getLabel()`.
- Ownership-denied → `response()->json(['message' => 'Non autorizzato.'], 403)`.
- After PHP changes: `vendor/bin/pint --dirty --format agent`.
- Test command: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='^Modules\\ClientProgress\\Tests'`.
- Migrations: `foreignId(...)->constrained()->cascadeOnDelete()`, `enum(...)`, composite indexes as specified. Dated prefix `2026_07_18_0000NN_*`.

---

### Task 1: Scaffold module, enable it, register Filament nav group

**Files:**
- Create (scaffolded): `modules/ClientProgress/**` (via recipe)
- Modify: `modules_statuses.json` (via `module:enable`)
- Create: `modules/ClientProgress/app/Filament/ClientProgressPlugin.php`
- Modify: `modules/ClientProgress/app/Providers/ClientProgressServiceProvider.php`

**Interfaces:**
- Produces: enabled module `ClientProgress` with `ClientProgressServiceProvider` (extends `App\Providers\ModuleServiceProvider`, `$name='ClientProgress'`, `$nameLower='clientprogress'`, `$providers=[RouteServiceProvider::class]`) and `RouteServiceProvider::map()` loading web+api routes; `ClientProgressPlugin` registering nav group "Client Progress".

- [ ] **Step 1: Scaffold the module**

Run:
```bash
php artisan miocoach:recipe ClientProgress --no-interaction
composer dump-autoload
php artisan module:enable ClientProgress
```
Expected: `modules/ClientProgress/` created; `modules_statuses.json` gains `"ClientProgress": true`.

- [ ] **Step 2: Verify provider + route provider match the WorkoutPlans shape**

Ensure `modules/ClientProgress/app/Providers/ClientProgressServiceProvider.php` is:
```php
<?php

namespace Modules\ClientProgress\Providers;

use App\Providers\ModuleServiceProvider;

class ClientProgressServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'ClientProgress';

    protected string $nameLower = 'clientprogress';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
```
And `modules/ClientProgress/app/Providers/RouteServiceProvider.php`:
```php
<?php

namespace Modules\ClientProgress\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::middleware('web')
            ->group(module_path('ClientProgress', '/routes/web.php'));
        Route::middleware('api')
            ->group(module_path('ClientProgress', '/routes/api.php'));
    }
}
```
If the recipe produced different content, correct it to the above.

- [ ] **Step 3: Write the Filament plugin**

Create `modules/ClientProgress/app/Filament/ClientProgressPlugin.php`:
```php
<?php

namespace Modules\ClientProgress\Filament;

use App\Filament\ModulePlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class ClientProgressPlugin implements Plugin
{
    use ModulePlugin;

    public function getModuleName(): string
    {
        return 'ClientProgress';
    }

    public function getId(): string
    {
        return 'clientprogress';
    }

    public function boot(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make()
                ->label(__('Client Progress'))
                ->icon(Heroicon::OutlinedChartBar)
                ->collapsible(),
        ]);
    }
}
```

- [ ] **Step 4: Write the failing test — module is enabled and routes load**

Create `modules/ClientProgress/tests/Feature/ModuleSmokeTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Feature;

use Tests\TestCase;

class ModuleSmokeTest extends TestCase
{
    public function test_module_is_enabled(): void
    {
        $statuses = json_decode(file_get_contents(base_path('modules_statuses.json')), true);
        $this->assertTrue($statuses['ClientProgress'] ?? false);
    }
}
```
Confirm `modules/ClientProgress/tests/TestCase.php` (or the base `Tests\TestCase`) is resolvable; the recipe usually creates a module `tests/TestCase.php`. If the module test namespace differs, follow the scaffolded convention and keep `Tests\TestCase` as the base import used across WorkoutPlans tests (check `modules/WorkoutPlans/tests/Feature` for the exact base class and copy it).

- [ ] **Step 5: Run the test**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='ModuleSmokeTest'`
Expected: PASS.

- [ ] **Step 6: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress modules_statuses.json
git commit -m "feat(clientprogress): scaffold module + filament nav group"
```

---

### Task 2: Enums

**Files:**
- Create: `modules/ClientProgress/app/Enums/MetricType.php`
- Create: `modules/ClientProgress/app/Enums/LoggedBy.php`
- Create: `modules/ClientProgress/app/Enums/CheckinFrequency.php`
- Create: `modules/ClientProgress/app/Enums/QuestionType.php`
- Test: `modules/ClientProgress/tests/Unit/MetricTypeTest.php`

**Interfaces:**
- Produces: `MetricType` (cases weight, body_fat, waist, chest, hips, arm, thigh, calf) with `public function column(): string`; `LoggedBy` (coach, client); `CheckinFrequency` (daily, weekly, biweekly, monthly); `QuestionType` (text, number, rating, yes_no). All backed-string enums with `getLabel()`.

- [ ] **Step 1: Write the failing test**

Create `modules/ClientProgress/tests/Unit/MetricTypeTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Unit;

use Modules\ClientProgress\Enums\MetricType;
use Tests\TestCase;

class MetricTypeTest extends TestCase
{
    public function test_metric_maps_to_column(): void
    {
        $this->assertSame('weight_kg', MetricType::Weight->column());
        $this->assertSame('body_fat_pct', MetricType::BodyFat->column());
        $this->assertSame('waist_cm', MetricType::Waist->column());
        $this->assertSame('calf_cm', MetricType::Calf->column());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='MetricTypeTest'`
Expected: FAIL (class not found).

- [ ] **Step 3: Create `MetricType`**

`modules/ClientProgress/app/Enums/MetricType.php`:
```php
<?php

namespace Modules\ClientProgress\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum MetricType: string implements HasLabel
{
    case Weight = 'weight';
    case BodyFat = 'body_fat';
    case Waist = 'waist';
    case Chest = 'chest';
    case Hips = 'hips';
    case Arm = 'arm';
    case Thigh = 'thigh';
    case Calf = 'calf';

    /**
     * The body_metrics DB column backing this metric.
     */
    public function column(): string
    {
        return match ($this) {
            self::Weight => 'weight_kg',
            self::BodyFat => 'body_fat_pct',
            self::Waist => 'waist_cm',
            self::Chest => 'chest_cm',
            self::Hips => 'hips_cm',
            self::Arm => 'arm_cm',
            self::Thigh => 'thigh_cm',
            self::Calf => 'calf_cm',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Weight => __('Weight'),
            self::BodyFat => __('Body Fat %'),
            self::Waist => __('Waist'),
            self::Chest => __('Chest'),
            self::Hips => __('Hips'),
            self::Arm => __('Arm'),
            self::Thigh => __('Thigh'),
            self::Calf => __('Calf'),
        };
    }
}
```

- [ ] **Step 4: Create `LoggedBy`**

`modules/ClientProgress/app/Enums/LoggedBy.php`:
```php
<?php

namespace Modules\ClientProgress\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum LoggedBy: string implements HasLabel
{
    case Coach = 'coach';
    case Client = 'client';

    public function getLabel(): string
    {
        return match ($this) {
            self::Coach => __('Coach'),
            self::Client => __('Client'),
        };
    }
}
```

- [ ] **Step 5: Create `CheckinFrequency`**

`modules/ClientProgress/app/Enums/CheckinFrequency.php`:
```php
<?php

namespace Modules\ClientProgress\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum CheckinFrequency: string implements HasLabel
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function getLabel(): string
    {
        return match ($this) {
            self::Daily => __('Daily'),
            self::Weekly => __('Weekly'),
            self::Biweekly => __('Biweekly'),
            self::Monthly => __('Monthly'),
        };
    }
}
```

- [ ] **Step 6: Create `QuestionType`**

`modules/ClientProgress/app/Enums/QuestionType.php`:
```php
<?php

namespace Modules\ClientProgress\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum QuestionType: string implements HasLabel
{
    case Text = 'text';
    case Number = 'number';
    case Rating = 'rating';
    case YesNo = 'yes_no';

    public function getLabel(): string
    {
        return match ($this) {
            self::Text => __('Text'),
            self::Number => __('Number'),
            self::Rating => __('Rating'),
            self::YesNo => __('Yes / No'),
        };
    }
}
```

- [ ] **Step 7: Run test to verify PASS**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='MetricTypeTest'`
Expected: PASS.

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress/app/Enums modules/ClientProgress/tests/Unit
git commit -m "feat(clientprogress): add metric, logged-by, frequency, question-type enums"
```

---

### Task 3: Migrations

**Files:**
- Create: `modules/ClientProgress/database/migrations/2026_07_18_000001_create_body_metrics_table.php`
- Create: `modules/ClientProgress/database/migrations/2026_07_18_000002_create_progress_photos_table.php`
- Create: `modules/ClientProgress/database/migrations/2026_07_18_000003_create_checkin_forms_table.php`
- Create: `modules/ClientProgress/database/migrations/2026_07_18_000004_create_checkin_form_client_table.php`
- Create: `modules/ClientProgress/database/migrations/2026_07_18_000005_create_checkin_responses_table.php`
- Test: `modules/ClientProgress/tests/Feature/MigrationsTest.php`

**Interfaces:**
- Produces: tables `body_metrics`, `progress_photos`, `checkin_forms`, `checkin_form_client`, `checkin_responses` with the columns defined in the spec.

- [ ] **Step 1: Write the failing test**

Create `modules/ClientProgress/tests/Feature/MigrationsTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tables_exist_with_key_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('body_metrics', [
            'client_id', 'logged_by', 'weight_kg', 'body_fat_pct',
            'waist_cm', 'chest_cm', 'hips_cm', 'arm_cm', 'thigh_cm', 'calf_cm',
            'notes', 'measured_at',
        ]));
        $this->assertTrue(Schema::hasColumns('progress_photos', ['client_id', 'logged_by', 'taken_at']));
        $this->assertTrue(Schema::hasColumns('checkin_forms', ['coach_id', 'title', 'frequency', 'send_time', 'questions', 'is_active']));
        $this->assertTrue(Schema::hasColumns('checkin_form_client', ['checkin_form_id', 'client_id', 'assigned_at']));
        $this->assertTrue(Schema::hasColumns('checkin_responses', ['checkin_form_id', 'client_id', 'answers', 'submitted_at']));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='MigrationsTest'`
Expected: FAIL (table not found).

- [ ] **Step 3: Create `body_metrics` migration**

`..._000001_create_body_metrics_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('body_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('logged_by', ['coach', 'client']);
            $table->decimal('weight_kg', 6, 2)->nullable();
            $table->decimal('body_fat_pct', 5, 2)->nullable();
            $table->decimal('waist_cm', 5, 1)->nullable();
            $table->decimal('chest_cm', 5, 1)->nullable();
            $table->decimal('hips_cm', 5, 1)->nullable();
            $table->decimal('arm_cm', 5, 1)->nullable();
            $table->decimal('thigh_cm', 5, 1)->nullable();
            $table->decimal('calf_cm', 5, 1)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('measured_at');
            $table->timestamps();

            $table->index(['client_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('body_metrics');
    }
};
```

- [ ] **Step 4: Create `progress_photos` migration**

`..._000002_create_progress_photos_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->enum('logged_by', ['coach', 'client']);
            $table->timestamp('taken_at');
            $table->timestamps();

            $table->index(['client_id', 'taken_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_photos');
    }
};
```

- [ ] **Step 5: Create `checkin_forms` migration**

`..._000003_create_checkin_forms_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkin_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->enum('frequency', ['daily', 'weekly', 'biweekly', 'monthly']);
            $table->time('send_time');
            $table->json('questions');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['coach_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_forms');
    }
};
```

- [ ] **Step 6: Create `checkin_form_client` migration**

`..._000004_create_checkin_form_client_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkin_form_client', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkin_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->unique(['checkin_form_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_form_client');
    }
};
```

- [ ] **Step 7: Create `checkin_responses` migration**

`..._000005_create_checkin_responses_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkin_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkin_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->json('answers');
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['checkin_form_id', 'client_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkin_responses');
    }
};
```

- [ ] **Step 8: Run test to verify PASS**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='MigrationsTest'`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add modules/ClientProgress/database/migrations modules/ClientProgress/tests/Feature/MigrationsTest.php
git commit -m "feat(clientprogress): add migrations for metrics, photos, checkin forms/responses"
```

---

### Task 4: Models, factories, and cross-module relations

**Files:**
- Create: `modules/ClientProgress/app/Models/BodyMetric.php`
- Create: `modules/ClientProgress/app/Models/ProgressPhoto.php`
- Create: `modules/ClientProgress/app/Models/CheckinForm.php`
- Create: `modules/ClientProgress/app/Models/CheckinResponse.php`
- Create: `modules/ClientProgress/database/factories/BodyMetricFactory.php`
- Create: `modules/ClientProgress/database/factories/ProgressPhotoFactory.php`
- Create: `modules/ClientProgress/database/factories/CheckinFormFactory.php`
- Create: `modules/ClientProgress/database/factories/CheckinResponseFactory.php`
- Modify: `modules/ClientProgress/app/Providers/ClientProgressServiceProvider.php`
- Test: `modules/ClientProgress/tests/Feature/ModelRelationsTest.php`

**Interfaces:**
- Produces:
  - `BodyMetric`: `belongsTo(Client)`; casts `logged_by`→`LoggedBy`, `measured_at`→`datetime`, decimals; `newFactory()`.
  - `ProgressPhoto implements HasMedia`: `belongsTo(Client)`; `registerMediaCollections()` collection `photo` singleFile jpg/png; `registerMediaConversions()` `thumb` 400×400; casts `logged_by`, `taken_at`.
  - `CheckinForm`: `belongsTo(Coach)`, `belongsToMany(Client,'checkin_form_client')->withPivot('assigned_at')` as `clients()`, `hasMany(CheckinResponse)`; casts `frequency`→`CheckinFrequency`, `questions`→`array`, `is_active`→`bool`; scope `active`.
  - `CheckinResponse`: `belongsTo(CheckinForm)`, `belongsTo(Client)`; casts `answers`→`array`, `submitted_at`→`datetime`.
  - Client relations (via `resolveRelationUsing` in provider `register()`): `bodyMetrics()`, `progressPhotos()`, `checkinResponses()`, `assignedCheckinForms()`. Coach relation: `checkinForms()`.

- [ ] **Step 1: Write the failing test**

Create `modules/ClientProgress/tests/Feature/ModelRelationsTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ClientProgress\Enums\CheckinFrequency;
use Modules\ClientProgress\Models\BodyMetric;
use Modules\ClientProgress\Models\CheckinForm;
use Tests\TestCase;

class ModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_has_body_metrics(): void
    {
        $client = Client::factory()->create();
        BodyMetric::factory()->for($client)->create(['weight_kg' => 80.5]);

        $this->assertCount(1, $client->bodyMetrics);
        $this->assertSame('80.50', (string) $client->bodyMetrics->first()->weight_kg);
    }

    public function test_checkin_form_casts_and_relations(): void
    {
        $coach = Coach::factory()->create();
        $form = CheckinForm::factory()->for($coach)->create([
            'frequency' => CheckinFrequency::Weekly,
            'questions' => [['id' => 'q1', 'text' => 'Come va?', 'type' => 'text', 'required' => true, 'order' => 1]],
        ]);

        $this->assertInstanceOf(CheckinFrequency::class, $form->frequency);
        $this->assertIsArray($form->questions);
        $this->assertTrue($coach->checkinForms->contains($form));
    }
}
```
(Confirm `Client::factory()` and `Coach::factory()` exist — they do, per the Users module. If a client factory requires a user, it handles that internally.)

- [ ] **Step 2: Run to verify it fails**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='ModelRelationsTest'`
Expected: FAIL (class not found).

- [ ] **Step 3: Create `BodyMetric` model**

`modules/ClientProgress/app/Models/BodyMetric.php`:
```php
<?php

namespace Modules\ClientProgress\Models;

use App\Modules\Users\Models\Client;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ClientProgress\Database\Factories\BodyMetricFactory;
use Modules\ClientProgress\Enums\LoggedBy;

/**
 * @property int $id
 * @property int $client_id
 * @property LoggedBy $logged_by
 * @property float|null $weight_kg
 * @property float|null $body_fat_pct
 * @property float|null $waist_cm
 * @property float|null $chest_cm
 * @property float|null $hips_cm
 * @property float|null $arm_cm
 * @property float|null $thigh_cm
 * @property float|null $calf_cm
 * @property string|null $notes
 * @property Carbon $measured_at
 */
class BodyMetric extends Model
{
    /** @use HasFactory<BodyMetricFactory> */
    use HasFactory;

    protected static function newFactory(): BodyMetricFactory
    {
        return BodyMetricFactory::new();
    }

    protected $fillable = [
        'client_id', 'logged_by', 'weight_kg', 'body_fat_pct',
        'waist_cm', 'chest_cm', 'hips_cm', 'arm_cm', 'thigh_cm', 'calf_cm',
        'notes', 'measured_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_by' => LoggedBy::class,
            'measured_at' => 'datetime',
            'weight_kg' => 'decimal:2',
            'body_fat_pct' => 'decimal:2',
            'waist_cm' => 'decimal:1',
            'chest_cm' => 'decimal:1',
            'hips_cm' => 'decimal:1',
            'arm_cm' => 'decimal:1',
            'thigh_cm' => 'decimal:1',
            'calf_cm' => 'decimal:1',
        ];
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
```

- [ ] **Step 4: Create `ProgressPhoto` model**

`modules/ClientProgress/app/Models/ProgressPhoto.php`:
```php
<?php

namespace Modules\ClientProgress\Models;

use App\Modules\Users\Models\Client;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ClientProgress\Database\Factories\ProgressPhotoFactory;
use Modules\ClientProgress\Enums\LoggedBy;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $client_id
 * @property LoggedBy $logged_by
 * @property Carbon $taken_at
 */
class ProgressPhoto extends Model implements HasMedia
{
    /** @use HasFactory<ProgressPhotoFactory> */
    use HasFactory, InteractsWithMedia;

    protected static function newFactory(): ProgressPhotoFactory
    {
        return ProgressPhotoFactory::new();
    }

    protected $fillable = ['client_id', 'logged_by', 'taken_at'];

    protected function casts(): array
    {
        return [
            'logged_by' => LoggedBy::class,
            'taken_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photo')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('photo')
            ->fit(\Spatie\Image\Enums\Fit::Crop, 400, 400)
            ->optimize();
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
```
(Verify the `Fit` enum import path against the installed `spatie/image` version — WorkoutPlans/Coach uses `->width()->height()`. If `Fit` is unavailable, use `->width(400)->height(400)->optimize()` which crop-fits via medialibrary defaults; prefer matching the Coach model's conversion style.)

- [ ] **Step 5: Create `CheckinForm` model**

`modules/ClientProgress/app/Models/CheckinForm.php`:
```php
<?php

namespace Modules\ClientProgress\Models;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\ClientProgress\Database\Factories\CheckinFormFactory;
use Modules\ClientProgress\Enums\CheckinFrequency;

/**
 * @property int $id
 * @property int $coach_id
 * @property string $title
 * @property CheckinFrequency $frequency
 * @property string $send_time
 * @property array<int, array{id:string,text:string,type:string,required:bool,order:int}> $questions
 * @property bool $is_active
 */
class CheckinForm extends Model
{
    /** @use HasFactory<CheckinFormFactory> */
    use HasFactory;

    protected static function newFactory(): CheckinFormFactory
    {
        return CheckinFormFactory::new();
    }

    protected $fillable = ['coach_id', 'title', 'frequency', 'send_time', 'questions', 'is_active'];

    protected function casts(): array
    {
        return [
            'frequency' => CheckinFrequency::class,
            'questions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @return BelongsTo<Coach, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    /** @return BelongsToMany<Client, $this> */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'checkin_form_client')
            ->withPivot('assigned_at')
            ->withTimestamps();
    }

    /** @return HasMany<CheckinResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(CheckinResponse::class);
    }
}
```

- [ ] **Step 6: Create `CheckinResponse` model**

`modules/ClientProgress/app/Models/CheckinResponse.php`:
```php
<?php

namespace Modules\ClientProgress\Models;

use App\Modules\Users\Models\Client;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\ClientProgress\Database\Factories\CheckinResponseFactory;

/**
 * @property int $id
 * @property int $checkin_form_id
 * @property int $client_id
 * @property array<int, array{questionId:string,value:mixed}> $answers
 * @property Carbon $submitted_at
 */
class CheckinResponse extends Model
{
    /** @use HasFactory<CheckinResponseFactory> */
    use HasFactory;

    protected static function newFactory(): CheckinResponseFactory
    {
        return CheckinResponseFactory::new();
    }

    protected $fillable = ['checkin_form_id', 'client_id', 'answers', 'submitted_at'];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<CheckinForm, $this> */
    public function checkinForm(): BelongsTo
    {
        return $this->belongsTo(CheckinForm::class);
    }

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
```

- [ ] **Step 7: Create factories**

`modules/ClientProgress/database/factories/BodyMetricFactory.php`:
```php
<?php

namespace Modules\ClientProgress\Database\Factories;

use App\Modules\Users\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ClientProgress\Enums\LoggedBy;
use Modules\ClientProgress\Models\BodyMetric;

class BodyMetricFactory extends Factory
{
    protected $model = BodyMetric::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'logged_by' => LoggedBy::Coach,
            'weight_kg' => $this->faker->randomFloat(2, 55, 110),
            'body_fat_pct' => $this->faker->randomFloat(2, 8, 35),
            'waist_cm' => $this->faker->randomFloat(1, 60, 110),
            'chest_cm' => $this->faker->randomFloat(1, 80, 130),
            'hips_cm' => $this->faker->randomFloat(1, 80, 120),
            'arm_cm' => $this->faker->randomFloat(1, 25, 45),
            'thigh_cm' => $this->faker->randomFloat(1, 45, 70),
            'calf_cm' => $this->faker->randomFloat(1, 30, 45),
            'notes' => null,
            'measured_at' => now(),
        ];
    }
}
```

`modules/ClientProgress/database/factories/ProgressPhotoFactory.php`:
```php
<?php

namespace Modules\ClientProgress\Database\Factories;

use App\Modules\Users\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ClientProgress\Enums\LoggedBy;
use Modules\ClientProgress\Models\ProgressPhoto;

class ProgressPhotoFactory extends Factory
{
    protected $model = ProgressPhoto::class;

    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'logged_by' => LoggedBy::Client,
            'taken_at' => now(),
        ];
    }
}
```

`modules/ClientProgress/database/factories/CheckinFormFactory.php`:
```php
<?php

namespace Modules\ClientProgress\Database\Factories;

use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ClientProgress\Enums\CheckinFrequency;
use Modules\ClientProgress\Models\CheckinForm;

class CheckinFormFactory extends Factory
{
    protected $model = CheckinForm::class;

    public function definition(): array
    {
        return [
            'coach_id' => Coach::factory(),
            'title' => $this->faker->sentence(3),
            'frequency' => CheckinFrequency::Weekly,
            'send_time' => '19:00',
            'questions' => [
                ['id' => 'q1', 'text' => 'Come ti senti?', 'type' => 'rating', 'required' => true, 'order' => 1],
                ['id' => 'q2', 'text' => 'Note libere', 'type' => 'text', 'required' => false, 'order' => 2],
            ],
            'is_active' => true,
        ];
    }
}
```

`modules/ClientProgress/database/factories/CheckinResponseFactory.php`:
```php
<?php

namespace Modules\ClientProgress\Database\Factories;

use App\Modules\Users\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ClientProgress\Models\CheckinForm;
use Modules\ClientProgress\Models\CheckinResponse;

class CheckinResponseFactory extends Factory
{
    protected $model = CheckinResponse::class;

    public function definition(): array
    {
        return [
            'checkin_form_id' => CheckinForm::factory(),
            'client_id' => Client::factory(),
            'answers' => [
                ['questionId' => 'q1', 'value' => 4],
                ['questionId' => 'q2', 'value' => 'Tutto ok'],
            ],
            'submitted_at' => now(),
        ];
    }
}
```

- [ ] **Step 8: Register cross-module relations in provider `register()`**

Edit `modules/ClientProgress/app/Providers/ClientProgressServiceProvider.php` — add a `register()` override that calls `parent::register()` then bolts relations onto `Client`/`Coach`:
```php
<?php

namespace Modules\ClientProgress\Providers;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use App\Providers\ModuleServiceProvider;
use Modules\ClientProgress\Models\BodyMetric;
use Modules\ClientProgress\Models\CheckinForm;
use Modules\ClientProgress\Models\CheckinResponse;
use Modules\ClientProgress\Models\ProgressPhoto;

class ClientProgressServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'ClientProgress';

    protected string $nameLower = 'clientprogress';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        Client::resolveRelationUsing('bodyMetrics', fn (Client $client) => $client->hasMany(BodyMetric::class));
        Client::resolveRelationUsing('progressPhotos', fn (Client $client) => $client->hasMany(ProgressPhoto::class));
        Client::resolveRelationUsing('checkinResponses', fn (Client $client) => $client->hasMany(CheckinResponse::class));
        Client::resolveRelationUsing('assignedCheckinForms', fn (Client $client) => $client->belongsToMany(CheckinForm::class, 'checkin_form_client'));
        Coach::resolveRelationUsing('checkinForms', fn (Coach $coach) => $coach->hasMany(CheckinForm::class));
    }
}
```

- [ ] **Step 9: Run test to verify PASS**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='ModelRelationsTest'`
Expected: PASS.

- [ ] **Step 10: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress/app modules/ClientProgress/database/factories modules/ClientProgress/tests/Feature/ModelRelationsTest.php
git commit -m "feat(clientprogress): models, factories, cross-module client/coach relations"
```

---

### Task 5: Client-access guard trait + BodyMetric API (store + metrics index)

**Files:**
- Create: `modules/ClientProgress/app/Http/Controllers/Api/V1/Concerns/AuthorizesClientAccess.php`
- Create: `modules/ClientProgress/app/Http/Requests/StoreBodyMetricRequest.php`
- Create: `modules/ClientProgress/app/Http/Requests/MetricsQueryRequest.php`
- Create: `modules/ClientProgress/app/Http/Resources/BodyMetricResource.php`
- Create: `modules/ClientProgress/app/Http/Resources/MetricPointResource.php`
- Create: `modules/ClientProgress/app/Http/Controllers/Api/V1/BodyMetricController.php`
- Modify: `modules/ClientProgress/routes/api.php`
- Test: `modules/ClientProgress/tests/Feature/BodyMetricApiTest.php`

**Interfaces:**
- Consumes: `Client`, `Coach`, `BodyMetric`, `MetricType`, `LoggedBy`.
- Produces:
  - trait `AuthorizesClientAccess` with `protected function authorizeClientAccess(\App\Models\User $user, Client $client): void` — throws 403 JSON via `abort_unless`. Rule: allowed if `$user->client?->id === $client->id` OR `$user->coach` exists and `$user->coach->athletes()->where('athlete_id', $client->id)->exists()`.
  - `BodyMetricController@store(StoreBodyMetricRequest, Client)` → 201 `BodyMetricResource`.
  - `BodyMetricController@index(MetricsQueryRequest, Client)` → `{data: [{date, value}]}`.
  - Routes named `clientprogress.api.metrics.store` / `.index`.

- [ ] **Step 1: Write the failing test**

Create `modules/ClientProgress/tests/Feature/BodyMetricApiTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ClientProgress\Models\BodyMetric;
use Tests\TestCase;

class BodyMetricApiTest extends TestCase
{
    use RefreshDatabase;

    private function coachLinkedTo(Client $client): Coach
    {
        $coach = Coach::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        return $coach;
    }

    public function test_coach_can_store_metric_for_linked_client(): void
    {
        $client = Client::factory()->create();
        $coach = $this->coachLinkedTo($client);

        $response = $this->actingAs($coach->user)->postJson("/api/v1/clients/{$client->id}/metrics", [
            'weightKg' => 82.4,
            'bodyFatPct' => 15.2,
            'measurements' => ['waistCm' => 84.5, 'chestCm' => 102.0],
            'notes' => 'Ok',
            'loggedBy' => 'coach',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('body_metrics', [
            'client_id' => $client->id,
            'logged_by' => 'coach',
            'weight_kg' => 82.40,
            'waist_cm' => 84.5,
        ]);
    }

    public function test_unlinked_coach_is_denied(): void
    {
        $client = Client::factory()->create();
        $otherCoach = Coach::factory()->create();

        $this->actingAs($otherCoach->user)
            ->postJson("/api/v1/clients/{$client->id}/metrics", ['loggedBy' => 'coach', 'weightKg' => 80])
            ->assertForbidden();
    }

    public function test_metrics_index_returns_date_value_series(): void
    {
        $client = Client::factory()->create();
        $coach = $this->coachLinkedTo($client);
        BodyMetric::factory()->for($client)->create(['weight_kg' => 80, 'measured_at' => '2026-06-01 08:00:00']);
        BodyMetric::factory()->for($client)->create(['weight_kg' => 79, 'measured_at' => '2026-06-08 08:00:00']);

        $response = $this->actingAs($coach->user)
            ->getJson("/api/v1/clients/{$client->id}/metrics?metric=weight&from=2026-06-01&to=2026-06-30");

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.value', '80.00');
    }
}
```
(Confirm the JWT `jwt.auth` middleware honors `actingAs()` for a `User` guard in tests, matching WorkoutPlans feature tests. If WorkoutPlans tests use a different auth helper — e.g. a JWT token header or `actingAs($user, 'api')` — copy that exact pattern here and in all later API tests.)

- [ ] **Step 2: Run to verify it fails**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='BodyMetricApiTest'`
Expected: FAIL (route/class missing).

- [ ] **Step 3: Create the guard trait**

`modules/ClientProgress/app/Http/Controllers/Api/V1/Concerns/AuthorizesClientAccess.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Controllers\Api\V1\Concerns;

use App\Models\User;
use App\Modules\Users\Models\Client;
use Symfony\Component\HttpFoundation\Response;

trait AuthorizesClientAccess
{
    protected function authorizeClientAccess(User $user, Client $client): void
    {
        $isSelf = $user->client?->id === $client->id;
        $isLinkedCoach = $user->coach
            && $user->coach->athletes()->where('athlete_id', $client->id)->exists();

        abort_unless($isSelf || $isLinkedCoach, Response::HTTP_FORBIDDEN, 'Non autorizzato.');
    }
}
```

- [ ] **Step 4: Create the FormRequests**

`modules/ClientProgress/app/Http/Requests/StoreBodyMetricRequest.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\ClientProgress\Enums\LoggedBy;

class StoreBodyMetricRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'weightKg' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'bodyFatPct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'measurements' => ['nullable', 'array'],
            'measurements.waistCm' => ['nullable', 'numeric', 'min:0', 'max:9999.9'],
            'measurements.chestCm' => ['nullable', 'numeric', 'min:0', 'max:9999.9'],
            'measurements.hipsCm' => ['nullable', 'numeric', 'min:0', 'max:9999.9'],
            'measurements.armCm' => ['nullable', 'numeric', 'min:0', 'max:9999.9'],
            'measurements.thighCm' => ['nullable', 'numeric', 'min:0', 'max:9999.9'],
            'measurements.calfCm' => ['nullable', 'numeric', 'min:0', 'max:9999.9'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'loggedBy' => ['required', Rule::enum(LoggedBy::class)],
        ];
    }
}
```

`modules/ClientProgress/app/Http/Requests/MetricsQueryRequest.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\ClientProgress\Enums\MetricType;

class MetricsQueryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'metric' => ['required', Rule::enum(MetricType::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
```

- [ ] **Step 5: Create the Resources**

`modules/ClientProgress/app/Http/Resources/BodyMetricResource.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ClientProgress\Models\BodyMetric;

/** @mixin BodyMetric */
class BodyMetricResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clientId' => $this->client_id,
            'loggedBy' => $this->logged_by,
            'weightKg' => $this->weight_kg,
            'bodyFatPct' => $this->body_fat_pct,
            'measurements' => [
                'waistCm' => $this->waist_cm,
                'chestCm' => $this->chest_cm,
                'hipsCm' => $this->hips_cm,
                'armCm' => $this->arm_cm,
                'thighCm' => $this->thigh_cm,
                'calfCm' => $this->calf_cm,
            ],
            'notes' => $this->notes,
            'measuredAt' => $this->measured_at?->toIso8601String(),
        ];
    }
}
```

`modules/ClientProgress/app/Http/Resources/MetricPointResource.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Expects each resource item to be an object/array with `date` and `value` keys.
 */
class MetricPointResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource->date,
            'value' => $this->resource->value,
        ];
    }
}
```

- [ ] **Step 6: Create the controller**

`modules/ClientProgress/app/Http/Controllers/Api/V1/BodyMetricController.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;
use Modules\ClientProgress\Enums\MetricType;
use Modules\ClientProgress\Http\Controllers\Api\V1\Concerns\AuthorizesClientAccess;
use Modules\ClientProgress\Http\Requests\MetricsQueryRequest;
use Modules\ClientProgress\Http\Requests\StoreBodyMetricRequest;
use Modules\ClientProgress\Http\Resources\BodyMetricResource;
use Modules\ClientProgress\Http\Resources\MetricPointResource;
use Modules\ClientProgress\Models\BodyMetric;

class BodyMetricController extends Controller
{
    use AuthorizesClientAccess;

    public function store(StoreBodyMetricRequest $request, Client $client): JsonResponse
    {
        $this->authorizeClientAccess($request->user(), $client);

        $m = $request->validated('measurements', []);

        $metric = BodyMetric::create([
            'client_id' => $client->id,
            'logged_by' => $request->validated('loggedBy'),
            'weight_kg' => $request->validated('weightKg'),
            'body_fat_pct' => $request->validated('bodyFatPct'),
            'waist_cm' => $m['waistCm'] ?? null,
            'chest_cm' => $m['chestCm'] ?? null,
            'hips_cm' => $m['hipsCm'] ?? null,
            'arm_cm' => $m['armCm'] ?? null,
            'thigh_cm' => $m['thighCm'] ?? null,
            'calf_cm' => $m['calfCm'] ?? null,
            'notes' => $request->validated('notes'),
            'measured_at' => now(),
        ]);

        return response()->json(['data' => new BodyMetricResource($metric)], 201);
    }

    public function index(MetricsQueryRequest $request, Client $client): JsonResponse
    {
        $this->authorizeClientAccess($request->user(), $client);

        $column = MetricType::from($request->validated('metric'))->column();

        $points = BodyMetric::query()
            ->where('client_id', $client->id)
            ->whereNotNull($column)
            ->when($request->validated('from'), fn ($q, $from) => $q->where('measured_at', '>=', $from))
            ->when($request->validated('to'), fn ($q, $to) => $q->where('measured_at', '<=', $to.' 23:59:59'))
            ->orderBy('measured_at')
            ->get([
                'measured_at as date',
                $column.' as value',
            ]);

        return response()->json(['data' => MetricPointResource::collection($points)]);
    }
}
```

- [ ] **Step 7: Register routes**

Set `modules/ClientProgress/routes/api.php` to:
```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\ClientProgress\Http\Controllers\Api\V1\BodyMetricController;

Route::prefix('api/v1')->middleware(['jwt.auth', 'verified.email'])->group(function () {

    Route::prefix('clients/{client}')->group(function () {
        Route::post('metrics', [BodyMetricController::class, 'store'])
            ->middleware('throttle:60,1')
            ->name('clientprogress.api.metrics.store');

        Route::get('metrics', [BodyMetricController::class, 'index'])
            ->middleware('throttle:120,1')
            ->name('clientprogress.api.metrics.index');
    });
});
```

- [ ] **Step 8: Run test to verify PASS**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='BodyMetricApiTest'`
Expected: PASS.

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress/app modules/ClientProgress/routes/api.php modules/ClientProgress/tests/Feature/BodyMetricApiTest.php
git commit -m "feat(clientprogress): body-metric store + chartable metrics index api"
```

---

### Task 6: ProgressPhoto API (upload + list with signed URLs)

**Files:**
- Create: `modules/ClientProgress/app/Http/Requests/StoreProgressPhotoRequest.php`
- Create: `modules/ClientProgress/app/Http/Requests/PhotosQueryRequest.php`
- Create: `modules/ClientProgress/app/Http/Resources/ProgressPhotoResource.php`
- Create: `modules/ClientProgress/app/Http/Controllers/Api/V1/ProgressPhotoController.php`
- Modify: `modules/ClientProgress/routes/api.php`
- Test: `modules/ClientProgress/tests/Feature/ProgressPhotoApiTest.php`

**Interfaces:**
- Consumes: `AuthorizesClientAccess`, `ProgressPhoto`, `LoggedBy`.
- Produces: `ProgressPhotoController@store` (multipart `photo`) → 201 `ProgressPhotoResource`; `@index` → list with `thumbUrl`/`fullUrl`. Routes `clientprogress.api.metrics.photos.store` / `.index`.

- [ ] **Step 1: Write the failing test**

Create `modules/ClientProgress/tests/Feature/ProgressPhotoApiTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgressPhotoApiTest extends TestCase
{
    use RefreshDatabase;

    private function coachLinkedTo(Client $client): Coach
    {
        $coach = Coach::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        return $coach;
    }

    public function test_client_can_upload_photo(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $client = Client::factory()->create();

        $response = $this->actingAs($client->user)->postJson("/api/v1/clients/{$client->id}/metrics/photos", [
            'photo' => UploadedFile::fake()->image('progress.jpg', 800, 800),
            'loggedBy' => 'client',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.loggedBy', 'client');
        $this->assertDatabaseHas('progress_photos', ['client_id' => $client->id, 'logged_by' => 'client']);
    }

    public function test_photo_too_large_is_rejected(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $client = Client::factory()->create();

        $this->actingAs($client->user)->postJson("/api/v1/clients/{$client->id}/metrics/photos", [
            'photo' => UploadedFile::fake()->create('big.jpg', 11000, 'image/jpeg'), // 11 MB
            'loggedBy' => 'client',
        ])->assertJsonValidationErrorFor('photo');
    }

    public function test_list_returns_urls(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $client = Client::factory()->create();
        $coach = $this->coachLinkedTo($client);

        $this->actingAs($client->user)->postJson("/api/v1/clients/{$client->id}/metrics/photos", [
            'photo' => UploadedFile::fake()->image('p.jpg', 800, 800),
            'loggedBy' => 'client',
        ])->assertCreated();

        $this->actingAs($coach->user)
            ->getJson("/api/v1/clients/{$client->id}/metrics/photos")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['id', 'takenAt', 'loggedBy', 'thumbUrl', 'fullUrl']]]);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='ProgressPhotoApiTest'`
Expected: FAIL.

- [ ] **Step 3: Create the FormRequests**

`modules/ClientProgress/app/Http/Requests/StoreProgressPhotoRequest.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\ClientProgress\Enums\LoggedBy;

class StoreProgressPhotoRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:10240'], // 10 MB
            'loggedBy' => ['required', Rule::enum(LoggedBy::class)],
            'takenAt' => ['nullable', 'date'],
        ];
    }
}
```

`modules/ClientProgress/app/Http/Requests/PhotosQueryRequest.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PhotosQueryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
```

- [ ] **Step 4: Create the Resource (signed URL logic)**

`modules/ClientProgress/app/Http/Resources/ProgressPhotoResource.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ClientProgress\Models\ProgressPhoto;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** @mixin ProgressPhoto */
class ProgressPhotoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $media = $this->getFirstMedia('photo');

        return [
            'id' => $this->id,
            'clientId' => $this->client_id,
            'loggedBy' => $this->logged_by,
            'takenAt' => $this->taken_at?->toIso8601String(),
            'thumbUrl' => $this->urlFor($media, 'thumb'),
            'fullUrl' => $this->urlFor($media, ''),
        ];
    }

    private function urlFor(?Media $media, string $conversion): ?string
    {
        if (! $media) {
            return null;
        }

        $driver = config("filesystems.disks.{$media->disk}.driver");

        if ($driver === 's3') {
            return $media->getTemporaryUrl(now()->addHour(), $conversion);
        }

        return $conversion === '' ? $media->getUrl() : $media->getUrl($conversion);
    }
}
```

- [ ] **Step 5: Create the controller**

`modules/ClientProgress/app/Http/Controllers/Api/V1/ProgressPhotoController.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;
use Modules\ClientProgress\Http\Controllers\Api\V1\Concerns\AuthorizesClientAccess;
use Modules\ClientProgress\Http\Requests\PhotosQueryRequest;
use Modules\ClientProgress\Http\Requests\StoreProgressPhotoRequest;
use Modules\ClientProgress\Http\Resources\ProgressPhotoResource;
use Modules\ClientProgress\Models\ProgressPhoto;

class ProgressPhotoController extends Controller
{
    use AuthorizesClientAccess;

    public function store(StoreProgressPhotoRequest $request, Client $client): JsonResponse
    {
        $this->authorizeClientAccess($request->user(), $client);

        $photo = ProgressPhoto::create([
            'client_id' => $client->id,
            'logged_by' => $request->validated('loggedBy'),
            'taken_at' => $request->validated('takenAt') ?? now(),
        ]);

        $photo->addMediaFromRequest('photo')->toMediaCollection('photo');

        return response()->json(['data' => new ProgressPhotoResource($photo->fresh('media'))], 201);
    }

    public function index(PhotosQueryRequest $request, Client $client): JsonResponse
    {
        $this->authorizeClientAccess($request->user(), $client);

        $photos = ProgressPhoto::query()
            ->with('media')
            ->where('client_id', $client->id)
            ->when($request->validated('from'), fn ($q, $from) => $q->where('taken_at', '>=', $from))
            ->when($request->validated('to'), fn ($q, $to) => $q->where('taken_at', '<=', $to.' 23:59:59'))
            ->orderByDesc('taken_at')
            ->get();

        return response()->json(['data' => ProgressPhotoResource::collection($photos)]);
    }
}
```

- [ ] **Step 6: Add routes**

Add inside the `clients/{client}` prefix group in `modules/ClientProgress/routes/api.php` (and import `ProgressPhotoController`):
```php
Route::post('metrics/photos', [ProgressPhotoController::class, 'store'])
    ->middleware('throttle:30,1')
    ->name('clientprogress.api.metrics.photos.store');

Route::get('metrics/photos', [ProgressPhotoController::class, 'index'])
    ->middleware('throttle:120,1')
    ->name('clientprogress.api.metrics.photos.index');
```
Note: register `metrics/photos` **before** the `metrics` GET is fine (distinct paths), but ensure the `metrics/photos` routes sit inside the same `clients/{client}` group.

- [ ] **Step 7: Run test to verify PASS**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='ProgressPhotoApiTest'`
Expected: PASS. (If media conversion generation errors in the test env because `thumb` needs a queued conversion, add `->nonQueued()` in `registerMediaConversions()`; verify against how Coach conversions behave in tests.)

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress/app modules/ClientProgress/routes/api.php modules/ClientProgress/tests/Feature/ProgressPhotoApiTest.php
git commit -m "feat(clientprogress): progress-photo upload + signed-url listing api"
```

---

### Task 7: CheckinForm API (create+assign, list, update, assign)

**Files:**
- Create: `modules/ClientProgress/app/Http/Controllers/Api/V1/Concerns/ResolvesCoach.php`
- Create: `modules/ClientProgress/app/Http/Requests/StoreCheckinFormRequest.php`
- Create: `modules/ClientProgress/app/Http/Requests/UpdateCheckinFormRequest.php`
- Create: `modules/ClientProgress/app/Http/Requests/AssignCheckinFormRequest.php`
- Create: `modules/ClientProgress/app/Http/Resources/CheckinFormResource.php`
- Create: `modules/ClientProgress/app/Http/Controllers/Api/V1/CheckinFormController.php`
- Modify: `modules/ClientProgress/routes/api.php`
- Test: `modules/ClientProgress/tests/Feature/CheckinFormApiTest.php`

**Interfaces:**
- Produces:
  - trait `ResolvesCoach` with `protected function resolveCoach(User $user): Coach` (abort 403 if no coach) and `protected function assertOwnsForm(Coach $coach, CheckinForm $form): void`.
  - `CheckinFormController@store/index/update/assign`. Routes `clientprogress.api.checkin-forms.{store,index,update,assign}`.

- [ ] **Step 1: Write the failing test**

Create `modules/ClientProgress/tests/Feature/CheckinFormApiTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ClientProgress\Models\CheckinForm;
use Tests\TestCase;

class CheckinFormApiTest extends TestCase
{
    use RefreshDatabase;

    private function coachWithClient(): array
    {
        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        return [$coach, $client];
    }

    public function test_coach_creates_form_and_assigns_clients(): void
    {
        [$coach, $client] = $this->coachWithClient();

        $response = $this->actingAs($coach->user)->postJson('/api/v1/coaches/me/checkin-forms', [
            'title' => 'Weekly check',
            'frequency' => 'weekly',
            'sendTime' => '19:00',
            'questions' => [
                ['id' => 'q1', 'text' => 'Peso?', 'type' => 'number', 'required' => true, 'order' => 1],
            ],
            'clientIds' => [$client->id],
        ]);

        $response->assertCreated()->assertJsonPath('data.title', 'Weekly check');
        $form = CheckinForm::first();
        $this->assertTrue($form->clients()->where('clients.id', $client->id)->exists());
    }

    public function test_index_lists_only_own_forms(): void
    {
        [$coach] = $this->coachWithClient();
        CheckinForm::factory()->for($coach)->count(2)->create();
        CheckinForm::factory()->for(Coach::factory()->create())->create();

        $this->actingAs($coach->user)->getJson('/api/v1/coaches/me/checkin-forms')
            ->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_cannot_update_another_coachs_form(): void
    {
        [$coach] = $this->coachWithClient();
        $foreign = CheckinForm::factory()->for(Coach::factory()->create())->create();

        $this->actingAs($coach->user)->putJson("/api/v1/coaches/me/checkin-forms/{$foreign->id}", [
            'title' => 'Hacked', 'frequency' => 'daily', 'sendTime' => '08:00',
            'questions' => [['id' => 'q1', 'text' => 'x', 'type' => 'text', 'required' => false, 'order' => 1]],
        ])->assertForbidden();
    }

    public function test_assign_rejects_client_not_belonging_to_coach(): void
    {
        [$coach] = $this->coachWithClient();
        $form = CheckinForm::factory()->for($coach)->create();
        $stranger = Client::factory()->create();

        $this->actingAs($coach->user)->postJson("/api/v1/coaches/me/checkin-forms/{$form->id}/assign", [
            'clientIds' => [$stranger->id],
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='CheckinFormApiTest'`
Expected: FAIL.

- [ ] **Step 3: Create the `ResolvesCoach` trait**

`modules/ClientProgress/app/Http/Controllers/Api/V1/Concerns/ResolvesCoach.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Controllers\Api\V1\Concerns;

use App\Models\User;
use App\Modules\Users\Models\Coach;
use Modules\ClientProgress\Models\CheckinForm;
use Symfony\Component\HttpFoundation\Response;

trait ResolvesCoach
{
    protected function resolveCoach(User $user): Coach
    {
        abort_unless($user->coach !== null, Response::HTTP_FORBIDDEN, 'Non autorizzato.');

        return $user->coach;
    }

    protected function assertOwnsForm(Coach $coach, CheckinForm $form): void
    {
        abort_unless($form->coach_id === $coach->id, Response::HTTP_FORBIDDEN, 'Non autorizzato.');
    }

    /**
     * @param  array<int, int>  $clientIds
     */
    protected function assertCoachOwnsClients(Coach $coach, array $clientIds): void
    {
        $ownedCount = $coach->athletes()->whereIn('athlete_id', $clientIds)->count();

        abort_unless($ownedCount === count(array_unique($clientIds)), Response::HTTP_FORBIDDEN, 'Non autorizzato.');
    }
}
```

- [ ] **Step 4: Create the FormRequests**

`modules/ClientProgress/app/Http/Requests/StoreCheckinFormRequest.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\ClientProgress\Enums\CheckinFrequency;
use Modules\ClientProgress\Enums\QuestionType;

class StoreCheckinFormRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::enum(CheckinFrequency::class)],
            'sendTime' => ['required', 'date_format:H:i'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['required', 'string', 'max:64'],
            'questions.*.text' => ['required', 'string', 'max:500'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.required' => ['required', 'boolean'],
            'questions.*.order' => ['required', 'integer', 'min:0'],
            'clientIds' => ['nullable', 'array'],
            'clientIds.*' => ['integer', 'exists:clients,id'],
        ];
    }
}
```

`modules/ClientProgress/app/Http/Requests/UpdateCheckinFormRequest.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\ClientProgress\Enums\CheckinFrequency;
use Modules\ClientProgress\Enums\QuestionType;

class UpdateCheckinFormRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'frequency' => ['required', Rule::enum(CheckinFrequency::class)],
            'sendTime' => ['required', 'date_format:H:i'],
            'questions' => ['required', 'array', 'min:1'],
            'questions.*.id' => ['required', 'string', 'max:64'],
            'questions.*.text' => ['required', 'string', 'max:500'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.required' => ['required', 'boolean'],
            'questions.*.order' => ['required', 'integer', 'min:0'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
```

`modules/ClientProgress/app/Http/Requests/AssignCheckinFormRequest.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignCheckinFormRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'clientIds' => ['required', 'array', 'min:1'],
            'clientIds.*' => ['integer', 'exists:clients,id'],
        ];
    }
}
```

- [ ] **Step 5: Create the Resource**

`modules/ClientProgress/app/Http/Resources/CheckinFormResource.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ClientProgress\Models\CheckinForm;

/** @mixin CheckinForm */
class CheckinFormResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'frequency' => $this->frequency,
            'sendTime' => substr((string) $this->send_time, 0, 5),
            'questions' => $this->questions,
            'isActive' => $this->is_active,
            'clientIds' => $this->whenLoaded('clients', fn () => $this->clients->pluck('id')),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Create the controller**

`modules/ClientProgress/app/Http/Controllers/Api/V1/CheckinFormController.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Modules\ClientProgress\Http\Controllers\Api\V1\Concerns\ResolvesCoach;
use Modules\ClientProgress\Http\Requests\AssignCheckinFormRequest;
use Modules\ClientProgress\Http\Requests\StoreCheckinFormRequest;
use Modules\ClientProgress\Http\Requests\UpdateCheckinFormRequest;
use Modules\ClientProgress\Http\Resources\CheckinFormResource;
use Modules\ClientProgress\Models\CheckinForm;

class CheckinFormController extends Controller
{
    use ResolvesCoach;

    public function index(): JsonResponse
    {
        $coach = $this->resolveCoach(request()->user());

        $forms = CheckinForm::query()
            ->where('coach_id', $coach->id)
            ->with('clients:id')
            ->latest()
            ->get();

        return response()->json(['data' => CheckinFormResource::collection($forms)]);
    }

    public function store(StoreCheckinFormRequest $request): JsonResponse
    {
        $coach = $this->resolveCoach($request->user());
        $clientIds = $request->validated('clientIds', []);

        if (! empty($clientIds)) {
            $this->assertCoachOwnsClients($coach, $clientIds);
        }

        $form = DB::transaction(function () use ($coach, $request, $clientIds) {
            $form = CheckinForm::create([
                'coach_id' => $coach->id,
                'title' => $request->validated('title'),
                'frequency' => $request->validated('frequency'),
                'send_time' => $request->validated('sendTime'),
                'questions' => $request->validated('questions'),
                'is_active' => true,
            ]);

            if (! empty($clientIds)) {
                $form->clients()->attach(
                    collect($clientIds)->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()]])->all()
                );
            }

            return $form;
        });

        return response()->json(['data' => new CheckinFormResource($form->load('clients:id'))], 201);
    }

    public function update(UpdateCheckinFormRequest $request, CheckinForm $checkinForm): JsonResponse
    {
        $coach = $this->resolveCoach($request->user());
        $this->assertOwnsForm($coach, $checkinForm);

        $checkinForm->update([
            'title' => $request->validated('title'),
            'frequency' => $request->validated('frequency'),
            'send_time' => $request->validated('sendTime'),
            'questions' => $request->validated('questions'),
            'is_active' => $request->validated('isActive', $checkinForm->is_active),
        ]);

        return response()->json(['data' => new CheckinFormResource($checkinForm->load('clients:id'))]);
    }

    public function assign(AssignCheckinFormRequest $request, CheckinForm $checkinForm): JsonResponse
    {
        $coach = $this->resolveCoach($request->user());
        $this->assertOwnsForm($coach, $checkinForm);

        $clientIds = $request->validated('clientIds');
        $this->assertCoachOwnsClients($coach, $clientIds);

        $checkinForm->clients()->syncWithoutDetaching(
            collect($clientIds)->mapWithKeys(fn ($id) => [$id => ['assigned_at' => now()]])->all()
        );

        return response()->json(['data' => new CheckinFormResource($checkinForm->load('clients:id'))]);
    }
}
```

- [ ] **Step 7: Add routes**

Add a coach-scoped group in `modules/ClientProgress/routes/api.php` (import `CheckinFormController`), inside the existing `api/v1` group:
```php
Route::prefix('coaches/me')->middleware('role:coach')->group(function () {
    Route::get('checkin-forms', [CheckinFormController::class, 'index'])
        ->middleware('throttle:120,1')->name('clientprogress.api.checkin-forms.index');

    Route::post('checkin-forms', [CheckinFormController::class, 'store'])
        ->middleware('throttle:30,1')->name('clientprogress.api.checkin-forms.store');

    Route::put('checkin-forms/{checkinForm}', [CheckinFormController::class, 'update'])
        ->middleware('throttle:30,1')->name('clientprogress.api.checkin-forms.update');

    Route::post('checkin-forms/{checkinForm}/assign', [CheckinFormController::class, 'assign'])
        ->middleware('throttle:30,1')->name('clientprogress.api.checkin-forms.assign');
});
```

- [ ] **Step 8: Run test to verify PASS**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='CheckinFormApiTest'`
Expected: PASS.

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress/app modules/ClientProgress/routes/api.php modules/ClientProgress/tests/Feature/CheckinFormApiTest.php
git commit -m "feat(clientprogress): checkin-form crud + client assignment api"
```

---

### Task 8: Client respond endpoint + coach notification + push stub

**Files:**
- Create: `modules/ClientProgress/app/Services/Contracts/PushServiceContract.php`
- Create: `modules/ClientProgress/app/Services/StubPushService.php`
- Create: `modules/ClientProgress/app/Notifications/ClientSubmittedCheckinNotification.php`
- Create: `modules/ClientProgress/app/Http/Requests/RespondCheckinRequest.php`
- Create: `modules/ClientProgress/app/Http/Resources/CheckinResponseResource.php`
- Create: `modules/ClientProgress/app/Http/Controllers/Api/V1/CheckinResponseController.php`
- Modify: `modules/ClientProgress/app/Providers/ClientProgressServiceProvider.php` (bind contract)
- Modify: `modules/ClientProgress/routes/api.php`
- Test: `modules/ClientProgress/tests/Feature/CheckinRespondTest.php`

**Interfaces:**
- Consumes: `CheckinForm`, `CheckinResponse`, `Client`, `Coach`.
- Produces:
  - `PushServiceContract::send(object $notifiable, string $title, string $body): void`; `StubPushService` logs.
  - `ClientSubmittedCheckinNotification` (`via = ['database']`, `toArray`, constructor `CheckinResponse $response, string $clientName`) that also calls `PushServiceContract` in its constructor-invoked flow — actually push is dispatched by the controller (see below), notification handles the DB channel.
  - `CheckinResponseController@respond(RespondCheckinRequest, CheckinForm)` → 201 `CheckinResponseResource`. Route `clientprogress.api.checkin-forms.respond`.

- [ ] **Step 1: Write the failing test**

Create `modules/ClientProgress/tests/Feature/CheckinRespondTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\ClientProgress\Models\CheckinForm;
use Modules\ClientProgress\Notifications\ClientSubmittedCheckinNotification;
use Tests\TestCase;

class CheckinRespondTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_client_can_respond_and_coach_is_notified(): void
    {
        Notification::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $form = CheckinForm::factory()->for($coach)->create([
            'questions' => [
                ['id' => 'q1', 'text' => 'Peso?', 'type' => 'number', 'required' => true, 'order' => 1],
            ],
        ]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        $this->actingAs($client->user)->postJson("/api/v1/checkin-forms/{$form->id}/respond", [
            'answers' => [['questionId' => 'q1', 'value' => 80]],
        ])->assertCreated();

        $this->assertDatabaseHas('checkin_responses', ['checkin_form_id' => $form->id, 'client_id' => $client->id]);
        Notification::assertSentTo($coach->user, ClientSubmittedCheckinNotification::class);
    }

    public function test_unassigned_client_cannot_respond(): void
    {
        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $form = CheckinForm::factory()->for($coach)->create();

        $this->actingAs($client->user)->postJson("/api/v1/checkin-forms/{$form->id}/respond", [
            'answers' => [['questionId' => 'q1', 'value' => 80]],
        ])->assertForbidden();
    }

    public function test_missing_required_answer_is_rejected(): void
    {
        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        $form = CheckinForm::factory()->for($coach)->create([
            'questions' => [['id' => 'q1', 'text' => 'Peso?', 'type' => 'number', 'required' => true, 'order' => 1]],
        ]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        $this->actingAs($client->user)->postJson("/api/v1/checkin-forms/{$form->id}/respond", [
            'answers' => [],
        ])->assertJsonValidationErrorFor('answers');
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='CheckinRespondTest'`
Expected: FAIL.

- [ ] **Step 3: Create push contract + stub**

`modules/ClientProgress/app/Services/Contracts/PushServiceContract.php`:
```php
<?php

namespace Modules\ClientProgress\Services\Contracts;

interface PushServiceContract
{
    public function send(object $notifiable, string $title, string $body): void;
}
```

`modules/ClientProgress/app/Services/StubPushService.php`:
```php
<?php

namespace Modules\ClientProgress\Services;

use Illuminate\Support\Facades\Log;
use Modules\ClientProgress\Services\Contracts\PushServiceContract;

class StubPushService implements PushServiceContract
{
    public function send(object $notifiable, string $title, string $body): void
    {
        Log::info('[ClientProgress Stub] PushService::send', [
            'notifiable' => $notifiable::class,
            'id' => $notifiable->getKey ?? null,
            'title' => $title,
            'body' => $body,
        ]);
    }
}
```

- [ ] **Step 4: Create the notification**

`modules/ClientProgress/app/Notifications/ClientSubmittedCheckinNotification.php`:
```php
<?php

namespace Modules\ClientProgress\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\ClientProgress\Models\CheckinResponse;

class ClientSubmittedCheckinNotification extends Notification
{
    use Queueable;

    public function __construct(
        public CheckinResponse $response,
        public string $clientName,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'checkin_submitted',
            'title' => "{$this->clientName} ha compilato il check-in",
            'checkinFormId' => $this->response->checkin_form_id,
            'checkinResponseId' => $this->response->id,
            'clientId' => $this->response->client_id,
        ];
    }
}
```

- [ ] **Step 5: Create the respond FormRequest (with required-question validation)**

`modules/ClientProgress/app/Http/Requests/RespondCheckinRequest.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\ClientProgress\Models\CheckinForm;

class RespondCheckinRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array'],
            'answers.*.questionId' => ['required', 'string'],
            'answers.*.value' => ['present'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var CheckinForm $form */
            $form = $this->route('checkinForm');
            $answers = collect($this->input('answers', []))->keyBy('questionId');

            foreach ($form->questions as $question) {
                if (($question['required'] ?? false)) {
                    $value = $answers->get($question['id'])['value'] ?? null;
                    if ($value === null || $value === '') {
                        $validator->errors()->add('answers', "Risposta obbligatoria mancante: {$question['id']}.");
                    }
                }
            }
        });
    }
}
```

- [ ] **Step 6: Create the Resource**

`modules/ClientProgress/app/Http/Resources/CheckinResponseResource.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\ClientProgress\Models\CheckinResponse;

/** @mixin CheckinResponse */
class CheckinResponseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $questions = collect($this->whenLoaded('checkinForm', fn () => $this->checkinForm->questions, []));
        $questionsById = $questions->keyBy('id');

        return [
            'id' => $this->id,
            'checkinFormId' => $this->checkin_form_id,
            'clientId' => $this->client_id,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'answers' => collect($this->answers)->map(fn ($a) => [
                'questionId' => $a['questionId'],
                'questionText' => $questionsById->get($a['questionId'])['text'] ?? null,
                'type' => $questionsById->get($a['questionId'])['type'] ?? null,
                'value' => $a['value'],
            ])->values(),
        ];
    }
}
```

- [ ] **Step 7: Create the controller**

`modules/ClientProgress/app/Http/Controllers/Api/V1/CheckinResponseController.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\ClientProgress\Http\Requests\RespondCheckinRequest;
use Modules\ClientProgress\Http\Resources\CheckinResponseResource;
use Modules\ClientProgress\Models\CheckinForm;
use Modules\ClientProgress\Models\CheckinResponse;
use Modules\ClientProgress\Notifications\ClientSubmittedCheckinNotification;
use Modules\ClientProgress\Services\Contracts\PushServiceContract;
use Symfony\Component\HttpFoundation\Response;

class CheckinResponseController extends Controller
{
    public function respond(RespondCheckinRequest $request, CheckinForm $checkinForm, PushServiceContract $push): JsonResponse
    {
        $client = $request->user()->client;
        abort_unless($client !== null, Response::HTTP_FORBIDDEN, 'Non autorizzato.');

        $isAssigned = $checkinForm->clients()->where('clients.id', $client->id)->exists();
        abort_unless($isAssigned, Response::HTTP_FORBIDDEN, 'Non autorizzato.');

        $response = CheckinResponse::create([
            'checkin_form_id' => $checkinForm->id,
            'client_id' => $client->id,
            'answers' => $request->validated('answers'),
            'submitted_at' => now(),
        ]);

        $clientName = $client->user?->name ?? 'Un cliente';
        $coachUser = $checkinForm->coach->user;

        $coachUser->notify(new ClientSubmittedCheckinNotification($response, $clientName));
        $push->send($coachUser, 'Nuovo check-in', "{$clientName} ha compilato il check-in");

        return response()->json(['data' => new CheckinResponseResource($response->load('checkinForm'))], 201);
    }
}
```

- [ ] **Step 8: Bind the contract in the provider**

In `modules/ClientProgress/app/Providers/ClientProgressServiceProvider.php`, add to `register()` (after `parent::register()`):
```php
$this->app->bind(
    \Modules\ClientProgress\Services\Contracts\PushServiceContract::class,
    \Modules\ClientProgress\Services\StubPushService::class,
);
```

- [ ] **Step 9: Add the route**

Add inside the `api/v1` group (client role), in `modules/ClientProgress/routes/api.php` (import `CheckinResponseController`):
```php
Route::post('checkin-forms/{checkinForm}/respond', [CheckinResponseController::class, 'respond'])
    ->middleware(['role:client', 'throttle:30,1'])
    ->name('clientprogress.api.checkin-forms.respond');
```

- [ ] **Step 10: Run test to verify PASS**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='CheckinRespondTest'`
Expected: PASS.

- [ ] **Step 11: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress/app modules/ClientProgress/routes/api.php modules/ClientProgress/tests/Feature/CheckinRespondTest.php
git commit -m "feat(clientprogress): client checkin respond + coach db notification + push stub"
```

---

### Task 9: Coach check-in history endpoint

**Files:**
- Create: `modules/ClientProgress/app/Http/Requests/CheckinHistoryRequest.php`
- Create: `modules/ClientProgress/app/Http/Controllers/Api/V1/CoachCheckinController.php`
- Modify: `modules/ClientProgress/routes/api.php`
- Test: `modules/ClientProgress/tests/Feature/CheckinHistoryTest.php`

**Interfaces:**
- Consumes: `ResolvesCoach`, `AuthorizesClientAccess`, `CheckinResponse`, `CheckinResponseResource`.
- Produces: `CoachCheckinController@index(CheckinHistoryRequest, Client)` → list of responses (questions expanded) in date range. Route `clientprogress.api.coaches.clients.checkins.index`.

- [ ] **Step 1: Write the failing test**

Create `modules/ClientProgress/tests/Feature/CheckinHistoryTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ClientProgress\Models\CheckinForm;
use Modules\ClientProgress\Models\CheckinResponse;
use Tests\TestCase;

class CheckinHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_sees_client_checkin_history_with_questions_expanded(): void
    {
        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $form = CheckinForm::factory()->for($coach)->create([
            'questions' => [['id' => 'q1', 'text' => 'Peso?', 'type' => 'number', 'required' => true, 'order' => 1]],
        ]);
        CheckinResponse::factory()->for($form)->for($client)->create([
            'answers' => [['questionId' => 'q1', 'value' => 80]],
            'submitted_at' => '2026-06-05 19:00:00',
        ]);

        $this->actingAs($coach->user)
            ->getJson("/api/v1/coaches/me/clients/{$client->id}/checkins?from=2026-06-01&to=2026-06-30")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.answers.0.questionText', 'Peso?');
    }

    public function test_coach_cannot_see_unlinked_client_history(): void
    {
        $coach = Coach::factory()->create();
        $stranger = Client::factory()->create();

        $this->actingAs($coach->user)
            ->getJson("/api/v1/coaches/me/clients/{$stranger->id}/checkins")
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='CheckinHistoryTest'`
Expected: FAIL.

- [ ] **Step 3: Create the FormRequest**

`modules/ClientProgress/app/Http/Requests/CheckinHistoryRequest.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckinHistoryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
```

- [ ] **Step 4: Create the controller**

`modules/ClientProgress/app/Http/Controllers/Api/V1/CoachCheckinController.php`:
```php
<?php

namespace Modules\ClientProgress\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;
use Modules\ClientProgress\Http\Controllers\Api\V1\Concerns\AuthorizesClientAccess;
use Modules\ClientProgress\Http\Controllers\Api\V1\Concerns\ResolvesCoach;
use Modules\ClientProgress\Http\Requests\CheckinHistoryRequest;
use Modules\ClientProgress\Http\Resources\CheckinResponseResource;
use Modules\ClientProgress\Models\CheckinResponse;

class CoachCheckinController extends Controller
{
    use AuthorizesClientAccess, ResolvesCoach;

    public function index(CheckinHistoryRequest $request, Client $client): JsonResponse
    {
        $this->resolveCoach($request->user());
        $this->authorizeClientAccess($request->user(), $client);

        $responses = CheckinResponse::query()
            ->with('checkinForm')
            ->where('client_id', $client->id)
            ->when($request->validated('from'), fn ($q, $from) => $q->where('submitted_at', '>=', $from))
            ->when($request->validated('to'), fn ($q, $to) => $q->where('submitted_at', '<=', $to.' 23:59:59'))
            ->latest('submitted_at')
            ->get();

        return response()->json(['data' => CheckinResponseResource::collection($responses)]);
    }
}
```

- [ ] **Step 5: Add the route**

Inside the `coaches/me` `role:coach` group in `modules/ClientProgress/routes/api.php` (import `CoachCheckinController`):
```php
Route::get('clients/{client}/checkins', [CoachCheckinController::class, 'index'])
    ->middleware('throttle:120,1')
    ->name('clientprogress.api.coaches.clients.checkins.index');
```

- [ ] **Step 6: Run test to verify PASS**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='CheckinHistoryTest'`
Expected: PASS.

- [ ] **Step 7: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress/app modules/ClientProgress/routes/api.php modules/ClientProgress/tests/Feature/CheckinHistoryTest.php
git commit -m "feat(clientprogress): coach checkin history endpoint"
```

---

### Task 10: Minimal scheduled dispatch command + MODULE_TODO.md

**Files:**
- Create: `modules/ClientProgress/app/Console/DispatchCheckinsCommand.php`
- Create: `modules/ClientProgress/app/Notifications/CheckinDueNotification.php`
- Modify: `modules/ClientProgress/app/Providers/ClientProgressServiceProvider.php` (`$commands` + `configureSchedules`)
- Create: `modules/ClientProgress/MODULE_TODO.md`
- Test: `modules/ClientProgress/tests/Feature/DispatchCheckinsCommandTest.php`

**Interfaces:**
- Consumes: `CheckinForm`, `CheckinFrequency`, `PushServiceContract`, `Client`.
- Produces: command signature `clientprogress:dispatch-checkins`; `CheckinDueNotification` (`via = ['database']`) to the client's user. `isDue(CheckinForm, Client, Carbon $now): bool` logic inside the command.

- [ ] **Step 1: Write the failing test**

Create `modules/ClientProgress/tests/Feature/DispatchCheckinsCommandTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Modules\ClientProgress\Models\CheckinForm;
use Modules\ClientProgress\Notifications\CheckinDueNotification;
use Tests\TestCase;

class DispatchCheckinsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_form_due_at_matching_hour_notifies_client(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 19:00:00'));
        Notification::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $form = CheckinForm::factory()->for($coach)->create([
            'frequency' => 'daily',
            'send_time' => '19:00',
            'is_active' => true,
        ]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        $this->artisan('clientprogress:dispatch-checkins')->assertSuccessful();

        Notification::assertSentTo($client->user, CheckinDueNotification::class);
        Carbon::setTestNow();
    }

    public function test_form_not_due_at_other_hour_is_skipped(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-18 08:00:00'));
        Notification::fake();

        $coach = Coach::factory()->create();
        $client = Client::factory()->create();
        $form = CheckinForm::factory()->for($coach)->create(['frequency' => 'daily', 'send_time' => '19:00', 'is_active' => true]);
        $form->clients()->attach($client->id, ['assigned_at' => now()]);

        $this->artisan('clientprogress:dispatch-checkins')->assertSuccessful();

        Notification::assertNothingSent();
        Carbon::setTestNow();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='DispatchCheckinsCommandTest'`
Expected: FAIL.

- [ ] **Step 3: Create the due notification**

`modules/ClientProgress/app/Notifications/CheckinDueNotification.php`:
```php
<?php

namespace Modules\ClientProgress\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Modules\ClientProgress\Models\CheckinForm;

class CheckinDueNotification extends Notification
{
    use Queueable;

    public function __construct(public CheckinForm $form) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'checkin_due',
            'title' => "È ora del tuo check-in: {$this->form->title}",
            'checkinFormId' => $this->form->id,
        ];
    }
}
```

- [ ] **Step 4: Create the command**

`modules/ClientProgress/app/Console/DispatchCheckinsCommand.php`:
```php
<?php

namespace Modules\ClientProgress\Console;

use App\Modules\Users\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Modules\ClientProgress\Enums\CheckinFrequency;
use Modules\ClientProgress\Models\CheckinForm;
use Modules\ClientProgress\Notifications\CheckinDueNotification;
use Modules\ClientProgress\Services\Contracts\PushServiceContract;

class DispatchCheckinsCommand extends Command
{
    protected $signature = 'clientprogress:dispatch-checkins';

    protected $description = 'Dispatch due check-in reminders to assigned clients (minimal hourly scheduler).';

    public function handle(PushServiceContract $push): int
    {
        $now = Carbon::now();

        CheckinForm::query()
            ->active()
            ->with('clients.user')
            ->get()
            ->each(function (CheckinForm $form) use ($now, $push) {
                foreach ($form->clients as $client) {
                    if (! $this->isDue($form, $client, $now)) {
                        continue;
                    }

                    $client->user?->notify(new CheckinDueNotification($form));
                    if ($client->user) {
                        $push->send($client->user, 'Check-in', "È ora del tuo check-in: {$form->title}");
                    }
                }
            });

        return self::SUCCESS;
    }

    private function isDue(CheckinForm $form, Client $client, Carbon $now): bool
    {
        // Hour gate: send_time hour must match current hour.
        $sendHour = (int) Carbon::parse($form->send_time)->format('H');
        if ($now->hour !== $sendHour) {
            return false;
        }

        $assignedAt = Carbon::parse($client->pivot->assigned_at);

        return match ($form->frequency) {
            CheckinFrequency::Daily => true,
            CheckinFrequency::Weekly => $now->dayOfWeek === $assignedAt->dayOfWeek,
            CheckinFrequency::Biweekly => $now->dayOfWeek === $assignedAt->dayOfWeek
                && $assignedAt->diffInWeeks($now) % 2 === 0,
            CheckinFrequency::Monthly => $now->day === $assignedAt->day,
        };
    }
}
```

- [ ] **Step 5: Register command + schedule in provider**

In `modules/ClientProgress/app/Providers/ClientProgressServiceProvider.php`, add the `$commands` property and a `configureSchedules` method:
```php
use Illuminate\Console\Scheduling\Schedule;
use Modules\ClientProgress\Console\DispatchCheckinsCommand;

// inside the class:
protected array $commands = [
    DispatchCheckinsCommand::class,
];

protected function configureSchedules(Schedule $schedule): void
{
    $schedule->command('clientprogress:dispatch-checkins')->hourly();
}
```
(The base `ModuleServiceProvider::boot()` already calls `registerCommands()` and `registerCommandSchedules()` which invokes `configureSchedules` if present.)

- [ ] **Step 6: Create `MODULE_TODO.md`**

`modules/ClientProgress/MODULE_TODO.md`:
```markdown
# ClientProgress — Deferred Work (modules_todo)

Minimal scheduling (`clientprogress:dispatch-checkins`, hourly) ships now. Deferred:

- **Real push transport**: `StubPushService` only logs. Add a device-token table, an FCM/Expo driver, and a real notification channel; rebind `PushServiceContract`.
- **Dispatch idempotency/dedupe**: hourly cron + hour-match limits sends to once/hour, but retries or multiple workers can double-send. Add a `checkin_dispatches` marker table keyed by (form_id, client_id, period) and guard against re-send.
- **Timezone handling**: `send_time` is evaluated in the app timezone. Honor per-client/per-coach timezones.
- **Missed-window catch-up**: if cron skips an hour, that window's reminders are lost. Add catch-up logic.
- **Storage disk**: photos use `config('media-library.disk_name')` (currently `public` in dev). Configure `s3`/`r2` credentials in production so `temporaryUrl()` yields real signed 1h URLs (the `ProgressPhotoResource` already switches on the disk driver). The `r2_docs` disk referenced elsewhere is still undefined in `config/filesystems.php`.
- **Biweekly correctness**: current `isDue` biweekly uses `diffInWeeks % 2`; validate against real assignment dates and DST boundaries.
```

- [ ] **Step 7: Run test to verify PASS**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='DispatchCheckinsCommandTest'`
Expected: PASS.

- [ ] **Step 8: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress/app modules/ClientProgress/MODULE_TODO.md modules/ClientProgress/tests/Feature/DispatchCheckinsCommandTest.php
git commit -m "feat(clientprogress): minimal hourly checkin dispatch command + module todo"
```

---

### Task 11: Filament resources

**Files:**
- Create: `modules/ClientProgress/app/Filament/Resources/CheckinForms/CheckinFormResource.php` (+ `Schemas/CheckinFormForm.php`, `Tables/CheckinFormsTable.php`, `Pages/{ListCheckinForms,CreateCheckinForm,EditCheckinForm}.php`)
- Create: `modules/ClientProgress/app/Filament/Resources/CheckinResponses/CheckinResponseResource.php` (+ `Tables/CheckinResponsesTable.php`, `Schemas/CheckinResponseInfolist.php`, `Pages/{ListCheckinResponses,ViewCheckinResponse}.php`)
- Create: `modules/ClientProgress/app/Filament/Resources/ProgressPhotos/ProgressPhotoResource.php` (+ `Schemas/ProgressPhotoForm.php`, `Tables/ProgressPhotosTable.php`, `Pages/{ListProgressPhotos,CreateProgressPhoto}.php`)
- Create: `modules/ClientProgress/app/Filament/Resources/BodyMetrics/BodyMetricResource.php` (+ `Tables/BodyMetricsTable.php`, `Pages/ListBodyMetrics.php`)
- Test: `modules/ClientProgress/tests/Feature/Filament/CheckinFormResourceTest.php`

**Interfaces:**
- Consumes: models + enums. Uses the WorkoutPlans Filament split convention and the `filament/spatie-laravel-media-library-plugin` for photos.
- Produces: four Filament resources registered under the "Client Progress" nav group (auto-discovered via `ClientProgressPlugin` + `ModulePlugin` trait).

> Note: This is the largest task; keep each resource minimal. Reference `modules/WorkoutPlans/app/Filament/Resources/WorkoutPlans/*` for exact v5 signatures (`form(Schema $schema)`, `table(Table $table)`, `getPages()`), and `modules/Auth/app/Filament/Resources/Users/*` for an infolist example. Copy the structural boilerplate from those, substituting this module's models/fields.

- [ ] **Step 1: Write the failing test (smoke render + create)**

Create `modules/ClientProgress/tests/Feature/Filament/CheckinFormResourceTest.php`:
```php
<?php

namespace Modules\ClientProgress\Tests\Feature\Filament;

use App\Models\User;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\ClientProgress\Filament\Resources\CheckinForms\Pages\ListCheckinForms;
use Modules\ClientProgress\Models\CheckinForm;
use Tests\TestCase;

class CheckinFormResourceTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        // Mirror how WorkoutPlans Filament tests authenticate an admin panel user.
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_list_page_renders_forms(): void
    {
        $this->actingAs($this->adminUser());
        $coach = Coach::factory()->create();
        $forms = CheckinForm::factory()->for($coach)->count(2)->create();

        Livewire::test(ListCheckinForms::class)
            ->assertOk()
            ->assertCanSeeTableRecords($forms);
    }
}
```
(Before writing this, open a WorkoutPlans Filament test — if one exists under `modules/WorkoutPlans/tests` — and copy its exact panel-auth setup (`Filament::setCurrentPanel`, admin role name, `->actingAs`). If no Filament test exists in the repo, verify admin login by checking `AdminPanelProvider` auth + `chef@miocoach.dev` seed role. Adjust `adminUser()` accordingly.)

- [ ] **Step 2: Run to verify it fails**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='CheckinFormResourceTest'`
Expected: FAIL (class not found).

- [ ] **Step 3: Generate resources via Filament artisan, then edit**

Use Filament generators to get correct v5 skeletons, then edit fields. Run (adjust flags to match `list-artisan-commands`/`--help`):
```bash
php artisan make:filament-resource CheckinForm --model="Modules\ClientProgress\Models\CheckinForm" --generate --no-interaction
```
Then **move** the generated files into `modules/ClientProgress/app/Filament/Resources/CheckinForms/...`, fix namespaces to `Modules\ClientProgress\Filament\Resources\CheckinForms`, and set `protected static ?string $navigationGroup = 'Client Progress';` (property type per Filament rules: `string | UnitEnum | null`). Repeat for `ProgressPhoto`, `CheckinResponse` (view-only: remove create/edit pages), and `BodyMetric` (list-only).

- [ ] **Step 4: Implement `CheckinFormForm` schema (question repeater + assign)**

`modules/ClientProgress/app/Filament/Resources/CheckinForms/Schemas/CheckinFormForm.php`:
```php
<?php

namespace Modules\ClientProgress\Filament\Resources\CheckinForms\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\ClientProgress\Enums\CheckinFrequency;
use Modules\ClientProgress\Enums\QuestionType;

class CheckinFormForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                TextInput::make('title')->required()->maxLength(255),
                Select::make('frequency')->options(CheckinFrequency::class)->required(),
                TimePicker::make('send_time')->seconds(false)->required(),
                Toggle::make('is_active')->default(true),
            ]),
            Repeater::make('questions')->columnSpanFull()->schema([
                TextInput::make('id')->required()->maxLength(64),
                TextInput::make('text')->required()->maxLength(500),
                Select::make('type')->options(QuestionType::class)->required(),
                Toggle::make('required')->default(false),
                TextInput::make('order')->numeric()->default(0),
            ])->reorderable('order')->minItems(1),
        ]);
    }
}
```

- [ ] **Step 5: Implement `CheckinFormsTable`**

`modules/ClientProgress/app/Filament/Resources/CheckinForms/Tables/CheckinFormsTable.php`:
```php
<?php

namespace Modules\ClientProgress\Filament\Resources\CheckinForms\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CheckinFormsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('coach.user.name')->label('Coach')->sortable(),
                TextColumn::make('frequency')->badge(),
                TextColumn::make('send_time'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('clients_count')->counts('clients')->label('Assigned'),
            ])
            ->recordActions([EditAction::make()]);
    }
}
```

- [ ] **Step 6: Wire the resource class**

`modules/ClientProgress/app/Filament/Resources/CheckinForms/CheckinFormResource.php`:
```php
<?php

namespace Modules\ClientProgress\Filament\Resources\CheckinForms;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\ClientProgress\Filament\Resources\CheckinForms\Pages\CreateCheckinForm;
use Modules\ClientProgress\Filament\Resources\CheckinForms\Pages\EditCheckinForm;
use Modules\ClientProgress\Filament\Resources\CheckinForms\Pages\ListCheckinForms;
use Modules\ClientProgress\Filament\Resources\CheckinForms\Schemas\CheckinFormForm;
use Modules\ClientProgress\Filament\Resources\CheckinForms\Tables\CheckinFormsTable;
use Modules\ClientProgress\Models\CheckinForm;
use UnitEnum;

class CheckinFormResource extends Resource
{
    protected static ?string $model = CheckinForm::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Client Progress';

    public static function form(Schema $schema): Schema
    {
        return CheckinFormForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CheckinFormsTable::configure($table);
    }

    /** @return array<string, mixed> */
    public static function getPages(): array
    {
        return [
            'index' => ListCheckinForms::route('/'),
            'create' => CreateCheckinForm::route('/create'),
            'edit' => EditCheckinForm::route('/{record}/edit'),
        ];
    }
}
```
(Pages classes are the generated thin `extends ListRecords/CreateRecord/EditRecord` stubs pointing at `CheckinFormResource`. Keep the generated versions, only fixing namespaces.)

- [ ] **Step 7: Implement the other three resources minimally**

- `BodyMetricResource` (list-only): table columns `client.user.name`, `weight_kg`, `body_fat_pct`, `waist_cm`, `measured_at`; filters `SelectFilter` on client; no create/edit pages (`getPages` returns only `index`).
- `ProgressPhotoResource`: form uses `Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('photo')->collection('photo')->image()->maxSize(10240)`, plus `client_id` Select, `logged_by` Select, `taken_at` DateTimePicker; table with `SpatieMediaLibraryImageColumn::make('photo')->conversion('thumb')`.
- `CheckinResponseResource` (view-only): table `checkinForm.title`, `client.user.name`, `submitted_at`; `ViewCheckinResponse` page with an infolist that renders `answers` (use a `RepeatableEntry` or `KeyValueEntry`). Remove create/edit pages.

Write each following the same Resource/Schemas/Tables/Pages split shown above. Use exact namespaces `Filament\Forms\Components\*`, `Filament\Tables\Columns\*`, `Filament\Schemas\Components\*`, actions from `Filament\Actions\*`.

- [ ] **Step 8: Run test to verify PASS**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='CheckinFormResourceTest'`
Expected: PASS. Iterate on namespace/property-type errors until green.

- [ ] **Step 9: Pint + commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress/app/Filament modules/ClientProgress/tests/Feature/Filament
git commit -m "feat(clientprogress): filament resources for forms, responses, photos, metrics"
```

---

### Task 12: TypeScript types, full module test run, final build

**Files:**
- Create (generated): `modules/ClientProgress/resources/js/types/generated.d.ts`
- Test: (run the whole module suite)

- [ ] **Step 1: Generate TypeScript types from enums**

Run:
```bash
php artisan module:generate-types ClientProgress
```
Expected: `modules/ClientProgress/resources/js/types/generated.d.ts` contains `MetricType`, `LoggedBy`, `CheckinFrequency`, `QuestionType`.

- [ ] **Step 2: Run the full module test suite**

Run: `php -d memory_limit=2048M artisan test --testsuite=Modules --filter='^Modules\\ClientProgress\\Tests'`
Expected: all ClientProgress tests PASS.

- [ ] **Step 3: Run PHPStan on the module**

Run: `composer analyse -- modules/ClientProgress`
(If `composer analyse` doesn't accept a path arg, run `vendor/bin/phpstan analyse modules/ClientProgress --level=5`.)
Expected: no errors. Fix any type issues (add `@property` PHPDoc, generics on relations).

- [ ] **Step 4: Route list sanity check**

Run: `php artisan route:list --path=api/v1/clients --path=api/v1/coaches --path=api/v1/checkin`
Expected: all `clientprogress.api.*` routes present with correct middleware.

- [ ] **Step 5: Build frontend (verify no manifest/type breakage)**

Run: `npm run build`
Expected: build succeeds (module assets discovered by `module-loader.js`).

- [ ] **Step 6: Final commit**

```bash
vendor/bin/pint --dirty --format agent
git add modules/ClientProgress/resources
git commit -m "chore(clientprogress): generate typescript types + final verification"
```

---

## Self-Review

**Spec coverage:**
- `POST /clients/:id/metrics` → Task 5. ✓
- `POST /clients/:id/metrics/photos` (multipart, 10 MB, jpg/png, thumbnail, storage) → Task 6 (MediaLibrary thumb 400×400; S3/signed via disk-driver switch; storage path is MediaLibrary-managed — the literal `progress-photos/{clientId}/{date}/{uuid}.jpg` key is replaced by MediaLibrary's path scheme, noted in spec). ✓
- `GET /clients/:id/metrics?metric=&from=&to=` → Task 5 `[{date,value}]`. ✓
- `GET /clients/:id/metrics/photos?from=&to=` (signed 1h, thumb+full) → Task 6. ✓
- `POST /coaches/me/checkin-forms` (+immediate assign) → Task 7. ✓
- `GET /coaches/me/checkin-forms` → Task 7. ✓
- `PUT /coaches/me/checkin-forms/:id` → Task 7. ✓
- `POST /coaches/me/checkin-forms/:id/assign` → Task 7. ✓
- `POST /checkin-forms/:id/respond` (client) + coach push → Task 8. ✓
- `GET /coaches/me/clients/:id/checkins?from=&to=` (questions expanded) → Task 9. ✓
- Enums, models, Filament, cross-module relations, scheduling, TS types → Tasks 2,4,11,10,12. ✓

**Placeholder scan:** Task 11 intentionally references copying WorkoutPlans/Auth Filament skeletons for exact v5 signatures (generator-driven) rather than reproducing every generated stub verbatim; all custom schemas/tables/resource wiring have full code. Steps 3/7 of Task 11 are the only "follow the existing convention" directions — acceptable because the concrete field lists and component names are given. No TBD/TODO in executable code.

**Type consistency:** `MetricType::column()`, `LoggedBy`, `CheckinFrequency`, `QuestionType` used consistently across requests/controllers/resources. `authorizeClientAccess()`, `resolveCoach()`, `assertOwnsForm()`, `assertCoachOwnsClients()` names match between trait definition (Tasks 5, 7) and consumers (Tasks 6, 8, 9). `clients()` relation (pivot `checkin_form_client`) used the same in model, controller, command, resource.

**Known verification points flagged for the implementer** (each has a fallback in-plan): (a) test auth helper for `jwt.auth` (`actingAs` vs token) — Task 5 Step 1; (b) `spatie/image` `Fit` enum vs `width/height` — Task 4 Step 4; (c) media conversion queued-vs-sync in tests — Task 6 Step 7; (d) Filament admin-panel test auth setup — Task 11 Step 1.

---

## Execution Handoff

Plan complete. Execution via **subagent-driven-development** (per user request "poi usa subagent") — fresh subagent per task with review between tasks.
