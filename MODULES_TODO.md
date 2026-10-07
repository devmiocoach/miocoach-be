# Moduli da costruire

Questo file traccia gli endpoint e le funzionalità che esulano dai moduli esistenti (Auth, Users, WorkoutPlans) e vanno implementati in nuovi moduli dedicati.

---

## Modulo: WorkoutPlans — funzionalità infrastrutturali *(futuro)*

Features del modulo WorkoutPlans che richiedono infrastruttura esterna e sono state escluse dall'implementazione iniziale.

### Video Upload esercizi custom

**Trigger:** `POST /coaches/me/exercises` con body `multipart/form-data`

- Upload file video (mp4/mov, max 100MB) su storage S3-compatible (`media-prod`)
- Path: `exercises/{coachId}/{uuid}.mp4`
- Job asincrono (Laravel Queue) per generare thumbnail via `ffmpeg`
- Salva `video_url` (URL storage) e `thumbnail_url` (URL thumbnail generata) sull'esercizio
- Se URL esterno (YouTube/Vimeo) → salva diretto senza upload

**Dipende da:** configurazione bucket S3 + `ffmpeg` disponibile sul server

### FCM Push Notifications — WorkoutPlans

Notifiche push specifiche del modulo WorkoutPlans, da collegare al `PushNotificationContract` del modulo Notifications:

| Trigger | Messaggio |
|---|---|
| `POST /workout-plans/:id/assign` | `"Il tuo coach ti ha assegnato una nuova scheda: [Nome Piano]"` per ogni client assegnato |
| Nuovo personal record su SessionSet | `"🏆 Nuovo record su [Esercizio]: [X] kg!"` per il cliente |

**Dipende da:** modulo Notifications implementato + credenziali Firebase FCM

### Job: `CalculateAiProgressionJob` *(STUB)* ✅ stub implementato

Job pubblicato al completamento di ogni `WorkoutSession`, per ogni esercizio della sessione.

- **Dipende da:** servizio AI esterno / modulo AI non ancora definito
- ✅ Stub implementato in `modules/WorkoutPlans/app/Jobs/CalculateAiProgressionJob.php` — logga `TODO: requires AI progression service` e ritorna senza fare nulla
- ✅ Pubblicato da `WorkoutSessionController::complete()` via `CalculateAiProgressionJob::dispatch($workoutSession->id, $exerciseId)` per ogni esercizio con set completati
- ⏳ **Da fare quando arriva il modulo AI:** sostituire il corpo di `handle()` con la logica reale di progressione

---

## Modulo: CoachCommerce ✅ implementato

**Path:** `modules/CoachCommerce/`

Gestisce pacchetti sessioni e piani di abbonamento del coach verso i clienti (separato da Billing che gestisce le subscription SaaS della piattaforma).

### Implementato

- ✅ Modello `Package` con migration, factory
- ✅ Modello `SubscriptionPlan` con migration, factory
- ✅ `Coach::packages()` e `Coach::subscriptionPlans()` registrati via `resolveRelationUsing()` in `CoachCommerceServiceProvider`
- ✅ 15 test PHPUnit (CRUD completo + ownership check + validazione)

### API Endpoints

#### Packages
| Metodo | Path | Descrizione | Stato |
|---|---|---|---|
| `GET` | `/api/v1/coaches/me/packages` | Lista pacchetti del coach | ✅ |
| `POST` | `/api/v1/coaches/me/packages` | Crea un pacchetto sessioni | ✅ |
| `PUT` | `/api/v1/coaches/me/packages/{id}` | Aggiorna un pacchetto | ✅ |
| `DELETE` | `/api/v1/coaches/me/packages/{id}` | Elimina un pacchetto | ✅ |

#### Subscription Plans
| Metodo | Path | Descrizione | Stato |
|---|---|---|---|
| `GET` | `/api/v1/coaches/me/subscription-plans` | Lista piani del coach | ✅ |
| `POST` | `/api/v1/coaches/me/subscription-plans` | Crea un piano abbonamento | ✅ |
| `PUT` | `/api/v1/coaches/me/subscription-plans/{id}` | Aggiorna un piano | ✅ |
| `DELETE` | `/api/v1/coaches/me/subscription-plans/{id}` | Elimina un piano | ✅ |

### Responsabilità sui dati cliente
Le colonne `subscription_expires_at` e `subscription_sessions_remaining` vivono sulla tabella `clients` (aggiunte dal modulo Users per supportare il listing) ma vengono **scritte e mantenute da CoachCommerce** quando si attiva/rinnova/scade un abbonamento.

