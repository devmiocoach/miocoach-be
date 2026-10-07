# Bookings Module — Design Spec
**Date:** 2026-06-05
**Branch:** bookings-module (to be created from main)

---

## Overview

Modulo `Bookings` per Miocoach. Gestisce prenotazioni sessioni tra coach e clienti, sia via dashboard coach (autenticato) che via vetrina pubblica (senza auth). Implementa slot availability, reminder automatici, integrazione stub con Google Calendar, SMS e email.

---

## 1. Architettura

**Pattern:** `BookingService` central con 4 contratti iniettati (`CalendarServiceContract`, `SmsServiceContract`, `BookingEmailServiceContract`, `PushNotificationContract`). Controller thin, tutta la logica orchestrata nel service. Stub su tutti i servizi esterni — swap futuro via binding nel provider.

**Integrazione Users module:** Zero dipendenze dirette. `BookingsServiceProvider::boot()` registra relazioni dinamiche su `Client` via `resolveRelationUsing()`. `ClientResource` e `ClientDetailResource` (modulo Users) caricano `upcomingBooking` e `recentSessions` tramite queste relazioni.

---

## 2. Directory Structure

```
modules/Bookings/
  app/
    Enums/
      BookingStatus.php
      BookingServiceType.php
      BookingSource.php
      BookingCancelledBy.php
    Models/
      Booking.php
    Http/
      Controllers/Api/V1/
        CoachBookingController.php
        PublicBookingController.php
        AvailabilitySlotsController.php
      Requests/
        StoreCoachBookingRequest.php
        UpdateBookingRequest.php
        CancelBookingRequest.php
        StorePublicBookingRequest.php
      Resources/
        BookingResource.php
    Services/
      BookingService.php
      Contracts/
        CalendarServiceContract.php
        SmsServiceContract.php
        BookingEmailServiceContract.php
      StubCalendarService.php
      StubSmsService.php
      StubBookingEmailService.php
    Jobs/
      SendBookingReminderJob.php
    Filament/
      BookingsPlugin.php
      Resources/
        Bookings/
          BookingResource.php
          BookingForm.php
          BookingTable.php
          pages/
            ListBookings.php
            CreateBooking.php
            EditBooking.php
    Providers/
      BookingsServiceProvider.php
      RouteServiceProvider.php
  database/
    migrations/
      2026_06_05_000001_create_bookings_table.php
    factories/
      BookingFactory.php
  routes/
    api.php
    web.php           # vuoto
    navigation.php    # vuoto — modulo admin-only
  resources/js/
    app.ts
    types/
      generated.d.ts
  tests/
    Feature/
      CoachBookingTest.php
      PublicBookingTest.php
      AvailabilitySlotsTest.php
  module.json
  composer.json
  vite.config.js
  Taskfile.yml
```

---

## 3. Database Schema

### Tabella `bookings`

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint PK | |
| `coach_id` | bigint FK coaches | cascade delete |
| `client_id` | bigint FK clients nullable | null per booking pubblici senza account |
| `package_id` | bigint nullable | no FK — futuro modulo Billing |
| `guest_name` | varchar(100) nullable | booking pubblici |
| `guest_email` | varchar(255) nullable | booking pubblici |
| `guest_phone` | varchar(30) nullable | booking pubblici |
| `service_type` | varchar(50) | BookingServiceType enum |
| `starts_at` | datetime | |
| `ends_at` | datetime | |
| `duration_minutes` | smallint unsigned | calcolato da starts_at/ends_at |
| `status` | varchar(20) | BookingStatus enum, default `confirmed` |
| `notes` | text nullable | |
| `cancellation_reason` | text nullable | |
| `cancelled_at` | timestamp nullable | |
| `cancelled_by` | varchar(10) nullable | BookingCancelledBy enum |
| `price` | decimal(10,2) nullable | snapshot del prezzo al momento della prenotazione |
| `source` | varchar(20) | BookingSource enum |
| `cancellation_token` | varchar(100) unique nullable | JWT 30gg — solo booking pubblici |
| `cancellation_token_expires_at` | timestamp nullable | |
| `google_calendar_event_id` | varchar(255) nullable | per futuro sync |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |
| `deleted_at` | timestamp nullable | soft delete |

**Indici:** `(coach_id, starts_at)`, `(client_id, starts_at)`, `(status)`, `cancellation_token` unique.

---

## 4. Enums (PHP, backed by string)

