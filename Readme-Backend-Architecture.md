# MioCoach — Architettura Tecnica Completa

> Documento tecnico complementare al README principale.
> Definisce le scelte architetturali per Backend (Laravel 13 + Filament), Frontend Web (Next.js 15), API e App Mobile (Flutter).

---

## 1. Architettura Generale

```
┌─────────────────────────────────────────────┐
│                  Laravel API                 │
│         (Sanctum, Eloquent, Services)        │
├──────────┬──────────────┬───────────────────┤
│          │              │                   │
▼          ▼              ▼                   ▼
Filament   Next.js        Next.js            Flutter
Admin      Coach SaaS     Portale Cliente    App Mobile
(interno)  + Vetrina      (web)              (Coach + Cliente)
```

### Principi

- **Un solo backend Laravel** espone API REST JSON per tutti i consumer
- **Filament** = pannello admin interno (gestione piattaforma da parte del team)
- **Next.js** = unico codebase web per coach SaaS, portale cliente e vetrina pubblica
- **Flutter** = unica app mobile per coach e cliente (esperienza diversa per ruolo)
- Pattern **MVC** con layer **Services** per la business logic complessa

---

## 2. Pattern MVC + Services

| Layer | Responsabilita | Posizione |
|-------|---------------|-----------|
| **Model** | Entita, relazioni Eloquent, cast, scopes | `app/Models/` |
| **View (Admin)** | Pannello admin interno | Filament Resources/Pages/Widgets |
| **View (API)** | Risposte JSON per Next.js e Flutter | `app/Http/Resources/` |
| **Controller** | Validazione, orchestrazione, response | `app/Http/Controllers/Api/V1/` |
| **Services** | Business logic pesante (pagamenti, AI, GDPR export) | `app/Services/` |

> I controller restano sottili: validano input (Form Request), chiamano il Service, ritornano la Resource JSON. La logica di business vive nei Services.

---

## 3. Modelli Eloquent — Entita Principali

```
User (base, con ruoli: admin, coach, client)
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
| User → AuditLogs | `hasMany` (polymorphic via `morphMany` se necessario) |

---

## 4. Struttura Cartelle Laravel

```
app/
├── Models/                    # Eloquent models
├── Http/
│   ├── Controllers/
│   │   └── Api/V1/            # API controllers (Next.js, Flutter)
│   │       ├── AuthController.php
│   │       ├── CoachController.php
│   │       ├── ClientController.php
│   │       ├── WorkoutPlanController.php
│   │       ├── BookingController.php
│   │       ├── ProgressController.php
│   │       ├── PaymentController.php
│   │       └── ReviewController.php
│   ├── Requests/              # Form Request validation
│   ├── Resources/             # API Resource transformers (JSON output)
│   └── Middleware/             # Rate limiting, tenant scoping, role check
├── Filament/
│   ├── Resources/             # CRUD panels admin (UserResource, CoachResource...)
│   ├── Pages/                 # Dashboard admin, Analytics piattaforma
│   └── Widgets/               # Stats cards, grafici revenue, metriche
├── Services/                  # Business logic
│   ├── PaymentService.php
│   ├── AIService.php
│   ├── GDPRExportService.php
│   ├── BookingService.php
│   └── NotificationService.php
├── Policies/                  # Authorization (chi puo fare cosa)
├── Observers/                 # Side effects su model events
├── Jobs/                      # Async jobs (export, notifiche, AI)
├── Notifications/             # Email, SMS (Twilio), Push (FCM) templates
└── Enums/                     # Status, ruoli, tipi
```

---

## 5. Filament — Pannello Admin Interno

Filament e riservato **esclusivamente al team interno** per la gestione operativa della piattaforma. I coach NON usano Filament.

### Cosa gestisce il pannello admin

| Sezione | Funzionalita |
|---------|-------------|
| **Utenti** | Lista coach e clienti, ban, reset password, verifica email |
| **Abbonamenti** | Stato subscription Stripe, rimborsi, dispute |
| **Pagamenti** | Transazioni, fatturato, export contabile |
| **Recensioni** | Moderazione recensioni, segnalazioni |
| **Audit Log** | Consultazione log immutabili (GDPR art. 30) |
| **Dashboard** | Revenue totale, coach attivi, churn rate, nuove iscrizioni |
| **Supporto** | Interventi manuali, escalation |

### Perche i coach NON usano Filament

| Aspetto | Filament | Next.js (coach SaaS) |
|---------|----------|---------------------|
| UX/UI | Look da backoffice, difficile da customizzare | Design premium, esperienza SaaS moderna |
| Branding | Interfaccia generica | Coerente col brand MioCoach |
| Interazioni | Round-trip server per ogni azione (Livewire) | Drag & drop schede, editor fluido, real-time |
| Performance | Ogni click va al server | SPA/SSR, navigazione istantanea |
| Mobile | Funziona ma non e mobile-first | Ottimizzato per ogni breakpoint |
| Codice condiviso | Zero condivisione con portale cliente | Coach e cliente condividono componenti e design system |

> Il coach e il cliente pagante. La sua esperienza deve essere premium, non un pannello di amministrazione.

---

## 6. API Layer

### Autenticazione (Sanctum)

| Consumer | Metodo Auth | Dettaglio |
|----------|------------|-----------|
| **Next.js (SPA)** | Cookie-based (Sanctum SPA) | `httpOnly`, `Secure`, `SameSite=Strict` |
| **Flutter (Mobile)** | Token-based (Bearer) | Token salvato in secure storage (Keychain/Keystore) |
| **Filament (Admin)** | Session Laravel standard | Accesso diretto a Eloquent, non passa dalle API |

#### Flusso mobile (Flutter)

```
POST /api/v1/auth/login → { access_token, user, role }
Flutter salva token in secure storage
Ogni request: Authorization: Bearer {token}
POST /api/v1/auth/refresh → nuovo token
POST /api/v1/auth/logout → invalida token
```

### Struttura endpoint API

```
/api/v1/auth/login
/api/v1/auth/register
/api/v1/auth/refresh
/api/v1/auth/logout
/api/v1/auth/verify-email
/api/v1/auth/forgot-password
/api/v1/auth/reset-password

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
/api/v1/coach/profile             (vetrina pubblica)
/api/v1/coach/reviews

