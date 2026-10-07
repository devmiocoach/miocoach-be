# Bookings Module Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the complete Bookings module — coach API, public booking API, availability slots, BookingService with stub external integrations, Filament admin panel, and Users module integration for `next_booking`/`sessions` fields.

**Architecture:** `BookingService` central service with 4 injected stub contracts (`CalendarServiceContract`, `SmsServiceContract`, `BookingEmailServiceContract`, `PushServiceContract`). Controllers are thin — all orchestration in service. `BookingsServiceProvider` wires bindings and registers dynamic `Client` relations via `resolveRelationUsing()` — zero direct dependency from Users module.

**Tech Stack:** Laravel 13, PHP 8.4, nwidart/laravel-modules, Filament 5, Laravel Queue (delayed jobs), `jwt.auth` middleware

---

### Task 1: Scaffold + configure module

**Files:**
- All scaffolded via `miocoach:recipe`
- Modify: `modules/Bookings/module.json`
- Modify: `modules/Bookings/app/Providers/BookingsServiceProvider.php`
- Modify: `modules/Bookings/app/Providers/RouteServiceProvider.php`
- Modify: `modules/Bookings/routes/navigation.php` (empty — admin-only)

- [ ] **Step 1: Run recipe**

```bash
php artisan miocoach:recipe Bookings
```

When prompted, select **Basic Recipe** and enter `Bookings` as the module name.

Expected: `modules/Bookings/` directory created with scaffold files.

- [ ] **Step 2: Verify scaffold**

```bash
ls modules/Bookings/
ls modules/Bookings/app/
```

- [ ] **Step 3: Replace module.json**

Full contents of `modules/Bookings/module.json`:

```json
{
    "name": "Bookings",
    "alias": "bookings",
    "description": "Bookings module — coach and public session booking",
    "author": "Miocoach",
    "version": "v1.0",
    "keywords": ["bookings"],
    "priority": 0,
    "providers": ["Modules\\Bookings\\Providers\\BookingsServiceProvider"],
    "files": []
}
```

- [ ] **Step 4: Replace BookingsServiceProvider (minimal — will be extended in Task 10)**

Full contents of `modules/Bookings/app/Providers/BookingsServiceProvider.php`:

```php
<?php

namespace Modules\Bookings\Providers;

use App\Providers\ModuleServiceProvider;

class BookingsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Bookings';

    protected string $nameLower = 'bookings';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
```

- [ ] **Step 5: Replace RouteServiceProvider**

Full contents of `modules/Bookings/app/Providers/RouteServiceProvider.php`:

```php
<?php

namespace Modules\Bookings\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function map(): void
    {
        Route::middleware('web')
            ->group(module_path('Bookings', '/routes/web.php'));
        Route::middleware('api')
            ->group(module_path('Bookings', '/routes/api.php'));
    }
}
```

- [ ] **Step 6: Empty navigation.php**

Full contents of `modules/Bookings/routes/navigation.php`:

```php
<?php
```

- [ ] **Step 7: Enable module**

```bash
composer dump-autoload
php artisan module:enable Bookings
```

Expected: `"Bookings": true` added to `modules_statuses.json`.

- [ ] **Step 8: Commit scaffold**

```bash
git add modules/Bookings/
git commit -m "chore(bookings): scaffold module via miocoach:recipe"
```

---

### Task 2: Enums

**Files:**
- Create: `modules/Bookings/app/Enums/BookingStatus.php`
- Create: `modules/Bookings/app/Enums/BookingServiceType.php`
- Create: `modules/Bookings/app/Enums/BookingSource.php`
- Create: `modules/Bookings/app/Enums/BookingCancelledBy.php`

- [ ] **Step 1: Create BookingStatus**

Full contents of `modules/Bookings/app/Enums/BookingStatus.php`:

```php
<?php

namespace Modules\Bookings\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum BookingStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending   => 'Pending',
            self::Confirmed => 'Confirmed',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::NoShow    => 'No Show',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending   => 'warning',
            self::Confirmed => 'success',
            self::Completed => 'info',
            self::Cancelled => 'danger',
            self::NoShow    => 'gray',
        };
    }
}
```

- [ ] **Step 2: Create BookingServiceType**

Full contents of `modules/Bookings/app/Enums/BookingServiceType.php`:

```php
<?php

namespace Modules\Bookings\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum BookingServiceType: string implements HasLabel
{
    case PersonalTraining      = 'personal_training';
    case OnlineCoaching        = 'online_coaching';
    case Assessment            = 'assessment';
    case GroupSession          = 'group_session';
    case NutritionConsultation = 'nutrition_consultation';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::PersonalTraining      => 'Personal Training',
            self::OnlineCoaching        => 'Online Coaching',
            self::Assessment            => 'Assessment',
            self::GroupSession          => 'Group Session',
            self::NutritionConsultation => 'Nutrition Consultation',
        };
    }
}
```

- [ ] **Step 3: Create BookingSource**

Full contents of `modules/Bookings/app/Enums/BookingSource.php`:

```php
<?php

namespace Modules\Bookings\Enums;

use Filament\Support\Contracts\HasLabel;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
enum BookingSource: string implements HasLabel
{
    case Manual    = 'manual';
    case PublicWeb = 'public_web';
    case AiBooking = 'ai_booking';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Manual    => 'Manual (Coach)',
            self::PublicWeb => 'Public Web',
            self::AiBooking => 'AI Booking',
        };
    }
}
```

- [ ] **Step 4: Create BookingCancelledBy**

Full contents of `modules/Bookings/app/Enums/BookingCancelledBy.php`:

```php
<?php

namespace Modules\Bookings\Enums;

enum BookingCancelledBy: string
{
    case Coach  = 'coach';
    case Client = 'client';
}
```

- [ ] **Step 5: Generate TypeScript types**

```bash
php artisan module:generate-types Bookings
```

Expected: `modules/Bookings/resources/js/types/generated.d.ts` updated.

- [ ] **Step 6: Commit**

```bash
git add modules/Bookings/app/Enums/ modules/Bookings/resources/js/types/
git commit -m "feat(bookings): add BookingStatus, BookingServiceType, BookingSource, BookingCancelledBy enums"
```

---

### Task 3: Migration + Model + Factory + Exception

**Files:**
- Create: `modules/Bookings/database/migrations/2026_06_05_000001_create_bookings_table.php`
- Create: `modules/Bookings/app/Models/Booking.php`
- Create: `modules/Bookings/database/factories/BookingFactory.php`
- Create: `modules/Bookings/app/Exceptions/BookingOverlapException.php`

- [ ] **Step 1: Create migration**

Full contents of `modules/Bookings/database/migrations/2026_06_05_000001_create_bookings_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coach_id')->constrained('coaches')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('guest_name', 100)->nullable();
            $table->string('guest_email', 255)->nullable();
            $table->string('guest_phone', 30)->nullable();
            $table->string('service_type', 50);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->string('status', 20)->default('confirmed');
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 10)->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('source', 20)->default('manual');
            $table->string('cancellation_token', 100)->unique()->nullable();
            $table->timestamp('cancellation_token_expires_at')->nullable();
            $table->string('google_calendar_event_id', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['coach_id', 'starts_at']);
            $table->index(['client_id', 'starts_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan module:migrate Bookings
```

Expected: `bookings` table created without errors.

- [ ] **Step 3: Create Booking model**

Full contents of `modules/Bookings/app/Models/Booking.php`:

```php
<?php

namespace Modules\Bookings\Models;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Bookings\Database\Factories\BookingFactory;
use Modules\Bookings\Enums\BookingCancelledBy;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingSource;
use Modules\Bookings\Enums\BookingStatus;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'coach_id',
        'client_id',
        'package_id',
        'guest_name',
        'guest_email',
        'guest_phone',
        'service_type',
        'starts_at',
        'ends_at',
        'duration_minutes',
        'status',
        'notes',
        'cancellation_reason',
        'cancelled_at',
        'cancelled_by',
        'price',
        'source',
        'cancellation_token',
        'cancellation_token_expires_at',
        'google_calendar_event_id',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'                     => 'datetime',
            'ends_at'                       => 'datetime',
            'cancelled_at'                  => 'datetime',
            'cancellation_token_expires_at' => 'datetime',
            'status'                        => BookingStatus::class,
            'service_type'                  => BookingServiceType::class,
            'source'                        => BookingSource::class,
            'cancelled_by'                  => BookingCancelledBy::class,
            'duration_minutes'              => 'integer',
            'price'                         => 'float',
        ];
    }

    protected static function newFactory(): BookingFactory
    {
        return BookingFactory::new();
    }

    public function coach(): BelongsTo
    {
        return $this->belongsTo(Coach::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function isForGuest(): bool
    {
        return $this->client_id === null && $this->guest_email !== null;
    }

    public function scopeConfirmed($query): void
    {
        $query->where('status', BookingStatus::Confirmed->value);
    }

    public function scopeForCoach($query, int $coachId): void
    {
        $query->where('coach_id', $coachId);
    }

    public function scopeUpcoming($query): void
    {
        $query->where('starts_at', '>', now());
    }
}
```

