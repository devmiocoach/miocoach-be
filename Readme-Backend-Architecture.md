# MioCoach — Architettura Tecnica Completa

> Documento tecnico complementare al README principale.
> Definisce le scelte architetturali per Backend (Laravel 13 + Filament v5), Frontend Web (Next.js 15), API e App Mobile (Flutter).

---

## 1. Architettura Generale

```
┌─────────────────────────────────────────────┐
│                  Laravel API                 │
│      (Sanctum, Eloquent, DDD Modulare)       │
├──────────┬──────────────┬───────────────────┤
│          │              │                   │
▼          ▼              ▼                   ▼
Filament   Next.js        Next.js            Flutter
Admin      Coach SaaS     Portale Cliente    App Mobile
(interno)  + Vetrina      (web)              (Coach + Cliente)
```

### Principi

- **Un solo backend Laravel** espone API REST JSON per tutti i consumer
- **Filament v5** = pannello admin interno (gestione piattaforma da parte del team)
- **Next.js** = unico codebase web per coach SaaS, portale cliente e vetrina pubblica
- **Flutter** = unica app mobile per coach e cliente (esperienza diversa per ruolo)
- Architettura **DDD Modulare** via `internachi/modular` — ogni dominio è isolato in `app/Modules/{Name}`
- **Actions** per logica intra-dominio, **Services** per logica cross-dominio

---

## 2. Pattern DDD Modulare + Actions + Services