/api/v1/client/dashboard
/api/v1/client/workout-plans
/api/v1/client/workout-plans/{id}/execute
/api/v1/client/progress
/api/v1/client/bookings
/api/v1/client/subscription

/api/v1/public/coaches             (vetrina: lista coach)
/api/v1/public/coaches/{slug}      (vetrina: profilo singolo)
/api/v1/public/coaches/{slug}/book (prenotazione pubblica)
/api/v1/public/coaches/{slug}/reviews

/api/v1/chat/conversations
/api/v1/chat/conversations/{id}/messages

/api/v1/payments/webhook           (Stripe webhook)

/api/v1/ai/booking-assistant       (AI conversazionale)
/api/v1/ai/workout-suggestion      (suggerimento schede)
```

### Convenzioni API

- Tutte le risposte wrappate in `{ "data": ..., "meta": ... }`
- Errori: `{ "message": "...", "errors": { "field": ["..."] } }` con status HTTP corretto
- Paginazione: cursor-based per liste lunghe, page-based per admin
- Versionamento: prefisso `/api/v1/` — mai breaking changes senza nuova versione
- Ogni endpoint ha un Form Request dedicato per la validazione

---

## 7. Pacchetti Laravel

| Pacchetto | Scopo |
|-----------|-------|
| `laravel/sanctum` | Auth API (token + SPA cookie) |
| `laravel/cashier-stripe` | Abbonamenti e pagamenti Stripe |
| `laravel/horizon` | Dashboard e monitoring job queue Redis |
| `laravel/reverb` | WebSocket nativi (alternativa a Stream Chat) |
| `laravel/fortify` | 2FA TOTP |
| `laravel/scout` | Full-text search (esercizi, clienti) |
| `spatie/laravel-permission` | Ruoli e permessi (`admin`, `coach`, `client`) |
| `spatie/laravel-medialibrary` | Upload e gestione media (foto, video, documenti) |
| `spatie/laravel-activitylog` | Audit log immutabile (GDPR) |
| `spatie/laravel-backup` | Backup DB + files schedulato |
| `barryvdh/laravel-dompdf` | Generazione PDF (schede, fatture) |
| `openai-php/laravel` | Integrazione GPT-4o (AI Assistant) |
| `propaganistas/laravel-phone` | Validazione numeri telefono (Twilio) |

---

## 8. Sicurezza — Implementazione Laravel

| Requisito (dal README principale) | Implementazione Laravel |
|----------------------------------|------------------------|
| JWT / Token auth | Sanctum token-based (mobile) + cookie SPA (web) |
| Refresh token rotation | Custom middleware + blacklist su Redis |
| Rate limiting auth (5 tentativi/15 min) | `RateLimiter::for('login', ...)` in `AppServiceProvider` |
| Rate limiting globale (100 req/min) | Middleware `throttle:100,1` |
| Input validation | Form Requests con rules strict su ogni endpoint |
| CSRF | Middleware `VerifyCsrfToken` (nativo Laravel, solo web) |
| Audit log immutabile | `spatie/laravel-activitylog` + tabella append-only |
| Encryption campi sensibili | `$casts = ['anamnesi' => 'encrypted']` su model |
| GDPR export (art. 15) | Job asincrono via Horizon → genera ZIP JSON |
| GDPR erasure (art. 17) | Soft delete immediato + hard delete schedulato (30gg) |
| Password policy | Regole validazione in `RegisterRequest` |
| Account lockout | Cache Redis con contatore tentativi per user |
| CORS whitelist | `config/cors.php` con domini espliciti |
| Security headers | Middleware custom (CSP, HSTS, X-Frame-Options) |

---

## 9. App Mobile Flutter — Una Sola App, Due Esperienze

### Perche una sola app

- Coach e cliente condividono l'80% delle funzionalita (auth, chat, notifiche, prenotazioni, profilo)
- Due app = doppio codebase, doppi bug, doppi rilasci, due listing sugli store
- Con una sola app, dopo il login il router mostra l'esperienza giusta in base al ruolo

### Cosa condividono coach e cliente

- Autenticazione (login, 2FA, recupero password)
- Chat (stessa UI, ruoli diversi)
- Notifiche push (stessa infra FCM)
- Profilo e impostazioni
- Prenotazioni (coach le gestisce, cliente le prenota)
- Networking layer, error handling, secure storage

### Cosa cambia per ruolo

| Sezione | Coach | Cliente |
|---------|-------|---------|
| **Home** | Dashboard operativa (clienti oggi, revenue) | Dashboard personale (prossimo allenamento, progressi) |
| **Schede** | Crea e modifica schede (editor) | Visualizza ed esegui scheda (workout execution) |
| **Clienti** | Lista clienti, progressi, anamnesi | Non presente |
| **Agenda** | Gestisce disponibilita e slot | Prenota slot disponibili |
| **Analytics** | Metriche business (retention, fatturato) | Progressi personali (peso, misure, PR) |
| **Vetrina** | Modifica profilo pubblico | Non presente |

### Struttura cartelle Flutter

```
lib/
├── core/                      # Condiviso
│   ├── auth/                  # Login, 2FA, token management
│   ├── networking/            # API client, interceptors, error handling
│   ├── theme/                 # Design system, colori, tipografia
│   ├── widgets/               # Componenti UI condivisi
│   └── storage/               # Secure storage wrapper
├── features/
│   ├── auth/                  # Schermate login/register (condiviso)
│   ├── chat/                  # Chat coach-cliente (condiviso)
│   ├── bookings/              # Prenotazioni (UI diversa per ruolo)
│   ├── notifications/         # Centro notifiche (condiviso)
│   ├── profile/               # Profilo e settings (condiviso)
│   ├── coach/                 # Solo coach
│   │   ├── dashboard/
│   │   ├── clients/
│   │   ├── workout_editor/
│   │   ├── availability/
│   │   ├── analytics/
│   │   └── public_profile/
│   └── client/                # Solo cliente
│       ├── dashboard/
│       ├── workout_execution/
│       ├── progress/
│       └── subscription/
└── routing/                   # Route guard basato su ruolo utente
```

### Routing per ruolo

Dopo il login, l'API ritorna il ruolo dell'utente. Il router Flutter usa un guard:

```dart
// Pseudocodice
if (user.role == 'coach') → navigazione coach (dashboard coach, clienti, editor...)
if (user.role == 'client') → navigazione client (dashboard client, workout, progressi...)
```

Una sola app, un solo codebase, un solo listing per store, due esperienze distinte.

---

## 10. Frontend Web — Next.js 15 (App Router)

### Un solo codebase, tre aree

Next.js 15 con App Router gestisce **tre macro-aree** in un unico progetto, separate tramite route groups e layout:

| Area | Utente | Rendering | Auth |
|------|--------|-----------|------|
| **Coach SaaS** | Coach autenticato | CSR/SSR (App Router) | Sanctum cookie SPA |
| **Portale Cliente** | Cliente autenticato | CSR/SSR (App Router) | Sanctum cookie SPA |
| **Vetrina Pubblica** | Visitatore anonimo | SSR/SSG (SEO) | Nessuna (pubblica) |

### Stack Frontend (dal README principale)

| Componente | Tecnologia | Uso nel progetto |
|------------|-----------|-----------------|
| Framework | Next.js 15 (App Router) + React 19 + TypeScript | Struttura applicativa |
| Styling | TailwindCSS 4 + shadcn/ui | Design system consistente, componenti accessibili |
| State/Query | React Server Components (fetch SSR) + React Query (stato server client-side) + Zustand (stato UI) | Fetching dati e stato globale |
| Form | React Hook Form + Zod | Validazione form client-side (specchia le Form Request Laravel) |
| Grafici | Recharts | Dashboard analytics coach, progressi cliente |
| Drag & Drop | @dnd-kit/core | Editor schede allenamento (ordine esercizi, serie) |
| Testing | Vitest + Testing Library + Playwright (E2E) | Unit, integration, end-to-end |

### Struttura cartelle Next.js

```
src/
├── app/
│   ├── (public)/                  # Vetrina pubblica (SSR/SSG, SEO)
│   │   ├── page.tsx               # Homepage piattaforma
│   │   ├── coaches/
│   │   │   ├── page.tsx           # Lista coach (SSG con revalidate)
│   │   │   └── [slug]/
│   │   │       ├── page.tsx       # Profilo coach pubblico (SSG)
│   │   │       ├── book/page.tsx  # Prenotazione pubblica
│   │   │       └── reviews/page.tsx
│   │   └── layout.tsx             # Layout pubblico (navbar, footer)
│   │
│   ├── (auth)/                    # Schermate auth (login, register, reset)
│   │   ├── login/page.tsx
│   │   ├── register/page.tsx
│   │   ├── forgot-password/page.tsx
│   │   └── layout.tsx             # Layout minimale auth
│   │
│   ├── (coach)/                   # Area coach autenticato
│   │   ├── dashboard/page.tsx     # Dashboard operativa
│   │   ├── clients/
│   │   │   ├── page.tsx           # Lista clienti
│   │   │   └── [id]/
│   │   │       ├── page.tsx       # Dettaglio cliente
│   │   │       └── progress/page.tsx
│   │   ├── workout-plans/
│   │   │   ├── page.tsx           # Lista schede
│   │   │   └── [id]/
│   │   │       └── editor/page.tsx # Editor drag & drop
│   │   ├── bookings/page.tsx      # Agenda prenotazioni
│   │   ├── availability/page.tsx  # Gestione slot
│   │   ├── analytics/page.tsx     # Metriche business
│   │   ├── chat/page.tsx          # Chat con clienti
│   │   ├── profile/page.tsx       # Modifica vetrina pubblica
│   │   ├── settings/page.tsx      # Impostazioni account
│   │   └── layout.tsx             # Layout coach (sidebar, topbar)
│   │
│   ├── (client)/                  # Area cliente autenticato
│   │   ├── dashboard/page.tsx     # Dashboard personale
│   │   ├── workout/
│   │   │   ├── page.tsx           # Schede assegnate
│   │   │   └── [id]/
│   │   │       └── execute/page.tsx # Esecuzione workout
│   │   ├── progress/page.tsx      # Progressi e misurazioni
│   │   ├── bookings/page.tsx      # Prenotazioni
│   │   ├── chat/page.tsx          # Chat col coach
│   │   ├── subscription/page.tsx  # Gestione abbonamento
│   │   ├── settings/page.tsx      # Impostazioni account
│   │   └── layout.tsx             # Layout cliente (sidebar, topbar)
│   │
│   └── api/                       # Route handlers Next.js (se servono proxy)
│
├── components/
│   ├── ui/                        # shadcn/ui components (Button, Input, Dialog...)
│   ├── shared/                    # Componenti condivisi tra coach e cliente
│   │   ├── ChatWindow.tsx
│   │   ├── BookingCalendar.tsx
│   │   ├── ProgressChart.tsx
│   │   └── NotificationCenter.tsx
│   ├── coach/                     # Componenti specifici coach
│   │   ├── ClientCard.tsx
│   │   ├── WorkoutEditor.tsx
│   │   ├── RevenueChart.tsx
│   │   └── AvailabilityGrid.tsx
│   └── client/                    # Componenti specifici cliente
│       ├── WorkoutPlayer.tsx
│       ├── ExerciseTimer.tsx
│       └── ProgressForm.tsx
│
├── lib/
│   ├── api/                       # Client API verso Laravel
│   │   ├── client.ts              # Axios/fetch wrapper con Sanctum CSRF
│   │   ├── auth.ts                # Login, register, refresh, logout
│   │   ├── coach.ts               # Endpoint coach
│   │   ├── client.ts              # Endpoint cliente
│   │   └── public.ts              # Endpoint pubblici (vetrina)
│   ├── hooks/                     # Custom React hooks
│   │   ├── useAuth.ts
│   │   ├── useCoachDashboard.ts
│   │   └── useWorkout.ts
│   ├── stores/                    # Zustand stores (stato UI)
│   │   ├── authStore.ts
│   │   └── uiStore.ts
│   ├── validations/               # Schemi Zod (specchiano le Form Request Laravel)
│   │   ├── auth.ts
│   │   ├── workout.ts
│   │   └── booking.ts
│   └── utils/                     # Helpers generici
│
├── types/                         # TypeScript types/interfaces
│   ├── user.ts
│   ├── workout.ts
│   ├── booking.ts
│   └── api.ts                     # Tipi response API generici
│
└── middleware.ts                   # Next.js middleware (auth guard, redirect per ruolo)
```

### Rendering Strategy

| Pagina | Strategia | Motivo |
|--------|-----------|--------|
| Vetrina coach (`/coaches/[slug]`) | **SSG** con `revalidate` (ISR) | SEO, performance, contenuto semi-statico |
| Lista coach (`/coaches`) | **SSG** con revalidate ogni 60s | SEO, lista cambia poco |
| Dashboard coach/cliente | **CSR** con React Query | Dati real-time, no SEO necessario |
| Editor schede | **CSR** puro | Interazioni pesanti (drag & drop), no SSR |
| Chat | **CSR** + WebSocket | Real-time bidirezionale |
| Pagine auth | **SSR** | Redirect server-side se gia autenticato |

### Comunicazione con Laravel API

```
Next.js (browser) ←→ Laravel API
        │
        ├── Sanctum SPA: il browser invia cookie httpOnly
        ├── CSRF: GET /sanctum/csrf-cookie prima delle mutation
        ├── React Query: cache, retry, optimistic updates
        └── Zod: validazione client-side prima di inviare (stesse regole del server)