### Da fare
- ⏳ Sincronizzazione Stripe: `stripe_price_id` presente su `subscription_plans` ma non ancora popolato — richiede Stripe Connect flow
- ⏳ Attivazione abbonamento cliente: scrittura `subscription_expires_at` / `subscription_sessions_remaining` su `clients` quando un piano viene acquistato

---

## Modulo: Reviews

**Path:** `app/Modules/Reviews/`

Gestisce le recensioni dei clienti ai coach. I dati di rating/recensioni sono referenziati nel profilo pubblico coach (`GET /coaches/{id}/public`) che è nel modulo Users.

### Endpoint

| Metodo | Path | Descrizione |
|---|---|---|
| `POST` | `/api/v1/coaches/{id}/reviews` | Il cliente lascia una recensione |
| `GET` | `/api/v1/coaches/{id}/reviews` | Lista pubblica recensioni coach |
| `DELETE` | `/api/v1/reviews/{id}` | Eliminazione (admin o proprietario) |

**Modello `Review`** — campi indicativi:
```
id, coach_id (FK coaches), client_id (FK clients), rating (tinyInt 1-5),
body (text nullable), is_visible (boolean), created_at, updated_at
```

### Integrazione con Users Module
`GET /api/v1/coaches/{id}/public` (nel modulo Users) deve esporre:
- `rating`: media calcolata da `reviews.rating` WHERE `coach_id = $id AND is_visible = true`
- `reviews_count`: count degli stessi record

Il modulo Users non interroga direttamente la tabella `reviews` ma può farlo tramite una relazione Eloquent su `Coach` definita nel modulo Reviews (oppure il valore viene cachato su `coaches.rating_avg` e `coaches.reviews_count` per performance).

---

## Modulo: Bookings ✅ implementato

**Path:** `modules/Bookings/`

Gestisce la prenotazione di sessioni da parte dei clienti, collegandosi agli slot di disponibilità del coach (`coach_availabilities` — modulo Users) e ai pagamenti (modulo Billing).

### Implementato

- ✅ Modello `Booking` con migration, factory, SoftDeletes
- ✅ Enums: `BookingServiceType`, `BookingStatus`, `BookingSource`, `BookingCancelledBy`
- ✅ `BookingService` — orchestratore centrale con 4 contratti iniettati (Calendar, SMS, Email, Push)
- ✅ Stub services per tutti i contratti (log `[Bookings Stub] ...`)
- ✅ `SendBookingReminderJob` — reminder 24h e 1h prima della sessione (Laravel Queue con `delay()`)
- ✅ `cancellation_token` (80 char, TTL 30 giorni) per cancellazione pubblica
- ✅ Policy `cancellation_window_hours` da `coaches`

### API Endpoints

| Metodo | Path | Descrizione | Stato |
|---|---|---|---|
| `GET` | `/api/v1/coaches/me/availability/slots` | Slot disponibili per data/durata | ✅ |
| `GET` | `/api/v1/coaches/me/bookings` | Lista prenotazioni coach (filtri: from/to/status/client_id) | ✅ |
| `POST` | `/api/v1/coaches/me/bookings` | Crea prenotazione manuale | ✅ |
| `PUT` | `/api/v1/coaches/me/bookings/{id}` | Reschedule / completa / aggiorna | ✅ |
| `DELETE` | `/api/v1/coaches/me/bookings/{id}` | Cancella con motivo | ✅ |
| `POST` | `/api/v1/bookings/public` | Prenotazione pubblica (guest) | ✅ |
| `DELETE` | `/api/v1/bookings/public/{token}` | Cancellazione via token | ✅ |
| `GET` | `/api/v1/coaches/me/calendar/status` | Stato connessione Google Calendar | ✅ |
| `GET` | `/api/v1/coaches/me/calendar/auth` | URL OAuth per collegare Google Calendar | ✅ |
| `DELETE` | `/api/v1/coaches/me/calendar/disconnect` | Disconnetti Google Calendar | ✅ |
| `GET` | `/api/v1/coaches/calendar/callback` | Callback OAuth (redirect da Google) | ✅ |

### Responsabilità sui dati cliente

- ✅ `next_booking` in `GET /coaches/me/clients` — popolato via `Client::resolveRelationUsing('upcomingBooking', ...)` in `BookingsServiceProvider`
- ✅ Storico ultime 10 sessioni (`sessions`) e prossima sessione (`next_session`) in `GET /coaches/me/clients/:id` — popolato via `Client::resolveRelationUsing('recentSessions', ...)`

### Filament Admin

- ✅ `BookingResource` — lista, crea, modifica prenotazioni; filtri per status e service_type

### Da fare