```php
#[TypeScript]
enum BookingStatus: string implements HasLabel, HasColor
{
    case Pending   = 'pending';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow    = 'no_show';
}

#[TypeScript]
enum BookingServiceType: string implements HasLabel
{
    case PersonalTraining       = 'personal_training';
    case OnlineCoaching         = 'online_coaching';
    case Assessment             = 'assessment';
    case GroupSession           = 'group_session';
    case NutritionConsultation  = 'nutrition_consultation';
}

#[TypeScript]
enum BookingSource: string implements HasLabel
{
    case Manual    = 'manual';
    case PublicWeb = 'public_web';
    case AiBooking = 'ai_booking';
}

enum BookingCancelledBy: string
{
    case Coach  = 'coach';
    case Client = 'client';
}
```

---

## 5. API Routes

```php
// Authenticated coach (middleware: auth:api, verified, role:coach)
GET    /api/v1/coaches/me/availability/slots?date=&duration=
GET    /api/v1/coaches/me/bookings?from=&to=&status=&clientId=
POST   /api/v1/coaches/me/bookings
PUT    /api/v1/coaches/me/bookings/{booking}
DELETE /api/v1/coaches/me/bookings/{booking}

// Public (no auth)
POST   /api/v1/bookings/public
DELETE /api/v1/bookings/public/{token}
```

### `GET /coaches/me/availability/slots`
Parametri: `date` (Y-m-d, required), `duration` (minuti, opzionale — default `duration_minutes` dalla disponibilità).
Logica: legge `CoachAvailability` ricorrenti per `day_of_week`, genera slot ogni `duration_minutes`, esclude slot sovrapposti a bookings `confirmed` o `pending`. Ritorna array `[{ starts_at, ends_at, duration_minutes, available: bool }]`.

### `POST /coaches/me/bookings`
Body: `{ clientId, startsAt, endsAt, serviceType, packageId?, notes?, price? }`
Logica: verifica overlap → crea `Booking` status `confirmed`, source `manual` → dispatch reminder jobs → `calendar->createEvent()` → push al client.

### `PUT /coaches/me/bookings/{booking}`
Body: `{ startsAt?, endsAt?, notes?, status?, price? }`
Logica: se cambio orario → reverifica overlap → `calendar->updateEvent()`. Se `status → completed` → chiama `BookingService::complete()`. Notifica cliente se cambio orario o status. (Il dispatch di `CalculateAiProgressionJob` rimane a carico di `WorkoutSessionController::complete()` — Booking non ha FK a WorkoutSession.)

### `DELETE /coaches/me/bookings/{booking}`
Body: `{ reason: string }`
Logica: `cancel()` su BookingService → notifica push + email al client → `calendar->deleteEvent()`.

### `POST /bookings/public`
Body: `{ coachId, slotStartsAt, serviceType, packageId?, guestName, guestEmail, guestPhone }`
Logica: verifica overlap → genera `cancellation_token` (JWT signed, 30gg TTL) → crea `Booking` status `confirmed`, source `public_web` → `email->sendConfirmation()` → `sms->sendConfirmation()` → push al coach → `calendar->createEvent()` → dispatch reminder jobs.

### `DELETE /bookings/public/{token}`
Logica: valida token → controlla `cancellation_window_hours` del coach (da `coaches.cancellation_window_hours`) → se inside window: `cancel()` + notifica coach → se outside window: 422 con messaggio policy.

---

## 6. BookingService

```php
class BookingService
{
    public function __construct(
        private CalendarServiceContract $calendar,
        private SmsServiceContract $sms,
        private BookingEmailServiceContract $email,
        private PushNotificationContract $push,
    ) {}

    public function createForCoach(Coach $coach, Client $client, array $data): Booking;
    public function createPublic(array $data): Booking;
    public function cancel(Booking $booking, string $reason, string $cancelledBy): void;
    public function complete(Booking $booking): void;
    public function reschedule(Booking $booking, array $data): void;
    public function checkAvailability(Coach $coach, Carbon $startsAt, Carbon $endsAt, ?int $excludeBookingId = null): bool;
    public function getAvailableSlots(Coach $coach, Carbon $date, ?int $durationMinutes = null): array;
    private function dispatchReminderJobs(Booking $booking): void;
    private function generateCancellationToken(Booking $booking): string;
}
```

**Overlap check:** query `bookings` WHERE `coach_id = $coach->id AND status IN (confirmed, pending) AND NOT (ends_at <= $startsAt OR starts_at >= $endsAt)` AND `id != $excludeBookingId`. Se risultati → throw `BookingOverlapException`.

---

## 7. Service Contracts + Stubs