```

- **React Query** gestisce tutto il data fetching client-side: cache automatica, refetch on focus, optimistic updates
- **React Server Components** per il fetch iniziale SSR (vetrina, dati statici)
- **Zustand** solo per stato UI locale (sidebar aperta/chiusa, modal, filtri temporanei) — non per dati server

### Condivisione codice tra coach e cliente

Componenti come `ChatWindow`, `BookingCalendar`, `ProgressChart` e `NotificationCenter` sono condivisi. Ricevono il ruolo come prop o lo leggono dal context auth, e adattano la UI:

```tsx
// Esempio: BookingCalendar condiviso
<BookingCalendar
  role={user.role}        // "coach" | "client"
  // coach: mostra gestione slot + lista prenotazioni ricevute
  // client: mostra slot disponibili + bottone prenota
/>
```

### Middleware Next.js — Protezione route

```typescript
// middleware.ts — pseudocodice
if (path.startsWith('/coach') && user.role !== 'coach') → redirect /login
if (path.startsWith('/client') && user.role !== 'client') → redirect /login
if (path.startsWith('/login') && user.isAuthenticated) → redirect per ruolo
```

---

## 11. App Mobile Flutter — Dettaglio Completo

### Una sola app, due esperienze

Coach e cliente condividono l'80% delle funzionalita. Una sola app riduce:
- Codebase da mantenere (1 invece di 2)
- Bug da risolvere (fix una volta, vale per entrambi)
- Rilasci sugli store (1 listing App Store + 1 Google Play)
- Pipeline CI/CD (1 sola)

### Stack Mobile (dal README principale)

| Componente | Tecnologia | Uso |
|------------|-----------|-----|
| Framework | Flutter | Cross-platform iOS + Android |
| State management | Riverpod o Bloc | Gestione stato reattiva |
| Networking | Dio | HTTP client con interceptors (token refresh automatico) |
| Storage sicuro | flutter_secure_storage | Token in Keychain (iOS) / Keystore (Android) |
| Grafici | fl_chart | Progressi cliente, analytics coach |
| Chat | Stream Chat Flutter SDK (o custom con Reverb) | Chat real-time coach-cliente |
| Push | Firebase Cloud Messaging (FCM) | Notifiche push cross-platform |
| Testing | flutter_test + integration_test | Unit, widget, integration |

### Cosa condividono coach e cliente

- Autenticazione (login, 2FA, recupero password, biometria)
- Chat (stessa UI, ruoli diversi)
- Notifiche push (stessa infra FCM)
- Profilo e impostazioni
- Prenotazioni (coach le gestisce, cliente le prenota)
- Networking layer, error handling, secure storage, tema

### Cosa cambia per ruolo

| Sezione | Coach | Cliente |
|---------|-------|---------|
| **Home** | Dashboard operativa (clienti oggi, revenue) | Dashboard personale (prossimo allenamento, progressi) |
| **Schede** | Crea e modifica schede (editor) | Visualizza ed esegui scheda (workout execution) |
| **Clienti** | Lista clienti, progressi, anamnesi | Non presente |
| **Agenda** | Gestisce disponibilita e slot | Prenota slot disponibili |
| **Analytics** | Metriche business (retention, fatturato) | Progressi personali (peso, misure, PR) |
| **Vetrina** | Modifica profilo pubblico | Non presente |

### Struttura cartelle Flutter

```
lib/
├── core/                          # Condiviso tra coach e cliente
│   ├── api/                       # API client (Dio), interceptors, token refresh
│   │   ├── api_client.dart        # Dio instance configurata
│   │   ├── auth_interceptor.dart  # Inject Bearer token, refresh automatico
│   │   └── error_handler.dart     # Parsing errori API, mapping a UI
│   ├── auth/                      # Logica auth condivisa
│   │   ├── auth_provider.dart     # State auth (Riverpod/Bloc)
│   │   ├── secure_storage.dart    # Wrapper flutter_secure_storage
│   │   └── biometric_service.dart # Face ID / Fingerprint
│   ├── theme/                     # Design system
│   │   ├── app_theme.dart         # Colori, tipografia, spacing
│   │   └── app_colors.dart
│   ├── widgets/                   # Widget UI condivisi
│   │   ├── loading_indicator.dart
│   │   ├── error_view.dart
│   │   ├── avatar.dart
│   │   └── stat_card.dart
│   ├── models/                    # Data models condivisi (DTO)
│   │   ├── user.dart
│   │   ├── workout_plan.dart
│   │   ├── exercise.dart
│   │   ├── booking.dart
│   │   └── progress_log.dart
│   └── utils/                     # Helpers, formatters, costanti
│
├── features/
│   ├── auth/                      # Schermate auth (condiviso)
│   │   ├── screens/
│   │   │   ├── login_screen.dart
│   │   │   ├── register_screen.dart
│   │   │   ├── forgot_password_screen.dart
│   │   │   └── two_factor_screen.dart
│   │   └── widgets/
│   │
│   ├── chat/                      # Chat (condiviso, UI identica)
│   │   ├── screens/
│   │   │   ├── conversations_screen.dart
│   │   │   └── chat_screen.dart
│   │   └── widgets/
│   │       ├── message_bubble.dart
│   │       └── chat_input.dart
│   │
│   ├── bookings/                  # Prenotazioni (condiviso, UI diversa per ruolo)
│   │   ├── screens/
│   │   │   ├── bookings_screen.dart      # Lista (diversa per ruolo)
│   │   │   └── booking_detail_screen.dart
│   │   └── widgets/
│   │       ├── booking_card.dart
│   │       └── calendar_view.dart
│   │
│   ├── notifications/             # Centro notifiche (condiviso)
│   │   ├── screens/
│   │   │   └── notifications_screen.dart
│   │   └── services/
│   │       └── fcm_service.dart   # Setup FCM, handling foreground/background
│   │
│   ├── profile/                   # Profilo e settings (condiviso)
│   │   └── screens/
│   │       ├── profile_screen.dart
│   │       └── settings_screen.dart
│   │
│   ├── coach/                     # Funzionalita SOLO coach
│   │   ├── dashboard/
│   │   │   ├── screens/
│   │   │   │   └── coach_dashboard_screen.dart
│   │   │   └── widgets/
│   │   │       ├── today_clients_widget.dart
│   │   │       └── revenue_widget.dart
│   │   ├── clients/
│   │   │   ├── screens/
│   │   │   │   ├── clients_list_screen.dart
│   │   │   │   └── client_detail_screen.dart
│   │   │   └── widgets/
│   │   │       └── client_card.dart
│   │   ├── workout_editor/
│   │   │   ├── screens/
│   │   │   │   └── workout_editor_screen.dart
│   │   │   └── widgets/
│   │   │       ├── exercise_picker.dart
│   │   │       └── set_row.dart
│   │   ├── availability/
│   │   │   └── screens/
│   │   │       └── availability_screen.dart
│   │   ├── analytics/
│   │   │   └── screens/
│   │   │       └── coach_analytics_screen.dart
│   │   └── public_profile/
│   │       └── screens/
│   │           └── edit_public_profile_screen.dart
│   │
│   └── client/                    # Funzionalita SOLO cliente
│       ├── dashboard/
│       │   ├── screens/
│       │   │   └── client_dashboard_screen.dart
│       │   └── widgets/
│       │       ├── next_workout_widget.dart
│       │       └── progress_summary_widget.dart
│       ├── workout_execution/
│       │   ├── screens/
│       │   │   ├── workout_list_screen.dart
│       │   │   └── workout_execution_screen.dart
│       │   └── widgets/
│       │       ├── exercise_card.dart
│       │       ├── rest_timer.dart
│       │       └── set_logger.dart
│       ├── progress/
│       │   ├── screens/
│       │   │   └── progress_screen.dart
│       │   └── widgets/
│       │       ├── progress_chart.dart
│       │       └── measurement_form.dart
│       └── subscription/
│           └── screens/
│               └── subscription_screen.dart
│
└── routing/                       # Navigazione e route guard
    ├── app_router.dart            # Definizione route
    └── auth_guard.dart            # Redirect basato su ruolo