| Layer | Responsabilita | Posizione |
|-------|---------------|-----------|
| **Model** | Entita, relazioni Eloquent, cast, scopes | `app/Modules/{Name}/Models/` |
| **Action** | Business logic intra-dominio (una Action = un caso d'uso) | `app/Modules/{Name}/Actions/` |
| **Service** | Business logic cross-dominio (coinvolge 2+ moduli) | `app/Services/` |
| **View (Admin)** | Pannello admin interno | `app/Modules/{Name}/Filament/` |
| **View (API)** | Risposte JSON per Next.js e Flutter | `app/Modules/{Name}/Http/Resources/` |
| **Controller** | Validazione input, chiama Action, ritorna Resource | `app/Modules/{Name}/Http/Controllers/Api/V1/` |

> I controller restano sottili: validano input (Form Request), chiamano l'Action, ritornano la Resource JSON.
> Un modulo non importa mai direttamente classi di un altro modulo — la comunicazione cross-dominio avviene tramite Services.

### Actions vs Services — Regola pratica

| Caso | Usa |
|------|-----|
| Login di un utente | `Auth\Actions\LoginAction` |
| Creazione workout plan | `WorkoutPlans\Actions\CreateWorkoutPlanAction` |
| Notifica al cliente dopo booking (coinvolge Bookings + Users + Chat) | `app/Services/NotificationService` |
| Export GDPR (coinvolge Users + Payments + Progress + Audit) | `app/Services/GDPRExportService` |

---

## 3. Modelli Eloquent — Entita Principali

```
User (base, con ruoli: admin, coach, client — gestiti da Shield + Spatie Permission)
├── Coach (profilo pubblico, vetrina, settings, disponibilita)
├── Client (legato a un coach, anamnesi, obiettivi)
├── Subscription (abbonamento Stripe via Laravel Cashier)
├── WorkoutPlan (scheda allenamento)
│   └── WorkoutExercise (pivot: esercizi nella scheda, serie, ripetizioni, ordine)
├── Exercise (catalogo esercizi globale + custom del coach)
├── Booking (prenotazioni: slot, stato, note)
├── ProgressLog (misurazioni, peso, foto, PR)
├── ChatMessage (o integrazione Stream Chat)
├── Payment / Invoice (via Laravel Cashier + Stripe)
├── Review (recensioni verificate, legate a client + coach)
└── AuditLog (log immutabile, append-only)
```

### Relazioni chiave

| Relazione | Tipo |
|-----------|------|
| User → Coach | `hasOne` |
| User → Client | `hasOne` |
| Coach → Clients | `hasMany` |
| Coach → WorkoutPlans | `hasMany` |
| WorkoutPlan → Exercises | `belongsToMany` (pivot: `workout_exercises`) |
| Client → ProgressLogs | `hasMany` |
| Client → Bookings | `hasMany` |
| Coach → Bookings | `hasMany` |
| Coach → Reviews | `hasMany` |
| User → AuditLogs | `hasMany` |

---

## 4. Struttura Modulare DDD

Ogni funzionalita risiede in un modulo autonomo sotto `app/Modules/`. La struttura interna di ogni modulo e identica:

```
app/Modules/{Name}/
  Models/
  Actions/                  # Casi d'uso intra-dominio
  Http/
    Controllers/Api/V1/
    Requests/
    Resources/
  Filament/
    Resources/
    Pages/
    Widgets/
  Providers/
  Database/
    Migrations/
```

### Moduli del progetto

```
app/Modules/
  Auth/             # Login, logout, register, 2FA (Fortify), reset password, verifica email
  Users/            # Profilo User, Coach, Client — ruoli e permessi (Shield)
  WorkoutPlans/     # Schede, esercizi, catalogo
  Bookings/         # Prenotazioni, disponibilita slot
  Payments/         # Stripe, abbonamenti, fatture, webhook
  Progress/         # ProgressLog, misurazioni, foto, PR
  Chat/             # Messaggi, conversazioni
  Reviews/          # Recensioni, moderazione
  Audit/            # AuditLog append-only (GDPR art. 30)
  AI/               # BookingAssistant, WorkoutSuggestion

app/Services/       # Solo Services cross-dominio
  NotificationService.php     # Coinvolge Users + Chat + Bookings
  GDPRExportService.php        # Coinvolge Users + Payments + Progress + Audit
```

---

## 5. Modulo Auth — Dettaglio

Il modulo Auth gestisce esclusivamente identita e accesso. Il profilo utente e i ruoli vivono nel modulo Users.

### Struttura

```
app/Modules/Auth/
  Actions/
    LoginAction.php               # Valida credenziali, emette token o cookie
    LogoutAction.php              # Revoca token / invalida sessione
    RegisterAction.php            # Crea User + emette token iniziale
    RefreshTokenAction.php        # Rotation refresh token (Redis blacklist)
    ForgotPasswordAction.php
    ResetPasswordAction.php
    VerifyEmailAction.php
    EnableTwoFactorAction.php     # TOTP via Fortify
    ConfirmTwoFactorAction.php
    DisableTwoFactorAction.php
    RevokeAllTokensAction.php     # Logout da tutti i device

  Http/
    Controllers/Api/V1/
      AuthController.php
      PasswordController.php
      EmailVerificationController.php
      TwoFactorController.php
    Requests/
      LoginRequest.php            # Valida email + password + rate limit
      RegisterRequest.php         # Password policy
      ResetPasswordRequest.php
      TwoFactorRequest.php
    Resources/
      AuthTokenResource.php       # { access_token, expires_in, user, role }

  Services/
    TokenBlacklistService.php     # Redis: blacklist refresh token
    RateLimiterService.php        # 5 tentativi/15 min per IP + email
    AccountLockoutService.php     # Contatore Redis, lockout automatico

  Notifications/
    ResetPasswordNotification.php
    EmailVerificationNotification.php
    NewDeviceLoginNotification.php
```

### Endpoints

```
POST   /api/v1/auth/login
POST   /api/v1/auth/register
POST   /api/v1/auth/logout
POST   /api/v1/auth/refresh
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
GET    /api/v1/auth/verify-email/{id}/{hash}
POST   /api/v1/auth/verify-email/resend
POST   /api/v1/auth/two-factor/enable
POST   /api/v1/auth/two-factor/confirm
DELETE /api/v1/auth/two-factor
POST   /api/v1/auth/logout-all
```

### Doppia strategia auth in un unico modulo

| Consumer | Strategia | Comportamento LoginAction |
|---|---|---|
| **Flutter (mobile)** | Bearer token (Sanctum) | Emette `PersonalAccessToken` |
| **Next.js (SPA)** | Cookie httpOnly (Sanctum SPA) | `Auth::attempt()` + sessione |

`LoginRequest` include `device_type: mobile|web` per differenziare il flusso.

---

## 6. 2FA — Strategia Duale

Il progetto usa due implementazioni distinte, una per contesto:

| Contesto | Implementazione | Motivo |
|---|---|---|
| **Filament admin panel** | Filament v5 built-in 2FA | Zero configurazione, UI inclusa, QR code e recovery codes pronti |
| **API (Flutter + Next.js)** | Laravel Fortify 2FA | Espone endpoint API, compatibile con tutti i consumer |

Le due implementazioni operano su flow di login separati e non si interferiscono.

> **Attenzione:** disabilitare la registrazione automatica delle route Fortify (`config/fortify.php`) per le route che Filament gia gestisce autonomamente.

---

## 7. Ruoli e Permessi — Filament Shield

Usiamo **Filament Shield** come layer UI sopra **Spatie Laravel Permission**:

- `spatie/laravel-permission` — motore sottostante (ruoli, permessi, tabelle DB)
- `bezhansalleh/filament-shield` — interfaccia Filament per gestire ruoli e policy senza toccare codice

### Ruoli applicativi

| Ruolo | Accesso |
|-------|---------|
| `admin` | Filament panel completo |
| `coach` | API coach, profilo, clienti, workout, agenda |
| `client` | API client, workout assegnati, progressi, prenotazioni |

### Isolamento dati per ruolo

Non usiamo multi-tenancy fisico (database separati). L'isolamento avviene tramite:

- **Eloquent Global Scopes** — ogni query e automaticamente filtrata per coach_id o client_id
- **Policies Laravel** — autorizzazione fine-grained (un coach vede solo i suoi clienti)
- **Shield Super Admin** — il team interno bypassa tutte le policy via Filament

---

## 8. Multi-Tenancy — Decisione: NO

MioCoach **non richiede multi-tenancy fisico**. Ecco perche:

| Criterio | MioCoach | Quando serve il multi-tenancy |
|---|---|---|
| Database separato per coach | No — tutti condividono lo stesso DB | SaaS con clienti enterprise, compliance separata |
| Schema separato per coach | No | Isolamento normativo (es. GDPR per giurisdizioni diverse) |
| Sottodominio per coach | No (slug pubblico: `/coaches/{slug}`) | White-label SaaS (`cliente.app.com`) |
| Volume dati per coach | Ridotto (decine di clienti) | Milioni di record per tenant |

L'isolamento e garantito a livello applicativo:
- `coach_id` su ogni risorsa (WorkoutPlan, Booking, ProgressLog...)
- Global Scopes su tutti i modelli che appartengono a un coach
- Policies che verificano l'ownership prima di ogni operazione

Usare `tenancy/tenancy` (stancl) o Hyn\Tenancy sarebbe overengineering e aggiungerebbe complessita operativa (migrazioni per tenant, connessioni dinamiche) senza benefici reali per questo caso d'uso.

---

## 9. Filament v5 — Pannello Admin Interno

Filament e riservato **esclusivamente al team interno**. I coach NON usano Filament.

### Discovery automatico delle risorse dai moduli

```php
// app/Providers/Filament/AdminPanelProvider.php
->discoverResources(in: app_path('Modules/*/Filament/Resources'), for: 'App\\Modules\\*\\Filament\\Resources')
->discoverPages(in: app_path('Modules/*/Filament/Pages'), for: 'App\\Modules\\*\\Filament\\Pages')
->discoverWidgets(in: app_path('Modules/*/Filament/Widgets'), for: 'App\\Modules\\*\\Filament\\Widgets')
```

### Cosa gestisce il pannello admin

| Sezione | Funzionalita |
|---------|-------------|
| **Utenti** | Lista coach e clienti, ban, reset password, verifica email |
| **Ruoli e Permessi** | Gestione via Shield (UI drag & drop su ruoli/policy) |
| **Abbonamenti** | Stato subscription Stripe, rimborsi, dispute |
| **Pagamenti** | Transazioni, fatturato, export contabile |
| **Recensioni** | Moderazione recensioni, segnalazioni |
| **Audit Log** | Consultazione log immutabili (GDPR art. 30) |
| **Dashboard** | Revenue totale, coach attivi, churn rate, nuove iscrizioni |
| **Supporto** | Interventi manuali, escalation |

### Perche i coach NON usano Filament

| Aspetto | Filament | Next.js (coach SaaS) |
|---------|----------|---------------------|
| UX/UI | Look da backoffice | Design premium, esperienza SaaS moderna |
| Branding | Interfaccia generica | Coerente col brand MioCoach |
| Interazioni | Round-trip server (Livewire) | Drag & drop, editor fluido, real-time |
| Mobile | Non mobile-first | Ottimizzato per ogni breakpoint |

---

## 10. API Layer

### Autenticazione (Sanctum)

| Consumer | Metodo Auth | Dettaglio |
|----------|------------|-----------|
| **Next.js (SPA)** | Cookie-based (Sanctum SPA) | `httpOnly`, `Secure`, `SameSite=Strict` |
| **Flutter (Mobile)** | Token-based (Bearer) | Token in secure storage (Keychain/Keystore) |
| **Filament (Admin)** | Session Laravel standard | Accesso diretto a Eloquent, non passa dalle API |

### Struttura endpoint API

```
/api/v1/auth/login
/api/v1/auth/register
/api/v1/auth/refresh
/api/v1/auth/logout
/api/v1/auth/logout-all
/api/v1/auth/verify-email
/api/v1/auth/forgot-password
/api/v1/auth/reset-password
/api/v1/auth/two-factor/enable
/api/v1/auth/two-factor/confirm

/api/v1/users/me
/api/v1/users/me/export          (GDPR art. 15)
DELETE /api/v1/users/me           (GDPR art. 17)

/api/v1/coach/dashboard
/api/v1/coach/clients
/api/v1/coach/clients/{id}
/api/v1/coach/clients/{id}/progress
/api/v1/coach/workout-plans
/api/v1/coach/workout-plans/{id}
/api/v1/coach/bookings
/api/v1/coach/availability
/api/v1/coach/analytics
/api/v1/coach/profile
/api/v1/coach/reviews

/api/v1/client/dashboard
/api/v1/client/workout-plans
/api/v1/client/workout-plans/{id}/execute
/api/v1/client/progress
/api/v1/client/bookings
/api/v1/client/subscription

/api/v1/public/coaches
/api/v1/public/coaches/{slug}
/api/v1/public/coaches/{slug}/book
/api/v1/public/coaches/{slug}/reviews

/api/v1/chat/conversations
/api/v1/chat/conversations/{id}/messages

/api/v1/payments/webhook           (Stripe webhook)

/api/v1/ai/booking-assistant
/api/v1/ai/workout-suggestion
```

### Convenzioni API

- Risposte wrappate in `{ "data": ..., "meta": ... }`
- Errori: `{ "message": "...", "errors": { "field": ["..."] } }` con status HTTP corretto
- Paginazione: cursor-based per liste lunghe, page-based per admin
- Versionamento: prefisso `/api/v1/` — mai breaking changes senza nuova versione
- Ogni endpoint ha un Form Request dedicato per la validazione

---

## 11. Pacchetti Laravel

| Pacchetto | Scopo |
|-----------|-------|
| `internachi/modular` | Architettura DDD modulare (`app/Modules/`) |
| `laravel/sanctum` | Auth API (token + SPA cookie) |
| `laravel/fortify` | 2FA TOTP per coach e clienti via API |
| `laravel/cashier-stripe` | Abbonamenti e pagamenti Stripe |
| `laravel/horizon` | Dashboard e monitoring job queue Redis |
| `laravel/reverb` | WebSocket nativi (chat real-time) |
| `laravel/scout` | Full-text search (esercizi, clienti) |
| `spatie/laravel-permission` | Motore ruoli e permessi (sottostante a Shield) |
| `bezhansalleh/filament-shield` | UI Filament per gestione ruoli e policy |
| `spatie/laravel-medialibrary` | Upload e gestione media (foto, video, documenti) |
| `spatie/laravel-activitylog` | Audit log immutabile (GDPR) |
| `spatie/laravel-backup` | Backup DB + files schedulato |
| `barryvdh/laravel-dompdf` | Generazione PDF (schede, fatture) |
| `openai-php/laravel` | Integrazione GPT-4o (AI Assistant) |
| `propaganistas/laravel-phone` | Validazione numeri telefono |

---

## 12. Sicurezza — Implementazione Laravel

| Requisito | Implementazione |
|-----------|----------------|
| Auth mobile | Sanctum token Bearer (secure storage) |
| Auth web SPA | Sanctum cookie httpOnly + CSRF |
| 2FA admin | Filament v5 built-in (TOTP, QR code, recovery codes) |
| 2FA coach/client | Laravel Fortify (endpoint API) |
| Refresh token rotation | `RefreshTokenAction` + blacklist Redis |
| Rate limiting auth | 5 tentativi/15 min — `RateLimiterService` |
| Rate limiting globale | Middleware `throttle:100,1` |
| Input validation | Form Requests su ogni endpoint |
| CSRF | Middleware `VerifyCsrfToken` (solo web) |
| Ruoli e permessi | Shield + Spatie Permission |
| Isolamento dati | Global Scopes + Policies (no multi-tenancy fisico) |
| Audit log immutabile | `spatie/laravel-activitylog` + tabella append-only |
| Encryption campi sensibili | `$casts = ['anamnesi' => 'encrypted']` |
| GDPR export (art. 15) | Job asincrono via Horizon → ZIP JSON |
| GDPR erasure (art. 17) | Soft delete immediato + hard delete schedulato (30gg) |
| Account lockout | `AccountLockoutService` Redis |
| CORS whitelist | `config/cors.php` con domini espliciti |
| Security headers | Middleware custom (CSP, HSTS, X-Frame-Options) |

---

## 13. App Mobile Flutter — Una Sola App, Due Esperienze

### Perche una sola app

- Coach e cliente condividono l'80% delle funzionalita
- Una sola app = un solo codebase, un solo listing store, una pipeline CI/CD

### Cosa cambia per ruolo

| Sezione | Coach | Cliente |
|---------|-------|---------|
| **Home** | Dashboard operativa (clienti oggi, revenue) | Dashboard personale (prossimo allenamento, progressi) |
| **Schede** | Crea e modifica schede (editor) | Visualizza ed esegui scheda |
| **Clienti** | Lista clienti, progressi, anamnesi | Non presente |
| **Agenda** | Gestisce disponibilita e slot | Prenota slot disponibili |
| **Analytics** | Metriche business | Progressi personali |

### Stack Mobile

| Componente | Tecnologia |
|------------|-----------|
| Framework | Flutter |
| State management | Riverpod o Bloc |
| Networking | Dio (interceptors, token refresh automatico) |
| Storage sicuro | flutter_secure_storage (Keychain/Keystore) |
| Grafici | fl_chart |
| Chat | Stream Chat Flutter SDK (o custom con Reverb) |
| Push | Firebase Cloud Messaging (FCM) |

### Struttura cartelle Flutter

```
lib/
├── core/                      # Condiviso
│   ├── api/                   # Dio client, interceptors, error handling
│   ├── auth/                  # State auth, secure storage, biometria
│   ├── theme/                 # Design system
│   ├── widgets/               # Componenti UI condivisi
│   ├── models/                # DTO condivisi
│   └── utils/
├── features/
│   ├── auth/                  # Login, register, 2FA, recupero password
│   ├── chat/                  # Chat coach-cliente
│   ├── bookings/              # Prenotazioni (UI diversa per ruolo)
│   ├── notifications/         # Centro notifiche + FCM
│   ├── profile/               # Profilo e settings
│   ├── coach/                 # Dashboard, clienti, editor schede, disponibilita, analytics
│   └── client/                # Dashboard, workout execution, progressi, subscription
└── routing/                   # Route guard basato su ruolo
```

---

## 14. Frontend Web — Next.js 15 (App Router)

### Un solo codebase, tre aree

| Area | Utente | Rendering | Auth |
|------|--------|-----------|------|
| **Coach SaaS** | Coach autenticato | CSR/SSR | Sanctum cookie SPA |
| **Portale Cliente** | Cliente autenticato | CSR/SSR | Sanctum cookie SPA |
| **Vetrina Pubblica** | Visitatore anonimo | SSR/SSG | Nessuna |

### Stack Frontend

| Componente | Tecnologia |
|------------|-----------|
| Framework | Next.js 15 (App Router) + React 19 + TypeScript |
| Styling | TailwindCSS 4 + shadcn/ui |
| State/Query | React Server Components + React Query + Zustand |
| Form | React Hook Form + Zod |
| Grafici | Recharts |
| Drag & Drop | @dnd-kit/core |
| Testing | Vitest + Testing Library + Playwright |

---

## 15. Riepilogo Decisioni Architetturali

| Decisione | Scelta | Motivazione |
|-----------|--------|-------------|
| Architettura backend | DDD Modulare (`internachi/modular`) | Separazione dominio, scalabilita, manutenibilita |
| Pattern logica | Actions (intra-dominio) + Services (cross-dominio) | Controller sottili, responsabilita chiare |
| Admin panel | Filament v5 (solo uso interno) | CRUD rapido, Shield per i permessi |
| Ruoli e permessi | Shield + Spatie Permission | UI Filament nativa, nessun codice custom |
| 2FA admin | Filament v5 built-in | Zero configurazione |
| 2FA coach/client | Laravel Fortify | Endpoint API per Flutter e Next.js |
| Multi-tenancy | No — row-level isolation | Global Scopes + Policies sufficienti, no overengineering |
| Coach SaaS web | Next.js | UX premium, condivisione codice col portale cliente |
| App mobile | Flutter (una sola app) | 80% funzionalita condivise, un solo listing store |
| Auth mobile | Sanctum token Bearer | Token in secure storage |
| Auth web SPA | Sanctum cookie httpOnly | Protezione CSRF nativa |
| API | REST JSON versionata `/api/v1/` | Compatibile con tutti i consumer |