- [ ] **Step 4: Create BookingFactory**

Full contents of `modules/Bookings/database/factories/BookingFactory.php`:

```php
<?php

namespace Modules\Bookings\Database\Factories;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingSource;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;

class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $duration = $this->faker->randomElement([30, 45, 60, 90]);
        $endsAt   = (clone $startsAt)->modify("+{$duration} minutes");

        return [
            'coach_id'         => Coach::factory(),
            'client_id'        => Client::factory(),
            'service_type'     => $this->faker->randomElement(BookingServiceType::cases())->value,
            'starts_at'        => $startsAt,
            'ends_at'          => $endsAt,
            'duration_minutes' => $duration,
            'status'           => BookingStatus::Confirmed->value,
            'source'           => BookingSource::Manual->value,
            'notes'            => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => BookingStatus::Confirmed->value]);
    }

    public function completed(): static
    {
        return $this->state(['status' => BookingStatus::Completed->value]);
    }

    public function cancelled(): static
    {
        return $this->state([
            'status'              => BookingStatus::Cancelled->value,
            'cancelled_at'        => now(),
            'cancelled_by'        => 'coach',
            'cancellation_reason' => $this->faker->sentence(),
        ]);
    }

    public function forGuest(): static
    {
        return $this->state([
            'client_id'   => null,
            'guest_name'  => $this->faker->name(),
            'guest_email' => $this->faker->email(),
            'guest_phone' => $this->faker->phoneNumber(),
            'source'      => BookingSource::PublicWeb->value,
        ]);
    }
}
```

- [ ] **Step 5: Create BookingOverlapException**

Full contents of `modules/Bookings/app/Exceptions/BookingOverlapException.php`:

```php
<?php

namespace Modules\Bookings\Exceptions;

use Exception;

class BookingOverlapException extends Exception
{
    public function __construct()
    {
        parent::__construct('Il coach ha già una prenotazione in questo orario.', 422);
    }
}
```

- [ ] **Step 6: Run pint**

```bash
vendor/bin/pint modules/Bookings/app/Models/ modules/Bookings/database/ modules/Bookings/app/Exceptions/ --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add modules/Bookings/database/ modules/Bookings/app/Models/ modules/Bookings/app/Exceptions/
git commit -m "feat(bookings): add migration, Booking model, factory and BookingOverlapException"
```

---

### Task 4: Service Contracts + Stub Services

**Files:**
- Create: `modules/Bookings/app/Services/Contracts/CalendarServiceContract.php`
- Create: `modules/Bookings/app/Services/Contracts/SmsServiceContract.php`
- Create: `modules/Bookings/app/Services/Contracts/BookingEmailServiceContract.php`
- Create: `modules/Bookings/app/Services/Contracts/PushServiceContract.php`
- Create: `modules/Bookings/app/Services/StubCalendarService.php`
- Create: `modules/Bookings/app/Services/StubSmsService.php`
- Create: `modules/Bookings/app/Services/StubBookingEmailService.php`
- Create: `modules/Bookings/app/Services/StubPushService.php`

- [ ] **Step 1: Create CalendarServiceContract**

Full contents of `modules/Bookings/app/Services/Contracts/CalendarServiceContract.php`:

```php
<?php

namespace Modules\Bookings\Services\Contracts;

use Modules\Bookings\Models\Booking;

interface CalendarServiceContract
{
    public function createEvent(Booking $booking): ?string;

    public function updateEvent(Booking $booking): void;

    public function deleteEvent(Booking $booking): void;
}
```

- [ ] **Step 2: Create SmsServiceContract**

Full contents of `modules/Bookings/app/Services/Contracts/SmsServiceContract.php`:

```php
<?php

namespace Modules\Bookings\Services\Contracts;

use Modules\Bookings\Models\Booking;

interface SmsServiceContract
{
    public function sendConfirmation(Booking $booking): void;

    public function sendCancellation(Booking $booking): void;

    public function sendReminder(Booking $booking): void;
}
```

- [ ] **Step 3: Create BookingEmailServiceContract**

Full contents of `modules/Bookings/app/Services/Contracts/BookingEmailServiceContract.php`:

```php
<?php

namespace Modules\Bookings\Services\Contracts;

use Modules\Bookings\Models\Booking;

interface BookingEmailServiceContract
{
    public function sendConfirmation(Booking $booking): void;

    public function sendCancellation(Booking $booking, string $reason): void;

    public function sendReminder(Booking $booking): void;
}
```

- [ ] **Step 4: Create PushServiceContract**

Full contents of `modules/Bookings/app/Services/Contracts/PushServiceContract.php`:

```php
<?php

namespace Modules\Bookings\Services\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PushServiceContract
{
    public function send(Model $user, string $title, string $body, array $data = []): void;
}
```

- [ ] **Step 5: Create stub services (4 files)**

Full contents of `modules/Bookings/app/Services/StubCalendarService.php`:

```php
<?php

namespace Modules\Bookings\Services;

use Illuminate\Support\Facades\Log;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\CalendarServiceContract;

class StubCalendarService implements CalendarServiceContract
{
    public function createEvent(Booking $booking): ?string
    {
        Log::info('[Bookings Stub] CalendarService::createEvent', ['booking_id' => $booking->id]);

        return null;
    }

    public function updateEvent(Booking $booking): void
    {
        Log::info('[Bookings Stub] CalendarService::updateEvent', ['booking_id' => $booking->id]);
    }

    public function deleteEvent(Booking $booking): void
    {
        Log::info('[Bookings Stub] CalendarService::deleteEvent', ['booking_id' => $booking->id]);
    }
}
```

Full contents of `modules/Bookings/app/Services/StubSmsService.php`:

```php
<?php

namespace Modules\Bookings\Services;

use Illuminate\Support\Facades\Log;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\SmsServiceContract;

class StubSmsService implements SmsServiceContract
{
    public function sendConfirmation(Booking $booking): void
    {
        Log::info('[Bookings Stub] SmsService::sendConfirmation', ['booking_id' => $booking->id]);
    }

    public function sendCancellation(Booking $booking): void
    {
        Log::info('[Bookings Stub] SmsService::sendCancellation', ['booking_id' => $booking->id]);
    }

    public function sendReminder(Booking $booking): void
    {
        Log::info('[Bookings Stub] SmsService::sendReminder', ['booking_id' => $booking->id]);
    }
}
```

Full contents of `modules/Bookings/app/Services/StubBookingEmailService.php`:

```php
<?php

namespace Modules\Bookings\Services;

use Illuminate\Support\Facades\Log;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\BookingEmailServiceContract;

class StubBookingEmailService implements BookingEmailServiceContract
{
    public function sendConfirmation(Booking $booking): void
    {
        Log::info('[Bookings Stub] EmailService::sendConfirmation', ['booking_id' => $booking->id]);
    }

    public function sendCancellation(Booking $booking, string $reason): void
    {
        Log::info('[Bookings Stub] EmailService::sendCancellation', ['booking_id' => $booking->id, 'reason' => $reason]);
    }

    public function sendReminder(Booking $booking): void
    {
        Log::info('[Bookings Stub] EmailService::sendReminder', ['booking_id' => $booking->id]);
    }
}
```

Full contents of `modules/Bookings/app/Services/StubPushService.php`:

```php
<?php

namespace Modules\Bookings\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Modules\Bookings\Services\Contracts\PushServiceContract;

class StubPushService implements PushServiceContract
{
    public function send(Model $user, string $title, string $body, array $data = []): void
    {
        Log::info('[Bookings Stub] PushService::send', [
            'user_id' => $user->getKey(),
            'title'   => $title,
            'body'    => $body,
        ]);
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add modules/Bookings/app/Services/
git commit -m "feat(bookings): add service contracts and stub implementations"
```

---

### Task 5: BookingService

**Files:**
- Create: `modules/Bookings/tests/Feature/BookingServiceTest.php`
- Create: `modules/Bookings/app/Services/BookingService.php`
- Create: `modules/Bookings/app/Jobs/SendBookingReminderJob.php` (stub — just the class shell needed by BookingService)

- [ ] **Step 1: Create SendBookingReminderJob (shell — needed by BookingService)**

Full contents of `modules/Bookings/app/Jobs/SendBookingReminderJob.php`:

```php
<?php

namespace Modules\Bookings\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\BookingEmailServiceContract;
use Modules\Bookings\Services\Contracts\PushServiceContract;

class SendBookingReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private readonly int $bookingId,
        private readonly string $type,
    ) {}

    public function handle(
        BookingEmailServiceContract $email,
        PushServiceContract $push,
    ): void {
        $booking = Booking::find($this->bookingId);

        if (! $booking || $booking->status !== BookingStatus::Confirmed) {
            Log::info('SendBookingReminderJob skipped', [
                'booking_id' => $this->bookingId,
                'reason'     => 'not confirmed or not found',
            ]);

            return;
        }

        if ($booking->client?->user) {
            $push->send(
                $booking->client->user,
                'Promemoria sessione',
                'Hai una sessione tra '.($this->type === '24h' ? '24 ore' : '1 ora').' — '.$booking->starts_at->format('d/m/Y H:i').'.',
            );
        }

        $email->sendReminder($booking);

        if ($this->type === '1h' && $booking->coach->user) {
            $push->send(
                $booking->coach->user,
                'Promemoria sessione',
                "Hai una sessione tra 1 ora — {$booking->starts_at->format('d/m/Y H:i')}.",
            );
        }

        Log::info("SendBookingReminderJob [{$this->type}] sent", ['booking_id' => $this->bookingId]);
    }
}
```

- [ ] **Step 2: Write failing tests**

Full contents of `modules/Bookings/tests/Feature/BookingServiceTest.php`:

```php
<?php

namespace Modules\Bookings\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Exceptions\BookingOverlapException;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingService;
use Modules\Bookings\Services\StubBookingEmailService;
use Modules\Bookings\Services\StubCalendarService;
use Modules\Bookings\Services\StubPushService;
use Modules\Bookings\Services\StubSmsService;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BookingService(
            new StubCalendarService(),
            new StubSmsService(),
            new StubBookingEmailService(),
            new StubPushService(),
        );
        $this->coach = Coach::factory()->create();
    }

    public function test_check_availability_returns_true_when_no_bookings(): void
    {
        $result = $this->service->checkAvailability(
            $this->coach,
            Carbon::parse('2026-08-01 10:00:00'),
            Carbon::parse('2026-08-01 11:00:00'),
        );

        $this->assertTrue($result);
    }

    public function test_check_availability_returns_false_on_overlap(): void
    {
        Booking::factory()->create([
            'coach_id'  => $this->coach->id,
            'starts_at' => '2026-08-01 10:00:00',
            'ends_at'   => '2026-08-01 11:00:00',
            'status'    => BookingStatus::Confirmed->value,
        ]);

        $result = $this->service->checkAvailability(
            $this->coach,
            Carbon::parse('2026-08-01 10:30:00'),
            Carbon::parse('2026-08-01 11:30:00'),
        );

        $this->assertFalse($result);
    }

    public function test_check_availability_ignores_cancelled(): void
    {
        Booking::factory()->cancelled()->create([
            'coach_id'  => $this->coach->id,
            'starts_at' => '2026-08-01 10:00:00',
            'ends_at'   => '2026-08-01 11:00:00',
        ]);

        $result = $this->service->checkAvailability(
            $this->coach,
            Carbon::parse('2026-08-01 10:00:00'),
            Carbon::parse('2026-08-01 11:00:00'),
        );

        $this->assertTrue($result);
    }

    public function test_create_for_coach_creates_confirmed_booking(): void
    {
        $client = Client::factory()->create();

        $booking = $this->service->createForCoach($this->coach, $client, [
            'starts_at'    => '2026-08-01 10:00:00',
            'ends_at'      => '2026-08-01 11:00:00',
            'service_type' => 'personal_training',
        ]);

        $this->assertDatabaseHas('bookings', [
            'id'           => $booking->id,
            'coach_id'     => $this->coach->id,
            'client_id'    => $client->id,
            'status'       => 'confirmed',
            'source'       => 'manual',
            'service_type' => 'personal_training',
        ]);
    }

    public function test_create_for_coach_throws_on_overlap(): void
    {
        $client = Client::factory()->create();

        Booking::factory()->create([
            'coach_id'  => $this->coach->id,
            'starts_at' => '2026-08-01 10:00:00',
            'ends_at'   => '2026-08-01 11:00:00',
            'status'    => BookingStatus::Confirmed->value,
        ]);

        $this->expectException(BookingOverlapException::class);

        $this->service->createForCoach($this->coach, $client, [
            'starts_at'    => '2026-08-01 10:30:00',
            'ends_at'      => '2026-08-01 11:30:00',
            'service_type' => 'personal_training',
        ]);
    }

    public function test_cancel_sets_cancelled_status_and_reason(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'status'   => BookingStatus::Confirmed->value,
        ]);

        $this->service->cancel($booking, 'Indisponibilità', 'coach');

        $booking->refresh();
        $this->assertEquals(BookingStatus::Cancelled, $booking->status);
        $this->assertEquals('Indisponibilità', $booking->cancellation_reason);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_complete_sets_completed_status(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'status'   => BookingStatus::Confirmed->value,
        ]);

        $this->service->complete($booking);

        $this->assertEquals(BookingStatus::Completed, $booking->fresh()->status);
    }

    public function test_reschedule_updates_times(): void
    {
        $booking = Booking::factory()->create([
            'coach_id'  => $this->coach->id,
            'starts_at' => '2026-08-01 10:00:00',
            'ends_at'   => '2026-08-01 11:00:00',
            'status'    => BookingStatus::Confirmed->value,
        ]);

        $this->service->reschedule($booking, [
            'starts_at' => '2026-08-02 14:00:00',
            'ends_at'   => '2026-08-02 15:00:00',
        ]);

        $booking->refresh();
        $this->assertEquals('2026-08-02 14:00:00', $booking->starts_at->format('Y-m-d H:i:s'));
    }
}
```

- [ ] **Step 3: Run tests to confirm they fail**

```bash
php -d memory_limit=2048M artisan test --compact modules/Bookings/tests/Feature/BookingServiceTest.php
```

Expected: FAIL — `BookingService` not found.

- [ ] **Step 4: Create BookingService**

Full contents of `modules/Bookings/app/Services/BookingService.php`:

```php
<?php

namespace Modules\Bookings\Services;

use App\Models\User;
use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Bookings\Enums\BookingCancelledBy;
use Modules\Bookings\Enums\BookingSource;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Exceptions\BookingOverlapException;
use Modules\Bookings\Jobs\SendBookingReminderJob;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\Contracts\BookingEmailServiceContract;
use Modules\Bookings\Services\Contracts\CalendarServiceContract;
use Modules\Bookings\Services\Contracts\PushServiceContract;
use Modules\Bookings\Services\Contracts\SmsServiceContract;

class BookingService
{
    public function __construct(
        private readonly CalendarServiceContract $calendar,
        private readonly SmsServiceContract $sms,
        private readonly BookingEmailServiceContract $email,
        private readonly PushServiceContract $push,
    ) {}

    public function checkAvailability(
        Coach $coach,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $excludeBookingId = null,
    ): bool {
        $query = Booking::where('coach_id', $coach->id)
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::Pending->value])
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->doesntExist();
    }

    /**
     * @return array<int, array{starts_at: string, ends_at: string, duration_minutes: int, available: bool}>
     */
    public function getAvailableSlots(Coach $coach, Carbon $date, ?int $durationMinutes = null): array
    {
        $dayOfWeek      = (int) $date->format('w');
        $availabilities = $coach->availabilities()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_recurring', true)
            ->get();

        $slots = [];

        foreach ($availabilities as $availability) {
            $duration  = $durationMinutes ?? $availability->duration_minutes;
            $slotStart = Carbon::parse($date->toDateString().' '.$availability->start_time);
            $slotEnd   = Carbon::parse($date->toDateString().' '.$availability->end_time);

            while ($slotStart->copy()->addMinutes($duration)->lte($slotEnd)) {
                $thisEnd = $slotStart->copy()->addMinutes($duration);

                $slots[] = [
                    'starts_at'        => $slotStart->toISOString(),
                    'ends_at'          => $thisEnd->toISOString(),
                    'duration_minutes' => $duration,
                    'available'        => $this->checkAvailability($coach, $slotStart->copy(), $thisEnd->copy()),
                ];

                $slotStart->addMinutes($duration);
            }
        }

        return $slots;
    }

    public function createForCoach(Coach $coach, Client $client, array $data): Booking
    {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt   = Carbon::parse($data['ends_at']);

        if (! $this->checkAvailability($coach, $startsAt, $endsAt)) {
            throw new BookingOverlapException();
        }

        $booking = Booking::create([
            'coach_id'         => $coach->id,
            'client_id'        => $client->id,
            'package_id'       => $data['package_id'] ?? null,
            'service_type'     => $data['service_type'],
            'starts_at'        => $startsAt,
            'ends_at'          => $endsAt,
            'duration_minutes' => (int) $startsAt->diffInMinutes($endsAt),
            'status'           => BookingStatus::Confirmed->value,
            'source'           => BookingSource::Manual->value,
            'notes'            => $data['notes'] ?? null,
            'price'            => $data['price'] ?? null,
        ]);

        $this->dispatchReminderJobs($booking);

        $calendarEventId = $this->calendar->createEvent($booking);
        if ($calendarEventId) {
            $booking->update(['google_calendar_event_id' => $calendarEventId]);
        }

        if ($client->user) {
            $this->push->send(
                $client->user,
                'Nuova sessione prenotata',
                "Il tuo coach ha prenotato una sessione per il {$booking->starts_at->format('d/m/Y H:i')}.",
            );
        }

        Log::info('BOOKING_CREATED', ['booking_id' => $booking->id, 'source' => 'manual']);

        return $booking;
    }

    public function createPublic(array $data): Booking
    {
        $coach    = Coach::findOrFail($data['coach_id']);
        $startsAt = Carbon::parse($data['slot_starts_at']);
        $duration = (int) ($data['duration_minutes'] ?? 60);
        $endsAt   = $startsAt->copy()->addMinutes($duration);

        if (! $this->checkAvailability($coach, $startsAt, $endsAt)) {
            throw new BookingOverlapException();
        }

        // Try to find existing client by email
        $client = null;
        if (! empty($data['guest_email'])) {
            $user   = User::where('email', $data['guest_email'])->first();
            $client = $user?->client ?? null;
        }

        $booking = Booking::create([
            'coach_id'                      => $coach->id,
            'client_id'                     => $client?->id,
            'package_id'                    => $data['package_id'] ?? null,
            'guest_name'                    => $data['guest_name'],
            'guest_email'                   => $data['guest_email'],
            'guest_phone'                   => $data['guest_phone'] ?? null,
            'service_type'                  => $data['service_type'],
            'starts_at'                     => $startsAt,
            'ends_at'                       => $endsAt,
            'duration_minutes'              => $duration,
            'status'                        => BookingStatus::Confirmed->value,
            'source'                        => BookingSource::PublicWeb->value,
            'cancellation_token'            => $this->generateCancellationToken(),
            'cancellation_token_expires_at' => now()->addDays(30),
        ]);

        $this->email->sendConfirmation($booking);
        $this->sms->sendConfirmation($booking);

        $calendarEventId = $this->calendar->createEvent($booking);
        if ($calendarEventId) {
            $booking->update(['google_calendar_event_id' => $calendarEventId]);
        }

        if ($coach->user) {
            $this->push->send(
                $coach->user,
                'Nuova prenotazione',
                "Nuova prenotazione da {$booking->guest_name} per il {$booking->starts_at->format('d/m/Y H:i')}.",
            );
        }

        $this->dispatchReminderJobs($booking);

        Log::info('BOOKING_CREATED', ['booking_id' => $booking->id, 'source' => 'public_web']);

        return $booking;
    }

    public function cancel(Booking $booking, string $reason, string $cancelledBy): void
    {
        $booking->update([
            'status'              => BookingStatus::Cancelled->value,
            'cancellation_reason' => $reason,
            'cancelled_at'        => now(),
            'cancelled_by'        => $cancelledBy,
        ]);

        $this->calendar->deleteEvent($booking);

        if ($cancelledBy === BookingCancelledBy::Coach->value) {
            if ($booking->client?->user) {
                $this->push->send(
                    $booking->client->user,
                    'Sessione cancellata',
                    'Il tuo coach ha cancellato la sessione del '.$booking->starts_at->format('d/m/Y H:i').'.',
                );
                $this->email->sendCancellation($booking, $reason);
            } elseif ($booking->guest_email) {
                $this->email->sendCancellation($booking, $reason);
            }
        } else {
            if ($booking->coach->user) {
                $this->push->send(
                    $booking->coach->user,
                    'Sessione cancellata',
                    "Il cliente ha cancellato la sessione del {$booking->starts_at->format('d/m/Y H:i')}.",
                );
            }
        }

        Log::info('BOOKING_CANCELLED', ['booking_id' => $booking->id, 'by' => $cancelledBy]);
    }

    public function complete(Booking $booking): void
    {
        $booking->update(['status' => BookingStatus::Completed->value]);

        Log::info('BOOKING_COMPLETED', ['booking_id' => $booking->id]);
    }

    public function reschedule(Booking $booking, array $data): void
    {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt   = Carbon::parse($data['ends_at']);

        if (! $this->checkAvailability($booking->coach, $startsAt, $endsAt, $booking->id)) {
            throw new BookingOverlapException();
        }

        $booking->update([
            'starts_at'        => $startsAt,
            'ends_at'          => $endsAt,
            'duration_minutes' => (int) $startsAt->diffInMinutes($endsAt),
        ]);

        $this->calendar->updateEvent($booking);

        if ($booking->client?->user) {
            $this->push->send(
                $booking->client->user,
                'Sessione spostata',
                "La sessione è stata spostata al {$booking->starts_at->format('d/m/Y H:i')}.",
            );
        }
    }

    private function dispatchReminderJobs(Booking $booking): void
    {
        $now = now();

        $delay24h = $booking->starts_at->copy()->subHours(24);
        if ($delay24h->gt($now)) {
            SendBookingReminderJob::dispatch($booking->id, '24h')->delay($delay24h);
        }

        $delay1h = $booking->starts_at->copy()->subHour();
        if ($delay1h->gt($now)) {
            SendBookingReminderJob::dispatch($booking->id, '1h')->delay($delay1h);
        }
    }

    private function generateCancellationToken(): string
    {
        return Str::random(80);
    }
}
```

- [ ] **Step 5: Run tests to confirm they pass**

```bash
php -d memory_limit=2048M artisan test --compact modules/Bookings/tests/Feature/BookingServiceTest.php
```

Expected: All 7 tests PASS.

- [ ] **Step 6: Run pint**

```bash
vendor/bin/pint modules/Bookings/app/Services/ modules/Bookings/app/Jobs/ modules/Bookings/tests/ --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add modules/Bookings/app/Services/BookingService.php modules/Bookings/app/Jobs/ modules/Bookings/tests/Feature/BookingServiceTest.php
git commit -m "feat(bookings): add BookingService with availability/lifecycle methods, SendBookingReminderJob, and tests"
```

---

### Task 6: API Layer — Resources, Requests, Controllers, Routes

**Files:**
- Create: `modules/Bookings/app/Http/Resources/BookingResource.php`
- Create: `modules/Bookings/app/Http/Requests/StoreCoachBookingRequest.php`
- Create: `modules/Bookings/app/Http/Requests/UpdateBookingRequest.php`
- Create: `modules/Bookings/app/Http/Requests/CancelBookingRequest.php`
- Create: `modules/Bookings/app/Http/Requests/StorePublicBookingRequest.php`
- Create: `modules/Bookings/app/Http/Controllers/Api/V1/AvailabilitySlotsController.php`
- Create: `modules/Bookings/app/Http/Controllers/Api/V1/CoachBookingController.php`
- Create: `modules/Bookings/app/Http/Controllers/Api/V1/PublicBookingController.php`
- Modify: `modules/Bookings/routes/api.php`

- [ ] **Step 1: Create BookingResource**

Full contents of `modules/Bookings/app/Http/Resources/BookingResource.php`:

```php
<?php

namespace Modules\Bookings\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'coach_id'         => $this->coach_id,
            'client_id'        => $this->client_id,
            'package_id'       => $this->package_id,
            'guest_name'       => $this->guest_name,
            'guest_email'      => $this->guest_email,
            'guest_phone'      => $this->guest_phone,
            'service_type'     => $this->service_type,
            'starts_at'        => $this->starts_at?->toISOString(),
            'ends_at'          => $this->ends_at?->toISOString(),
            'duration_minutes' => $this->duration_minutes,
            'status'           => $this->status,
            'notes'            => $this->notes,
            'price'            => $this->price,
            'source'           => $this->source,
            'created_at'       => $this->created_at?->toISOString(),
        ];
    }
}
```

- [ ] **Step 2: Create form requests (4 files)**

Full contents of `modules/Bookings/app/Http/Requests/StoreCoachBookingRequest.php`:

```php
<?php

namespace Modules\Bookings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Bookings\Enums\BookingServiceType;

class StoreCoachBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'client_id'    => ['required', 'integer', 'exists:clients,id'],
            'starts_at'    => ['required', 'date', 'after:now'],
            'ends_at'      => ['required', 'date', 'after:starts_at'],
            'service_type' => ['required', 'string', 'in:'.implode(',', array_column(BookingServiceType::cases(), 'value'))],
            'package_id'   => ['nullable', 'integer'],
            'notes'        => ['nullable', 'string', 'max:2000'],
            'price'        => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

Full contents of `modules/Bookings/app/Http/Requests/UpdateBookingRequest.php`:

```php
<?php

namespace Modules\Bookings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Bookings\Enums\BookingStatus;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['nullable', 'date'],
            'ends_at'   => ['nullable', 'date', 'after:starts_at'],
            'notes'     => ['nullable', 'string', 'max:2000'],
            'status'    => ['nullable', 'string', 'in:'.implode(',', array_column(BookingStatus::cases(), 'value'))],
            'price'     => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
```

Full contents of `modules/Bookings/app/Http/Requests/CancelBookingRequest.php`:

```php
<?php

namespace Modules\Bookings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
```

Full contents of `modules/Bookings/app/Http/Requests/StorePublicBookingRequest.php`:

```php
<?php

namespace Modules\Bookings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Bookings\Enums\BookingServiceType;

class StorePublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coach_id'         => ['required', 'integer', 'exists:coaches,id'],
            'slot_starts_at'   => ['required', 'date', 'after:now'],
            'service_type'     => ['required', 'string', 'in:'.implode(',', array_column(BookingServiceType::cases(), 'value'))],
            'package_id'       => ['nullable', 'integer'],
            'guest_name'       => ['required', 'string', 'max:100'],
            'guest_email'      => ['required', 'email', 'max:255'],
            'guest_phone'      => ['nullable', 'string', 'max:30'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
        ];
    }
}
```

- [ ] **Step 3: Create AvailabilitySlotsController**

Full contents of `modules/Bookings/app/Http/Controllers/Api/V1/AvailabilitySlotsController.php`:

```php
<?php

namespace Modules\Bookings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Bookings\Services\BookingService;

class AvailabilitySlotsController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date'     => ['required', 'date_format:Y-m-d'],
            'duration' => ['nullable', 'integer', 'min:15', 'max:480'],
        ]);

        $coach = $request->user()->coach;

        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $date     = Carbon::parse($request->input('date'));
        $duration = $request->filled('duration') ? (int) $request->input('duration') : null;

        $slots = $this->service->getAvailableSlots($coach, $date, $duration);

        return response()->json(['data' => $slots]);
    }
}
```

- [ ] **Step 4: Create CoachBookingController**

Full contents of `modules/Bookings/app/Http/Controllers/Api/V1/CoachBookingController.php`:

```php
<?php

namespace Modules\Bookings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Users\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Exceptions\BookingOverlapException;
use Modules\Bookings\Http\Requests\CancelBookingRequest;
use Modules\Bookings\Http\Requests\StoreCoachBookingRequest;
use Modules\Bookings\Http\Requests\UpdateBookingRequest;
use Modules\Bookings\Http\Resources\BookingResource;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingService;

class CoachBookingController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    public function index(Request $request): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $query = Booking::forCoach($coach->id)->with(['client.user']);

        if ($request->filled('from')) {
            $query->where('starts_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->where('starts_at', '<=', $request->input('to'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', (int) $request->input('client_id'));
        }

        $bookings = $query->orderBy('starts_at')->paginate(20);

        return response()->json(BookingResource::collection($bookings)->response()->getData(true));
    }

    public function store(StoreCoachBookingRequest $request): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach) {
            return response()->json(['message' => 'Coach profile not found.'], 403);
        }

        $client = Client::findOrFail($request->validated('client_id'));

        if (! $coach->athletes()->where('athlete_id', $client->id)->exists()) {
            return response()->json(['message' => 'Client non trovato.'], 404);
        }

        try {
            $booking = $this->service->createForCoach($coach, $client, $request->validated());
        } catch (BookingOverlapException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new BookingResource($booking)], 201);
    }

    public function update(UpdateBookingRequest $request, Booking $booking): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach || $booking->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $data = $request->validated();

        if (isset($data['starts_at'], $data['ends_at'])) {
            try {
                $this->service->reschedule($booking, $data);
            } catch (BookingOverlapException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        if (isset($data['status'])) {
            $newStatus = BookingStatus::from($data['status']);
            if ($newStatus === BookingStatus::Completed) {
                $this->service->complete($booking);
            } else {
                $booking->update(['status' => $newStatus->value]);
            }
        }

        $updatable = array_filter(
            ['notes' => $data['notes'] ?? null, 'price' => $data['price'] ?? null],
            fn ($v) => $v !== null,
        );

        if (! empty($updatable)) {
            $booking->fill($updatable)->save();
        }

        $booking->refresh()->load('client.user');

        return response()->json(['data' => new BookingResource($booking)]);
    }

    public function destroy(CancelBookingRequest $request, Booking $booking): JsonResponse
    {
        $coach = $request->user()->coach;
        if (! $coach || $booking->coach_id !== $coach->id) {
            return response()->json(['message' => 'Non autorizzato.'], 403);
        }

        $this->service->cancel($booking, $request->validated('reason'), 'coach');

        return response()->json(null, 204);
    }
}
```

- [ ] **Step 5: Create PublicBookingController**

Full contents of `modules/Bookings/app/Http/Controllers/Api/V1/PublicBookingController.php`:

```php
<?php

namespace Modules\Bookings\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Bookings\Exceptions\BookingOverlapException;
use Modules\Bookings\Http\Requests\StorePublicBookingRequest;
use Modules\Bookings\Http\Resources\BookingResource;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingService;

class PublicBookingController extends Controller
{
    public function __construct(private readonly BookingService $service) {}

    public function store(StorePublicBookingRequest $request): JsonResponse
    {
        try {
            $booking = $this->service->createPublic($request->validated());
        } catch (BookingOverlapException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => new BookingResource($booking)], 201);
    }

    public function cancel(Request $request, string $token): JsonResponse
    {
        $booking = Booking::where('cancellation_token', $token)
            ->whereNotNull('cancellation_token')
            ->first();

        if (! $booking) {
            return response()->json(['message' => 'Token non valido.'], 404);
        }

        if ($booking->cancellation_token_expires_at?->isPast()) {
            return response()->json(['message' => 'Il token di cancellazione è scaduto.'], 422);
        }

        $windowHours       = $booking->coach->cancellation_window_hours ?? 24;
        $hoursUntilBooking = now()->diffInHours($booking->starts_at, false);

        if ($hoursUntilBooking < $windowHours) {
            return response()->json([
                'message' => "La cancellazione non è più possibile. La policy del coach richiede almeno {$windowHours} ore di preavviso.",
            ], 422);
        }

        $this->service->cancel($booking, 'Cancellato dal cliente via token.', 'client');

        return response()->json(['message' => 'Prenotazione cancellata con successo.']);
    }
}
```

- [ ] **Step 6: Write API routes**

Full contents of `modules/Bookings/routes/api.php`:

```php
<?php

use Illuminate\Support\Facades\Route;
use Modules\Bookings\Http\Controllers\Api\V1\AvailabilitySlotsController;
use Modules\Bookings\Http\Controllers\Api\V1\CoachBookingController;
use Modules\Bookings\Http\Controllers\Api\V1\PublicBookingController;