- ✅ Integrazione Google Calendar — `GoogleCalendarService` (crea/aggiorna/elimina eventi), OAuth flow con state token via Cache, token cifrati con `Crypt::encryptString()`
- ⏳ Integrazione reale SMS (sostituire `StubSmsService`)
- ⏳ Integrazione reale Email booking (sostituire `StubBookingEmailService`)
- ⏳ Collegamento a modulo Billing per `package_id` FK (ora nullable senza FK per forward-compat)

---

## Modulo: Notifications

**Path:** `modules/Notifications/`

Gestisce le notifiche in-app e push verso coach e clienti. Necessario per i cron job della spec client management.

### Struttura modulo

```
modules/Notifications/
  app/
    Models/
      Notification.php
      DeviceToken.php
    Http/Controllers/Api/V1/
      NotificationController.php   # GET list, PATCH read
    Http/Resources/
      NotificationResource.php
    Services/
      PushNotificationContract.php  # interfaccia
      StubPushNotificationService.php  # implementazione stub (log), wiring FCM in futuro
    Jobs/
      CheckExpiringSubscriptionsJob.php  # implementazione reale
      CheckInactiveClientsJob.php        # STUB — dipende da modulo Bookings
      SendCheckinRemindersJob.php        # STUB — dipende da modulo Forms
    Providers/
      NotificationsServiceProvider.php  # registra binding PushNotificationContract → Stub + Scheduler
  database/migrations/
    create_notifications_table.php
    create_device_tokens_table.php
  routes/api.php
```

### Modello `Notification`
```
id, user_id (FK users), type (enum), body (text),
read_at (timestamp nullable), metadata (json nullable),
created_at, updated_at
```

### Modello `DeviceToken`
```
id, user_id (FK users), token (string unique),
platform (enum: fcm|apns), created_at, updated_at
```
> Preferire tabella dedicata `device_tokens` rispetto a `coaches.fcm_token` per supportare multi-device e future piattaforme (APNs).

### Tipi di notifica (enum)
| Tipo | Trigger |
|---|---|
| `SUBSCRIPTION_EXPIRING` | subscriptionExpiresAt tra oggi e +7 giorni |
| `CLIENT_INACTIVE` | nessuna sessione completata negli ultimi N giorni |
| `CHECKIN_REMINDER` | CheckinFormAssignment con frequenza corrispondente al giorno |

### Push Notifications — architettura

```php
// app/Modules/Notifications/Services/PushNotificationContract.php
interface PushNotificationContract {
    public function send(User $user, string $title, string $body, array $data = []): void;
}

// StubPushNotificationService → Log::info() per ora
// FcmPushNotificationService → kreait/firebase-php quando arrivano credenziali Firebase
```

Il `NotificationsServiceProvider` binda `PushNotificationContract → StubPushNotificationService`.
Per abilitare FCM: cambia solo il binding nel provider, senza toccare i Job.

### API Endpoints

```
GET    /api/v1/notifications           # lista notifiche utente autenticato (paginata)
PATCH  /api/v1/notifications/{id}/read # segna come letta
DELETE /api/v1/notifications/{id}      # elimina notifica
POST   /api/v1/notifications/device-token  # registra FCM device token
```

### Cron Jobs — Laravel Scheduler (non BullMQ)

Registrati in `NotificationsServiceProvider::boot()` via `app()->booted()`.
Schedule: ogni giorno 08:00 UTC per tutti e tre.

#### Job: `CheckExpiringSubscriptionsJob` ✅ implementabile subito
- Trova clienti con `subscription_expires_at` tra `now()` e `now()->addDays(7)` (status active)
- Deduplicazione: skip se `Notification` con `type=SUBSCRIPTION_EXPIRING` e `metadata.client_id=X` esiste nella giornata odierna
- Per ogni cliente: crea `Notification` per `coach.user` + `PushNotificationContract::send()`
- **Dipende da:** colonne `subscription_expires_at` su `clients` (già presenti)

#### Job: `CheckInactiveClientsJob` ⚠️ STUB — dipendenza ora disponibile
- **Dipende da:** tabella `bookings` del modulo Bookings ✅ **ora implementato** — la dipendenza è soddisfatta
- Implementazione stub: logga `TODO: requires Bookings module` e ritorna senza fare nulla
- `N` giorni di inattività = impostazione coach `inactivity_threshold_days` (default 14) — aggiungere colonna a `coaches` quando si implementa
- ⏳ **Pronto per implementazione reale**: query `Booking::where('coach_id', $coachId)->where('status', 'completed')->where('ends_at', '>=', now()->subDays($n))` per trovare clienti inattivi

#### Job: `SendCheckinRemindersJob` ⚠️ STUB
- **Dipende da:** modulo Forms/Checkins (non ancora implementato)
- Implementazione stub: logga `TODO: requires Forms module` e ritorna senza fare nulla