```

### Routing per ruolo

Dopo il login, l'API ritorna il ruolo dell'utente. Il router Flutter usa un guard:

```dart
// Pseudocodice
if (user.role == 'coach') → navigazione coach (dashboard coach, clienti, editor...)
if (user.role == 'client') → navigazione client (dashboard client, workout, progressi...)
```

### Sicurezza Mobile (dal README principale)

| Misura | Implementazione Flutter |
|--------|------------------------|
| Token storage | `flutter_secure_storage` (Keychain iOS, Keystore Android) |
| Certificate pinning | Dio + custom `SecurityContext` per accettare solo il certificato del server |
| Biometria | `local_auth` package (Face ID / Fingerprint) per sblocco dopo inattivita |
| Jailbreak/Root detection | `flutter_jailbreak_detection` → avviso utente, sessione invalidata |
| Session timeout | Refresh token automatico, ri-auth dopo 30gg inattivita |
| Obfuscazione | `flutter build --obfuscate --split-debug-info` in produzione |
| Network security | Dio interceptor blocca chiamate non HTTPS |

### Comunicazione con Laravel API

```
Flutter App ←→ Laravel API
     │
     ├── Dio HTTP client con interceptors
     ├── Bearer token in header Authorization
     ├── Token refresh automatico (interceptor 401 → refresh → retry)
     ├── Retry logic con backoff esponenziale
     └── Offline queue (opzionale): salva azioni offline, sincronizza al reconnect
```

---

## 12. Riepilogo Decisioni Architetturali

| Decisione | Scelta | Motivazione |
|-----------|--------|-------------|
| Pattern backend | MVC + Services | Controller sottili, logica nei Services |
| Admin panel | Filament (solo uso interno) | Veloce da sviluppare, CRUD gratis |
| Coach SaaS web | Next.js | UX premium, condivisione codice col portale cliente |
| Portale cliente web | Next.js (stesso codebase del coach) | Un solo codebase web, aree separate per ruolo |
| Vetrina pubblica | Next.js (SSR/SSG) | SEO-friendly, stesso codebase |
| App mobile | Flutter (una sola app) | Condivisione 80% funzionalita, un solo listing store |
| Auth mobile | Sanctum token-based | Token in secure storage, Bearer header |
| Auth web SPA | Sanctum cookie-based | httpOnly cookie, protezione CSRF nativa |
| API | REST JSON versionata (`/api/v1/`) | Standard, compatibile con tutti i consumer |