Route::prefix('api/v1')->group(function () {
    Route::middleware(['jwt.auth', 'verified.email', 'role:coach'])
        ->prefix('coaches/me')
        ->group(function () {
            Route::get('availability/slots', [AvailabilitySlotsController::class, 'index'])
                ->middleware('throttle:60,1')
                ->name('bookings.api.slots.index');

            Route::get('bookings', [CoachBookingController::class, 'index'])
                ->middleware('throttle:60,1')
                ->name('bookings.api.coach.index');

            Route::post('bookings', [CoachBookingController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('bookings.api.coach.store');

            Route::put('bookings/{booking}', [CoachBookingController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('bookings.api.coach.update');

            Route::delete('bookings/{booking}', [CoachBookingController::class, 'destroy'])
                ->middleware('throttle:20,1')
                ->name('bookings.api.coach.destroy');
        });

    Route::prefix('bookings/public')
        ->middleware('throttle:20,1')
        ->group(function () {
            Route::post('/', [PublicBookingController::class, 'store'])
                ->name('bookings.api.public.store');

            Route::delete('/{token}', [PublicBookingController::class, 'cancel'])
                ->name('bookings.api.public.cancel');
        });
});
```

- [ ] **Step 7: Run pint**

```bash
vendor/bin/pint modules/Bookings/app/Http/ modules/Bookings/routes/ --dirty --format agent
```

- [ ] **Step 8: Commit**

```bash
git add modules/Bookings/app/Http/ modules/Bookings/routes/api.php
git commit -m "feat(bookings): add API resources, requests, controllers and routes"
```

---

### Task 7: BookingsServiceProvider — final with bindings + resolveRelationUsing

**Files:**
- Modify: `modules/Bookings/app/Providers/BookingsServiceProvider.php`

- [ ] **Step 1: Replace BookingsServiceProvider with full version**

Full contents of `modules/Bookings/app/Providers/BookingsServiceProvider.php`:

```php
<?php

namespace Modules\Bookings\Providers;

use App\Modules\Users\Models\Client;
use App\Providers\ModuleServiceProvider;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Modules\Bookings\Services\BookingService;
use Modules\Bookings\Services\Contracts\BookingEmailServiceContract;
use Modules\Bookings\Services\Contracts\CalendarServiceContract;
use Modules\Bookings\Services\Contracts\PushServiceContract;
use Modules\Bookings\Services\Contracts\SmsServiceContract;
use Modules\Bookings\Services\StubBookingEmailService;
use Modules\Bookings\Services\StubCalendarService;
use Modules\Bookings\Services\StubPushService;
use Modules\Bookings\Services\StubSmsService;

class BookingsServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Bookings';

    protected string $nameLower = 'bookings';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(CalendarServiceContract::class, StubCalendarService::class);
        $this->app->bind(SmsServiceContract::class, StubSmsService::class);
        $this->app->bind(BookingEmailServiceContract::class, StubBookingEmailService::class);
        $this->app->bind(PushServiceContract::class, StubPushService::class);

        $this->app->bind(BookingService::class, fn ($app) => new BookingService(
            $app->make(CalendarServiceContract::class),
            $app->make(SmsServiceContract::class),
            $app->make(BookingEmailServiceContract::class),
            $app->make(PushServiceContract::class),
        ));
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerClientRelations();
    }

    private function registerClientRelations(): void
    {
        Client::resolveRelationUsing('upcomingBooking', fn (Client $client) => $client
            ->hasOne(Booking::class, 'client_id')
            ->where('status', BookingStatus::Confirmed->value)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
        );

        Client::resolveRelationUsing('recentSessions', fn (Client $client) => $client
            ->hasMany(Booking::class, 'client_id')
            ->where('status', BookingStatus::Completed->value)
            ->orderByDesc('starts_at')
            ->limit(10)
        );
    }
}
```

- [ ] **Step 2: Run pint**

```bash
vendor/bin/pint modules/Bookings/app/Providers/ --dirty --format agent
```

- [ ] **Step 3: Commit**

```bash
git add modules/Bookings/app/Providers/BookingsServiceProvider.php
git commit -m "feat(bookings): wire service bindings and Client dynamic relations in BookingsServiceProvider"
```

---

### Task 8: Filament

**Files:**
- Modify: `modules/Bookings/app/Filament/BookingsPlugin.php`
- Create: `modules/Bookings/app/Filament/Resources/Bookings/Schemas/BookingForm.php`
- Create: `modules/Bookings/app/Filament/Resources/Bookings/Tables/BookingsTable.php`
- Create: `modules/Bookings/app/Filament/Resources/Bookings/BookingResource.php`
- Create: `modules/Bookings/app/Filament/Resources/Bookings/Pages/ListBookings.php`
- Create: `modules/Bookings/app/Filament/Resources/Bookings/Pages/CreateBooking.php`
- Create: `modules/Bookings/app/Filament/Resources/Bookings/Pages/EditBooking.php`

- [ ] **Step 1: Update BookingsPlugin**

Full contents of `modules/Bookings/app/Filament/BookingsPlugin.php`:

```php
<?php

namespace Modules\Bookings\Filament;

use App\Filament\ModulePlugin;
use Filament\Contracts\Plugin;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class BookingsPlugin implements Plugin
{
    use ModulePlugin;

    public function getModuleName(): string
    {
        return 'Bookings';
    }

    public function getId(): string
    {
        return 'bookings';
    }

    public function boot(Panel $panel): void
    {
        $panel->navigationGroups([
            NavigationGroup::make()
                ->label(__('Bookings'))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->collapsible(),
        ]);
    }
}
```

- [ ] **Step 2: Create BookingForm**

Full contents of `modules/Bookings/app/Filament/Resources/Bookings/Schemas/BookingForm.php`:

```php
<?php

namespace Modules\Bookings\Filament\Resources\Bookings\Schemas;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingStatus;

class BookingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Section::make(__('Booking Details'))->schema([
                    Select::make('coach_id')
                        ->label(__('Coach'))
                        ->options(fn () => Coach::query()
                            ->join('users', 'coaches.user_id', '=', 'users.id')
                            ->pluck('users.name', 'coaches.id')
                            ->toArray())
                        ->searchable()
                        ->required(),

                    Select::make('client_id')
                        ->label(__('Client'))
                        ->options(fn () => Client::query()
                            ->join('users', 'clients.user_id', '=', 'users.id')
                            ->pluck('users.name', 'clients.id')
                            ->toArray())
                        ->searchable()
                        ->nullable(),

                    Select::make('service_type')
                        ->label(__('Service Type'))
                        ->options(BookingServiceType::class)
                        ->required(),

                    DateTimePicker::make('starts_at')
                        ->label(__('Start'))
                        ->required(),

                    DateTimePicker::make('ends_at')
                        ->label(__('End'))
                        ->required(),

                    TextInput::make('price')
                        ->label(__('Price (€)'))
                        ->numeric()
                        ->nullable(),

                    Textarea::make('notes')
                        ->label(__('Notes'))
                        ->rows(3)
                        ->nullable(),
                ])->columnSpan(1),

                Section::make(__('Status & Guest'))->schema([
                    Select::make('status')
                        ->label(__('Status'))
                        ->options(BookingStatus::class)
                        ->required()
                        ->default(BookingStatus::Confirmed->value),

                    TextInput::make('guest_name')
                        ->label(__('Guest Name'))
                        ->nullable(),

                    TextInput::make('guest_email')
                        ->label(__('Guest Email'))
                        ->email()
                        ->nullable(),

                    TextInput::make('guest_phone')
                        ->label(__('Guest Phone'))
                        ->nullable(),
                ])->columnSpan(1),
            ]),
        ]);
    }
}
```

- [ ] **Step 3: Create BookingsTable**

Full contents of `modules/Bookings/app/Filament/Resources/Bookings/Tables/BookingsTable.php`:

```php
<?php

namespace Modules\Bookings\Filament\Resources\Bookings\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Bookings\Enums\BookingServiceType;
use Modules\Bookings\Enums\BookingStatus;

class BookingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                TextColumn::make('client.user.name')
                    ->label(__('Client'))
                    ->default(fn ($record) => $record->guest_name ?? '—')
                    ->searchable(query: fn ($query, string $search) => $query
                        ->whereHas('client.user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                        ->orWhere('guest_name', 'like', "%{$search}%")),

                TextColumn::make('coach.user.name')
                    ->label(__('Coach'))
                    ->searchable(),

                TextColumn::make('service_type')
                    ->label(__('Service'))
                    ->badge(),

                TextColumn::make('starts_at')
                    ->label(__('Start'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label(__('End'))
                    ->dateTime('d/m/Y H:i'),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),

                TextColumn::make('source')
                    ->label(__('Source'))
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BookingStatus::class),

                SelectFilter::make('service_type')
                    ->label(__('Service Type'))
                    ->options(BookingServiceType::class),
            ])
            ->actions([
                EditAction::make()->icon(Heroicon::PencilSquare),
                DeleteAction::make(),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}
```

- [ ] **Step 4: Create BookingResource**

Full contents of `modules/Bookings/app/Filament/Resources/Bookings/BookingResource.php`:

```php
<?php

namespace Modules\Bookings\Filament\Resources\Bookings;

use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Bookings\Filament\Resources\Bookings\Pages\CreateBooking;
use Modules\Bookings\Filament\Resources\Bookings\Pages\EditBooking;
use Modules\Bookings\Filament\Resources\Bookings\Pages\ListBookings;
use Modules\Bookings\Filament\Resources\Bookings\Schemas\BookingForm;
use Modules\Bookings\Filament\Resources\Bookings\Tables\BookingsTable;
use Modules\Bookings\Models\Booking;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?int $navigationSort = 1;

    public static function getNavigationGroup(): ?string
    {
        return __('Bookings');
    }

    public static function getNavigationLabel(): string
    {
        return __('Bookings');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['guest_name', 'guest_email'];
    }

    public static function form(Schema $schema): Schema
    {
        return BookingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListBookings::route('/'),
            'create' => CreateBooking::route('/create'),
            'edit'   => EditBooking::route('/{record}/edit'),
        ];
    }
}
```

- [ ] **Step 5: Create Filament pages (3 files)**

Full contents of `modules/Bookings/app/Filament/Resources/Bookings/Pages/ListBookings.php`:

```php
<?php