---

## Modulo: Forms / Checkins *(futuro)*

**Path:** `app/Modules/Forms/`

Gestisce i questionari di check-in periodici assegnati dal coach ai clienti.

### Modelli indicativi
- `CheckinForm`: id, coach_id, name, fields (json), is_active
- `CheckinFormAssignment`: id, form_id, client_id, frequency (enum: daily/weekly/monthly), next_due_at, created_at
- `CheckinResponse`: id, assignment_id, client_id, answers (json), submitted_at

### Integrazione con Notifications
Il job `sendCheckinReminders` (modulo Notifications) legge `CheckinFormAssignment` da questo modulo.

---

## Modulo: Users — endpoint completati ✅

> Endpoint identificati come mancanti durante l'audit del modulo Users e implementati nel branch `allinaemento-auth-user`.

| Metodo | Path | Stato |
|---|---|---|
| `GET` | `/api/v1/coaches/me/clients/{client}/notes` | ✅ Implementato |
| `DELETE` | `/api/v1/coaches/me/clients/{client}/notes/{clientNote}` | ✅ Implementato |
| `GET` | `/api/v1/coaches/me/clients/{client}/anamnesis` | ✅ Implementato |
| `GET` | `/api/v1/coaches/me/clients/{client}/files` | ✅ Implementato |
| `DELETE` | `/api/v1/coaches/me/clients/{client}/files/{clientFile}` | ✅ Implementato |

**Note:**
- `anamnesis` usa `EncryptionService::decrypt()` — restituisce `{"data": null}` se nessuna anamnesi presente
- `files` usa `Storage::disk('r2_docs')` — delete rimuove da storage + DB
- `notes` filtrate per `coach_id` — nessun coach può vedere note di atleti altrui

---

## Modulo: Auth — endpoint aggiuntivo

> Non un nuovo modulo, ma un endpoint mancante nel modulo Auth esistente (`modules/Auth/`).

### `POST /api/v1/auth/accept-invite/{token}` — Onboarding cliente da link invito

**Stato:** ✅ Implementato

**Dipende da:** `app/Services/InviteTokenService` (cross-module wrapper di `JwtService`)

**Logica:**
1. Valida JWT (7 gg TTL) via `InviteTokenService::decode()` — claims: `{ purpose: 'invite', coach_id, email, exp }`
2. Trova `CoachInvitation` by `(token, status=pending)` — se mancante o scaduto → 422
3. Body: `{ password, passwordConfirmation, firstName, lastName, dateOfBirth?, gender?, heightCm?, objective?, fitnessLevel? }`
4. Crea `User` con `email_verified_at = now()` (invito = verifica implicita)
5. Crea `Client` con campi profilo + attacca a coach via `AttachAthleteToCoachAction`
6. Segna `CoachInvitation.status = accepted`
7. Ritorna `access_token` + `refresh_token` cookie (login diretto, stesso formato di `/auth/login`)

**File modificati:**
| File | Modifica |
|------|---------|
| `modules/Users/app/Actions/SendInvitationAction.php` | Genera JWT via `InviteTokenService` invece di `Str::random(64)` |
| `modules/Users/database/migrations/…_token_to_text.php` | `coach_invitations.token` VARCHAR(64) → TEXT |
| `modules/Users/database/migrations/…_add_fields_to_clients.php` | +`objective VARCHAR(255)`, +`fitness_level VARCHAR(50)` |
| `app/Services/InviteTokenService.php` | **NUOVO** — genera + decoda JWT invito (wrappa `JwtService`) |
| `modules/Auth/app/Services/JwtService.php` | +`generateInviteToken(int $coachId, string $email): string` |
| `modules/Auth/app/Actions/RegisterViaInviteAction.php` | JWT decode, crea User+Client direttamente, ritorna User |
| `modules/Auth/app/Http/Controllers/Api/V1/InvitationController.php` | `register()` ritorna access+refresh token |
| `modules/Auth/app/Http/Requests/RegisterViaInviteRequest.php` | +`firstName`, `lastName`, `dateOfBirth`, `gender`, `heightCm`, `objective`, `fitnessLevel` |
| `modules/Auth/routes/api.php` | Alias `accept-invite/{token}` → `InvitationController::register()` |

**Note:**
- Utente invitato salta verifica email (`email_verified_at = now()`) — l'invito è già verifica implicita
- `RegisterViaInviteAction` NON usa `CreateClientAction` (evita re-lookup per email): crea `Client` direttamente e chiama `AttachAthleteToCoachAction`
- Route esistente `POST /api/v1/auth/invite/{token}/register` rimane per backward compat