```php
interface CalendarServiceContract {
    public function createEvent(Booking $booking): ?string;  // returns event_id
    public function updateEvent(Booking $booking): void;
    public function deleteEvent(Booking $booking): void;
}

interface SmsServiceContract {
    public function sendConfirmation(Booking $booking): void;
    public function sendCancellation(Booking $booking): void;
    public function sendReminder(Booking $booking): void;
}

interface BookingEmailServiceContract {
    public function sendConfirmation(Booking $booking): void;
    public function sendCancellation(Booking $booking, string $reason): void;
    public function sendReminder(Booking $booking): void;
}
```

Tutti gli stub: `Log::info('[Bookings Stub] ' . __METHOD__, ['booking_id' => $booking->id])`.

`BookingsServiceProvider` binda:
- `CalendarServiceContract → StubCalendarService`
- `SmsServiceContract → StubSmsService`
- `BookingEmailServiceContract → StubBookingEmailService`

---

## 8. Jobs

### `SendBookingReminderJob`
```php
// Dispatched twice on booking creation:
SendBookingReminderJob::dispatch($booking->id, '24h')
    ->delay($booking->starts_at->subHours(24));
SendBookingReminderJob::dispatch($booking->id, '1h')
    ->delay($booking->starts_at->subHour());
```
- Ricarica booking: se non `confirmed` → abort silenzioso
- `push->send($client->user, ...)` + `email->sendReminder($booking)`
- Push al coach per reminder 1h

---

## 9. Integrazione Users Module

`BookingsServiceProvider::boot()` registra relazioni dinamiche su `Client` senza modificare il modello Users:

```php
Client::resolveRelationUsing('upcomingBooking', fn ($client) =>
    $client->hasOne(Booking::class, 'client_id')
           ->where('status', BookingStatus::Confirmed->value)
           ->where('starts_at', '>', now())
           ->orderBy('starts_at')
);

Client::resolveRelationUsing('recentSessions', fn ($client) =>
    $client->hasMany(Booking::class, 'client_id')
           ->where('status', BookingStatus::Completed->value)
           ->orderByDesc('starts_at')
           ->limit(10)
);
```

**`ClientResource`** (Users module) — aggiornamento:
```php
'next_booking' => $this->whenLoaded('upcomingBooking', fn () =>
    new BookingResource($this->upcomingBooking)
),
```

**`ClientDetailResource`** (Users module) — aggiornamento:
```php
'next_session'  => $this->whenLoaded('upcomingBooking', fn () =>
    new BookingResource($this->upcomingBooking)
),
'sessions'      => BookingResource::collection(
    $this->whenLoaded('recentSessions', fn () => $this->recentSessions)
),
'next_booking'  => $this->whenLoaded('upcomingBooking', fn () =>
    new BookingResource($this->upcomingBooking)
),
```

Il controller `CoachClientController` (Users) carica `with(['upcomingBooking', 'recentSessions'])` se il modulo Bookings è abilitato (check via `app()->bound(BookingService::class)`).

---

## 10. Filament

**`BookingsPlugin`** — navigation group `Bookings`, icon `Heroicon::OutlinedCalendarDays`.

**`BookingResource`:**
- Model: `Booking`
- Navigation sort: 1
- Globally searchable: `guest_name`, `guest_email` + relazione `client.user.email`
- **Table:** colonne `client/guest`, `coach`, `service_type`, `starts_at`, `ends_at`, `status` (badge con colori), `source`; filtri per `status`, `service_type`, date range, coach
- **Form:** Grid 2 colonne; sezioni: Booking Details (coach, client, service_type, starts_at, ends_at, price, notes), Status (status), Guest Info (guest_name, guest_email, guest_phone — visibili solo se client_id null)

---

## 11. Testing

**`CoachBookingTest`:** create booking (happy path), overlap → 422, cancel dentro window, complete → status change, reschedule → calendar stub chiamato.

**`PublicBookingTest`:** create pubblico → token generato, cancella con token valido, cancella fuori window → 422, token scaduto → 422.

**`AvailabilitySlotsTest`:** slots liberi restituiti, slots occupati esclusi, coach senza disponibilità → array vuoto.

---

## 12. Out of Scope (stub / futuro)

- Google Calendar OAuth real (`GET /auth/google`, `GET /auth/google/callback`) — stub CalendarService basta per ora
- Twilio SMS reale — stub SmsService
- SendGrid email reale — stub EmailService
- Recurring bookings
- Integrazione Billing `package_id` FK + saldo sessioni
- `CheckInactiveClientsJob` nel modulo Notifications (dipende da questo modulo — da completare quando Bookings è live)