namespace Modules\Bookings\Filament\Resources\Bookings\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Bookings\Filament\Resources\Bookings\BookingResource;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
```

Full contents of `modules/Bookings/app/Filament/Resources/Bookings/Pages/CreateBooking.php`:

```php
<?php

namespace Modules\Bookings\Filament\Resources\Bookings\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Bookings\Filament\Resources\Bookings\BookingResource;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;
}
```

Full contents of `modules/Bookings/app/Filament/Resources/Bookings/Pages/EditBooking.php`:

```php
<?php

namespace Modules\Bookings\Filament\Resources\Bookings\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Bookings\Filament\Resources\Bookings\BookingResource;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
```

- [ ] **Step 6: Run pint**

```bash
vendor/bin/pint modules/Bookings/app/Filament/ --dirty --format agent
```

- [ ] **Step 7: Commit**

```bash
git add modules/Bookings/app/Filament/
git commit -m "feat(bookings): add Filament BookingsPlugin, BookingResource with form, table and pages"
```

---

### Task 9: Update Users module — integrate next_booking and sessions

**Files:**
- Modify: `modules/Users/app/Http/Resources/ClientResource.php`
- Modify: `modules/Users/app/Http/Resources/ClientDetailResource.php`
- Modify: `modules/Users/app/Http/Controllers/Api/V1/CoachController.php`
- Modify: `modules/Users/app/Http/Controllers/Api/V1/CoachClientController.php`

- [ ] **Step 1: Update ClientResource — replace `'next_booking' => null`**

In `modules/Users/app/Http/Resources/ClientResource.php`, replace:

```php
            'next_booking' => null,
```

With:

```php
            'next_booking' => $this->whenLoaded('upcomingBooking', function () {
                $booking = $this->resource->upcomingBooking;
                if (! $booking) {
                    return null;
                }

                return [
                    'id'           => $booking->id,
                    'starts_at'    => $booking->starts_at?->toISOString(),
                    'ends_at'      => $booking->ends_at?->toISOString(),
                    'service_type' => $booking->service_type,
                    'status'       => $booking->status,
                ];
            }, null),
```

- [ ] **Step 2: Update ClientDetailResource — replace the 4 placeholder lines**

In `modules/Users/app/Http/Resources/ClientDetailResource.php`, replace:

```php
            'sessions' => [],
            'next_session' => null,
            'payment_history' => [],
            'next_booking' => null,
```

With:

```php
            'sessions' => $this->whenLoaded('recentSessions', function () {
                return $this->resource->recentSessions->map(fn ($b) => [
                    'id'               => $b->id,
                    'starts_at'        => $b->starts_at?->toISOString(),
                    'ends_at'          => $b->ends_at?->toISOString(),
                    'service_type'     => $b->service_type,
                    'status'           => $b->status,
                    'duration_minutes' => $b->duration_minutes,
                ]);
            }, []),
            'next_session' => $this->whenLoaded('upcomingBooking', function () {
                $booking = $this->resource->upcomingBooking;
                if (! $booking) {
                    return null;
                }

                return [
                    'id'           => $booking->id,
                    'starts_at'    => $booking->starts_at?->toISOString(),
                    'ends_at'      => $booking->ends_at?->toISOString(),
                    'service_type' => $booking->service_type,
                    'status'       => $booking->status,
                ];
            }, null),
            'payment_history' => [],
            'next_booking' => $this->whenLoaded('upcomingBooking', function () {
                $booking = $this->resource->upcomingBooking;
                if (! $booking) {
                    return null;
                }

                return [
                    'id'           => $booking->id,
                    'starts_at'    => $booking->starts_at?->toISOString(),
                    'ends_at'      => $booking->ends_at?->toISOString(),
                    'service_type' => $booking->service_type,
                    'status'       => $booking->status,
                ];
            }, null),
```

- [ ] **Step 3: Update CoachController.clients() — add upcomingBooking eager load**

In `modules/Users/app/Http/Controllers/Api/V1/CoachController.php`, replace:

```php
        $query = $coach->clients()->with('user');
```

With:

```php
        $query = $coach->clients()->with(['user', 'upcomingBooking']);
```

- [ ] **Step 4: Update CoachClientController.show() — add booking relations**

In `modules/Users/app/Http/Controllers/Api/V1/CoachClientController.php`, replace:

```php
        $client->load([
            'user',
            'notes' => fn ($q) => $q->limit(10),
            'files',
            'anamnesis',
        ]);
```

With:

```php
        $client->load([
            'user',
            'notes'          => fn ($q) => $q->limit(10),
            'files',
            'anamnesis',
            'upcomingBooking',
            'recentSessions',
        ]);
```

- [ ] **Step 5: Run pint on modified Users files**

```bash
vendor/bin/pint modules/Users/app/Http/Resources/ClientResource.php modules/Users/app/Http/Resources/ClientDetailResource.php modules/Users/app/Http/Controllers/Api/V1/CoachController.php modules/Users/app/Http/Controllers/Api/V1/CoachClientController.php --format agent
```

- [ ] **Step 6: Commit**

```bash
git add modules/Users/app/Http/Resources/ClientResource.php modules/Users/app/Http/Resources/ClientDetailResource.php modules/Users/app/Http/Controllers/Api/V1/CoachController.php modules/Users/app/Http/Controllers/Api/V1/CoachClientController.php
git commit -m "feat(bookings): integrate next_booking and sessions into Users module client resources"
```

---

### Task 10: API Tests

**Files:**
- Create: `modules/Bookings/tests/Feature/AvailabilitySlotsTest.php`
- Create: `modules/Bookings/tests/Feature/CoachBookingTest.php`
- Create: `modules/Bookings/tests/Feature/PublicBookingTest.php`

- [ ] **Step 1: Create AvailabilitySlotsTest**

Full contents of `modules/Bookings/tests/Feature/AvailabilitySlotsTest.php`:

```php
<?php

namespace Modules\Bookings\Tests\Feature;

use App\Modules\Users\Models\Coach;
use App\Modules\Users\Models\CoachAvailability;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Tests\TestCase;

class AvailabilitySlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_slots_for_coach_with_availability(): void
    {
        $coach = Coach::factory()->create();
        $nextMonday = Carbon::now()->next('Monday');

        CoachAvailability::create([
            'coach_id'         => $coach->id,
            'day_of_week'      => (int) $nextMonday->format('w'),
            'start_time'       => '09:00:00',
            'end_time'         => '11:00:00',
            'duration_minutes' => 60,
            'is_recurring'     => true,
        ]);

        $response = $this->actingAs($coach->user, 'api')
            ->getJson('/api/v1/coaches/me/availability/slots?date='.$nextMonday->format('Y-m-d'));

        $response->assertOk()
            ->assertJsonStructure(['data' => [['starts_at', 'ends_at', 'duration_minutes', 'available']]])
            ->assertJsonCount(2, 'data');
    }

    public function test_returns_empty_when_no_availability(): void
    {
        $coach = Coach::factory()->create();

        $response = $this->actingAs($coach->user, 'api')
            ->getJson('/api/v1/coaches/me/availability/slots?date=2026-08-03');

        $response->assertOk()->assertJson(['data' => []]);
    }

    public function test_occupied_slot_is_marked_unavailable(): void
    {
        $coach = Coach::factory()->create();
        $nextMonday = Carbon::now()->next('Monday');

        CoachAvailability::create([
            'coach_id'         => $coach->id,
            'day_of_week'      => (int) $nextMonday->format('w'),
            'start_time'       => '09:00:00',
            'end_time'         => '10:00:00',
            'duration_minutes' => 60,
            'is_recurring'     => true,
        ]);

        Booking::factory()->create([
            'coach_id'  => $coach->id,
            'starts_at' => $nextMonday->copy()->setTime(9, 0),
            'ends_at'   => $nextMonday->copy()->setTime(10, 0),
            'status'    => BookingStatus::Confirmed->value,
        ]);

        $response = $this->actingAs($coach->user, 'api')
            ->getJson('/api/v1/coaches/me/availability/slots?date='.$nextMonday->format('Y-m-d'));

        $response->assertOk();
        $this->assertFalse($response->json('data.0.available'));
    }
}
```

- [ ] **Step 2: Create CoachBookingTest**

Full contents of `modules/Bookings/tests/Feature/CoachBookingTest.php`:

```php
<?php

namespace Modules\Bookings\Tests\Feature;

use App\Modules\Users\Models\Client;
use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Tests\TestCase;

class CoachBookingTest extends TestCase
{
    use RefreshDatabase;

    private Coach $coach;

    protected function setUp(): void
    {
        parent::setUp();
        $this->coach = Coach::factory()->create();
    }

    public function test_coach_can_list_own_bookings(): void
    {
        $client = Client::factory()->create();
        $this->coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);
        Booking::factory()->count(3)->create(['coach_id' => $this->coach->id, 'client_id' => $client->id]);

        $response = $this->actingAs($this->coach->user, 'api')
            ->getJson('/api/v1/coaches/me/bookings');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_coach_can_create_booking_for_client(): void
    {
        $client = Client::factory()->create();
        $this->coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        $response = $this->actingAs($this->coach->user, 'api')
            ->postJson('/api/v1/coaches/me/bookings', [
                'client_id'    => $client->id,
                'starts_at'    => '2026-09-01 10:00:00',
                'ends_at'      => '2026-09-01 11:00:00',
                'service_type' => 'personal_training',
            ]);

        $response->assertCreated()->assertJsonStructure(['data' => ['id', 'status', 'starts_at']]);
        $this->assertDatabaseHas('bookings', [
            'coach_id'  => $this->coach->id,
            'client_id' => $client->id,
            'status'    => 'confirmed',
        ]);
    }

    public function test_create_booking_returns_422_on_overlap(): void
    {
        $client = Client::factory()->create();
        $this->coach->athletes()->attach($client->id, ['status' => 'active', 'started_at' => now()]);

        Booking::factory()->create([
            'coach_id'  => $this->coach->id,
            'starts_at' => '2026-09-01 10:00:00',
            'ends_at'   => '2026-09-01 11:00:00',
            'status'    => BookingStatus::Confirmed->value,
        ]);

        $response = $this->actingAs($this->coach->user, 'api')
            ->postJson('/api/v1/coaches/me/bookings', [
                'client_id'    => $client->id,
                'starts_at'    => '2026-09-01 10:30:00',
                'ends_at'      => '2026-09-01 11:30:00',
                'service_type' => 'personal_training',
            ]);

        $response->assertUnprocessable();
    }

    public function test_coach_can_cancel_booking(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'status'   => BookingStatus::Confirmed->value,
        ]);

        $response = $this->actingAs($this->coach->user, 'api')
            ->deleteJson("/api/v1/coaches/me/bookings/{$booking->id}", [
                'reason' => 'Indisponibilità improvvisa',
            ]);

        $response->assertNoContent();
        $this->assertEquals(BookingStatus::Cancelled->value, $booking->fresh()->status->value);
    }

    public function test_coach_cannot_cancel_another_coachs_booking(): void
    {
        $otherCoach = Coach::factory()->create();
        $booking = Booking::factory()->create([
            'coach_id' => $otherCoach->id,
            'status'   => BookingStatus::Confirmed->value,
        ]);

        $response = $this->actingAs($this->coach->user, 'api')
            ->deleteJson("/api/v1/coaches/me/bookings/{$booking->id}", [
                'reason' => 'Test',
            ]);

        $response->assertForbidden();
    }

    public function test_coach_can_complete_booking(): void
    {
        $booking = Booking::factory()->create([
            'coach_id' => $this->coach->id,
            'status'   => BookingStatus::Confirmed->value,
        ]);

        $response = $this->actingAs($this->coach->user, 'api')
            ->putJson("/api/v1/coaches/me/bookings/{$booking->id}", [
                'status' => 'completed',
            ]);

        $response->assertOk();
        $this->assertEquals(BookingStatus::Completed->value, $booking->fresh()->status->value);
    }
}
```

- [ ] **Step 3: Create PublicBookingTest**

Full contents of `modules/Bookings/tests/Feature/PublicBookingTest.php`:

```php
<?php

namespace Modules\Bookings\Tests\Feature;

use App\Modules\Users\Models\Coach;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Bookings\Enums\BookingStatus;
use Modules\Bookings\Models\Booking;
use Tests\TestCase;

class PublicBookingTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_can_create_booking(): void
    {
        $coach = Coach::factory()->create();

        $response = $this->postJson('/api/v1/bookings/public', [
            'coach_id'         => $coach->id,
            'slot_starts_at'   => '2026-09-01 10:00:00',
            'service_type'     => 'personal_training',
            'guest_name'       => 'Mario Rossi',
            'guest_email'      => 'mario@example.com',
            'guest_phone'      => '+39 333 1234567',
            'duration_minutes' => 60,
        ]);

        $response->assertCreated()->assertJsonStructure(['data' => ['id', 'status', 'starts_at']]);
        $this->assertDatabaseHas('bookings', [
            'coach_id'    => $coach->id,
            'guest_email' => 'mario@example.com',
            'status'      => 'confirmed',
            'source'      => 'public_web',
        ]);

        $booking = Booking::where('guest_email', 'mario@example.com')->first();
        $this->assertNotNull($booking->cancellation_token);
    }

    public function test_public_booking_returns_422_on_overlap(): void
    {
        $coach = Coach::factory()->create();

        Booking::factory()->create([
            'coach_id'  => $coach->id,
            'starts_at' => '2026-09-01 10:00:00',
            'ends_at'   => '2026-09-01 11:00:00',
            'status'    => BookingStatus::Confirmed->value,
        ]);

        $response = $this->postJson('/api/v1/bookings/public', [
            'coach_id'         => $coach->id,
            'slot_starts_at'   => '2026-09-01 10:30:00',
            'service_type'     => 'personal_training',
            'guest_name'       => 'Mario Rossi',
            'guest_email'      => 'mario@example.com',
            'duration_minutes' => 60,
        ]);

        $response->assertUnprocessable();
    }

    public function test_can_cancel_with_valid_token_inside_window(): void
    {
        $coach = Coach::factory()->create(['cancellation_window_hours' => 24]);

        Booking::factory()->create([
            'coach_id'                      => $coach->id,
            'starts_at'                     => now()->addDays(3),
            'ends_at'                       => now()->addDays(3)->addHour(),
            'status'                        => BookingStatus::Confirmed->value,
            'cancellation_token'            => 'valid-token-abc',
            'cancellation_token_expires_at' => now()->addDays(30),
        ]);

        $response = $this->deleteJson('/api/v1/bookings/public/valid-token-abc');

        $response->assertOk()->assertJsonStructure(['message']);
    }

    public function test_cancel_outside_policy_window_returns_422(): void
    {
        $coach = Coach::factory()->create(['cancellation_window_hours' => 48]);

        Booking::factory()->create([
            'coach_id'                      => $coach->id,
            'starts_at'                     => now()->addHours(24),
            'ends_at'                       => now()->addHours(25),
            'status'                        => BookingStatus::Confirmed->value,
            'cancellation_token'            => 'late-token-xyz',
            'cancellation_token_expires_at' => now()->addDays(30),
        ]);

        $response = $this->deleteJson('/api/v1/bookings/public/late-token-xyz');

        $response->assertUnprocessable()->assertJsonStructure(['message']);
    }

    public function test_cancel_with_invalid_token_returns_404(): void
    {
        $response = $this->deleteJson('/api/v1/bookings/public/nonexistent-token');

        $response->assertNotFound();
    }
}
```

- [ ] **Step 4: Run all Bookings tests**

```bash
php -d memory_limit=2048M artisan test --compact --filter='^Modules\\Bookings\\Tests'
```

Expected: All tests PASS.

- [ ] **Step 5: Run pint on test files**

```bash
vendor/bin/pint modules/Bookings/tests/ --dirty --format agent
```

- [ ] **Step 6: Commit**

```bash
git add modules/Bookings/tests/Feature/AvailabilitySlotsTest.php modules/Bookings/tests/Feature/CoachBookingTest.php modules/Bookings/tests/Feature/PublicBookingTest.php
git commit -m "test(bookings): add API feature tests for availability slots, coach bookings, public bookings"
```

---

### Task 11: Build + PHPStan + Full Suite

- [ ] **Step 1: Run PHPStan**

```bash
composer analyse
```

Fix any level-5 errors before proceeding.

- [ ] **Step 2: Rebuild frontend assets**

```bash
npm run build
```

Expected: build exits with code 0.

- [ ] **Step 3: Run full test suite**

```bash
php -d memory_limit=2048M artisan test --compact
```

Expected: all existing tests still pass, new Bookings tests pass.

- [ ] **Step 4: Final commit if any lint/build fixes were made**

```bash
git add -A
git commit -m "chore(bookings): fix lint/stan issues, rebuild assets"
```
