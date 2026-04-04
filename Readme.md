# MioCoach — Gestionale Coach + Vetrina + AI
> Sviluppo: 7 Aprile 2026 → 20 Luglio 2026 
> Obiettivo al 20 Luglio è avere Saas operativo, sicuro, scalabile e testato in produzione

---

## 🧭 Descrizione del Prodotto

Piattaforma SaaS B2B e B2C composta da tre macro-prodotti:

1. GESTIONALE COACH — strumento operativo per istruttori e
   personal trainer (clienti, agenda, schede, chat, progressi,
   pagamenti, analytics)

2. PORTALE CLIENTE — area personale del cliente finale
   (dashboard, workout execution, progressi, prenotazioni,
   chat con il coach, abbonamento)

3. VETRINA PUBBLICA — profilo SEO-friendly del coach con
   prenotazione online h24, recensioni verificate e
   AI Booking Assistant conversazionale


## 🛠️ Stack Tecnico - Questa è una bozza

### Backend
| Componente       | Tecnologia                          |
|------------------|-------------------------------------|
| Runtime          | PHP 8.3+|
| Framework        | Laravel 13 con Filament |
| ORM              | Eloquent (incluso in Laravel)  |
| Database         | PostgreSQL 16 (RDS Aurora serverless) |
| Cache/Session    | Redis 7 (ElastiCache)               |
| Job Queue        | Laravel Horizon (basato su Redis)       |
| File Storage     | CloudFlare R2 + CloudFront CDN (via Laravel Flysystem) 
| Auth             | Laravel Sanctum (token API per Mobile + SPA/Next.js)|
| 2FA              | Laravel Fortify o pacchetto pragmarx/google2fa |
| AI               | OpenAI GPT-4o (openai-php/client + function calling)    
| Email            | SendGrid o Mailchimp       |
| SMS              | Twilio                              |
| Push             | Firebase Cloud Messaging            |
| Pagamenti        | Laravel Cashier (integrazione nativa Stripe)|
| Documenti PDF    | Spatie Browsershot (usa Puppeteer) o DomPDF(da verificare se serve)  |
| Geocoding        | Google Maps API (o Nominatim)       |
| WebSocket/Chat   | Stream Chat SDK (o Laravel Reverb per WebSockets nativi)  |
| Testing | Pest PHP o PHPUnit |

### Frontend Web
| Componente       | Tecnologia                          |
|------------------|-------------------------------------|
| Framework        | Next.js 15 (App Router) + React 19 + TypeScript               
| Build tool       | SWC / Turbopack (inclusi in Next.js)                              |
| Styling          | TailwindCSS 4 + shadcn/ui           |
| State/Query      | React Server Components (fetching SSR) + React Query / Zustand (stato client) |
| Form             | React Hook Form + Zod               |
| Grafici          | Recharts                            |
| DnD              | @dnd-kit/core (editor schede)       |
| Routing          | Next.js App Router (nativo)       |
| Testing          | Vitest / Jest + Testing Library + Playwright (per E2E)   |

### Mobile -> USARE FLUTTERRRR
| Componente       | Tecnologia                          |
|------------------|-------------------------------------|
| Framework        | Flutter
| Build            | EAS Build (managed workflow)        |
| Navigazione      | React Navigation 7                  |
| State            | Zustand + React Query               |
| Storage sicuro   | expo-secure-store (mai AsyncStorage |
|                  | per dati sensibili)                 |
| Grafici          | Victory Native                      |
| Chat             | Stream Chat React Native SDK        |
| Testing          | Jest + Detox (E2E su device reali)  |

### Infrastruttura
| Componente       | Tecnologia                          |
|------------------|-------------------------------------|
| Cloud            | AWS (Region: eu-south-1 Milano)     |
| Container        | Docker + Docker Compose (dev)       |
| Orchestrazione   | ECS Fargate (staging + prod)        |
| IaC              | Terraform (tutti gli ambienti)      |
| CI/CD            | GitHub Actions                      |
| CDN / WAF        | CloudFront + AWS WAF                |
| DDoS             | AWS Shield Standard                 |
| DNS              | Route 53                            |
| SSL/TLS          | AWS ACM (TLS 1.3 obbligatorio)      |
| Secrets          | AWS Secrets Manager                 |
| Monitoring       | Posthog o Datadog? (APM + infra + logs)        |
| Error tracking   | Sentry (frontend + backend)         |
| Uptime           | Better Uptime (status page)         |

---

## 🔐 Modello di Sicurezza

Questo è fondamentale: la sicurezza NON è una fase finale ma è integrata in ogni task che facciamo.
Di seguito le misure implementate per layer.

### Layer 1 — Autenticazione e Identità
- JWT asimmetrico (RS256): chiave privata in Secrets Manager,
  access_token TTL 15 min, refresh_token TTL 30 giorni
- Refresh token rotation: ogni refresh emette nuovo token e
  invalida il precedente (blacklist su Redis)
- 2FA TOTP opzionale per coach, obbligatorio per admin
- Rate limiting su auth: max 5 tentativi login per IP ogni
  15 minuti → blocco temporaneo + alert
- Account lockout: dopo 10 tentativi falliti in 1h →
  blocco account + email notifica al proprietario
- Email verification obbligatoria prima del primo login
- Password policy: min 8 chars, 1 maiuscola, 1 numero,
  1 carattere speciale
- Sessioni web: refresh_token in httpOnly + Secure cookie,
  SameSite=Strict. Mai localStorage per token.
- Token revocation: logout invalida immediatamente il
  refresh token su Redis

### Layer 2 — Sicurezza API
- Helmet.js: tutti gli header di sicurezza HTTP abilitati
  (CSP, HSTS max-age 1 anno, X-Frame-Options DENY,
  X-Content-Type-Options nosniff, Referrer-Policy)
- CORS: whitelist esplicita dei domini consentiti,
  no wildcard in produzione
- Rate limiting globale: 100 req/min per IP (express-rate-limit)
  Rate limiting per endpoint sensibili: upload = 10/min,
  prenotazione pubblica = 20/min, AI chat = 30/min
- Input validation: Zod su tutti gli endpoint (request body,
  query params, path params). Rifiuta richieste malformate con 422.
- Sanitization: DOMPurify lato client, escape SQL via Prisma
  (zero raw queries senza parametrizzazione)
- Dimensione request: limite 10MB (50MB per endpoint upload media)
- CSRF: token CSRF per tutte le mutation da browser web
  (double submit cookie pattern)
- SQL Injection: impossibile via Prisma ORM con typed queries.
  Zero query string concatenation.
- Path traversal: validazione path su tutti gli endpoint S3

### Layer 3 — Sicurezza Dati
- Encryption at rest: RDS con AES-256 (abilitato di default su Aurora), S3 SSE-S3 su tutti i bucket
- Encryption in transit: TLS 1.3 obbligatorio su tutte le
  connessioni (rifiuta TLS 1.0 e 1.1)
- Cifratura applicativa: i campi ultra-sensibili (anamnesi,
  note mediche, documenti clinici) sono cifrati a livello
  applicativo con AES-256-GCM prima di essere salvati su DB.
  La chiave è in Secrets Manager, ruotata ogni 90 giorni.
- PII minimization: non raccogliere dati che non servono.
  Codice fiscale mai richiesto. Cartella clinica è campo
  testo libero, non strutturato.
- Pseudonimizzazione nei log: i log applicativi non contengono
  email o nomi in chiaro — solo user_id (UUID).
- Audit log immutabile: ogni azione sensibile scrive su tabella
  `audit_logs` (append-only, nessun UPDATE/DELETE permesso).
  Azioni logate: login, logout, accesso dati cliente,
  modifica dati, pagamento, export dati, eliminazione account.
  Retention audit log: 24 mesi (obbligo GDPR art. 30).

### Layer 4 — GDPR Compliance
- Privacy by design: consent esplicito durante registrazione
- Right to Access (art. 15): endpoint GET /users/me/export →
  genera ZIP con tutti i dati in JSON entro 24h (job asincrono)
- Right to Erasure (art. 17): endpoint DELETE /users/me →
  soft delete immediato (accesso bloccato), hard delete dopo
  30 giorni (cron job), dati di fatturazione conservati 10 anni
  per obbligo fiscale
- Right to Portability (art. 20): export dati in formato JSON
  machine-readable
- Data retention policy: dati inattivi eliminati dopo 24 mesi
  di inattività (avviso email 30 giorni prima)
- DPA (Data Processing Agreement): il coach firma DPA con
  la piattaforma al momento della registrazione
- Cookie policy: banner consenso (IAB TCF 2.2 compliant),
  solo cookie tecnici senza consenso
- Registro trattamenti (art. 30): documentato e aggiornato

### Layer 5 — Sicurezza Infrastruttura
- VPC: subnet private per RDS e ElastiCache (zero accesso
  diretto da internet), subnet pubbliche solo per ALB
- Security Groups: principio del minimo privilegio.
  RDS accetta connessioni SOLO dalle ECS task.
  Redis idem.
- Secrets: ZERO variabili d'ambiente in chiaro.
  Tutto in AWS Secrets Manager. I container ricevono
  i secrets via IAM Role (no credenziali hardcoded).
- IAM: principio del minimo privilegio per tutti i ruoli.
  Nessun role con AdministratorAccess in produzione.
- Container security: immagini Docker basate su alpine,
  scansione vulnerabilità con Trivy in CI prima di ogni deploy
- Dependency scanning: Dependabot + npm audit in CI →
  blocca deploy se vulnerabilità CRITICAL o HIGH non patchate
- WAF Rules: blocca SQL injection, XSS, LFI, RCE patterns,
  scanner automatici, bad bots. Rate limiting a livello WAF.
- AWS Shield Standard: DDoS protection automatica inclusa

### Layer 6 — Sicurezza Mobile
- Certificate pinning: le chiamate API accettano SOLO il
  certificato del nostro server (implementato con
  react-native-ssl-pinning)
- Storage: tutti i token in expo-secure-store (Keychain iOS,
  Keystore Android). Mai AsyncStorage per dati sensibili.
- Jailbreak/Root detection: expo-device + libreria custom →
  avviso utente, sessione invalidata
- Biometria: Face ID / Fingerprint per sblocco app dopo
  timeout inattività (5 minuti configurabile)
- Session timeout: access_token refresh automatico, ma dopo
  30 giorni di inattività l'utente deve ri-autenticarsi
- Obfuscazione: Metro bundler + hermes engine in produzione,
  ProGuard abilitato per Android
- Network: pinning del certificato, intercept di chiamate
  non autorizzate bloccato in produzione

### Layer 7 — Security Testing & Monitoring
- OWASP Top 10 checklist verificata prima del launch
  (revisione manuale da @Dev1 + @PM)
- Penetration test base: eseguito con OWASP ZAP in CI
  (automated scan) + test manuale pre-launch
- Sentry: cattura tutti gli errori non gestiti con contesto
  (URL, user_id anonimizzato, stack trace), alert Slack
  se error rate > 1% in 5 minuti
- Datadog: APM tracing su tutte le richieste API, alert su
  latenza P99 > 2 secondi, CPU > 80%, Memory > 85%
- Failed login monitoring: se stesso IP ha > 5 failed login
  in 10 minuti → alert immediato a @Dev1 e @PM
- Audit log review: @PM fa review settimanale degli audit log
  per pattern anomali

---

## 💾 Piano di Backup e Disaster Recovery

### Database PostgreSQL (Aurora Serverless)

| Tipo backup         | Frequenza         | Retention   | Storage     |
|---------------------|-------------------|-------------|-------------|
| Snapshot automatico | Ogni 6 ore        | 7 giorni    | S3 (cifrato)|
| Backup giornaliero  | 02:00 UTC         | 30 giorni   | S3 (cifrato)|
| Backup settimanale  | Domenica 03:00 UTC| 12 settimane| S3 (cifrato)|
| Backup mensile      | 1° del mese 04:00 | 12 mesi     | S3 Glacier  |
| Point-in-time rec.  | Continuo (WAL)    | 35 giorni   | Aurora built-in|

- Cross-region replication: backup replicati in eu-west-1
  (Irlanda) in aggiunta a eu-south-1 (Milano)
- Test restore: ogni primo lunedì del mese, restore automatico
  dell'ultimo backup su ambiente isolato di test, verifica
  integrità, risultato loggato. @Dev1 riceve alert.
- RPO (Recovery Point Objective): max 6 ore di dati
- RTO (Recovery Time Objective): max 2 ore per restore completo
- Failover: Aurora Multi-AZ abilitato in produzione →
  failover automatico in < 30 secondi in caso di failure AZ

Popolerà @Dario il calendario con tutto in modo da sapere ogni giorno cosa fare, verrà popolato google calendar email dev (che troverete alla fine)

### File Storage Cloudflare R2

| Bucket              | Configurazione                                |
|---------------------|-----------------------------------------------|
| Tutti i bucket prod | Versioning abilitato, MFA Delete abilitato    |
| Foto/Video utenti   | Lifecycle: versioni vecchie → Glacier 30gg    |
| Documenti medici    | Lifecycle: Glacier dopo 90gg, delete dopo 7aa |
| Backup DB           | Lifecycle: Standard-IA dopo 30gg, Glacier 90gg|
| Cross-region        | Replication automatica eu-south-1 → eu-west-1 |

### Redis (ElastiCache)

- Snapshot RDB ogni 15 minuti (durabilità sessioni/cache)
- AOF (Append Only File) abilitato: ogni write è persistente
- Multi-AZ con replica automatica: failover < 60 secondi
- Redis non contiene dati primari (solo cache/sessioni) →
  perdita totale Redis = degraded performance, non data loss

### Disaster Recovery Plan

Scenario 1 — Failure singola AZ:
→ Aurora + ElastiCache failover automatico < 60s
→ ECS Fargate riprende su AZ diversa automaticamente
→ Zero downtime (o < 30s)

Scenario 2 — Failure region completa eu-south-1:
→ Restore manuale da backup cross-region in eu-west-1
→ RTO stimato: 2-4 ore
→ RPO: max 6 ore di dati
→ Runbook documentato in Notion (link in questo README)

Scenario 3 — Breach di sicurezza / dati corrotti:
→ Isolamento immediato (blocco Security Group)
→ Notifica @PM e @Dev1 entro 5 minuti (alert automatico)
→ Point-in-time recovery al timestamp pre-breach
→ Notifica agli utenti entro 72h (obbligo GDPR art. 33)

Scenario 4 — Cancellazione accidentale dati produzione:
→ S3 versioning permette restore immediato file
→ PostgreSQL PITR (Point-In-Time Recovery) per il DB
→ RTO: 30-60 minuti

---

## 🔀 Gitflow

main ──────────────────────────────────────► produzione
│ └── develop ────────────────────────────► staging (deploy auto)
│
├── feature/TASK-01-setup-infra ► ogni task Asana
├── feature/TASK-07-auth-jwt
├── feature/TASK-15-ai-progression
│
└── hotfix/TASK-XX-descrizione ► fix urgenti su main

Branch naming: `feature/TASK-{id}-{slug-breve}`
Nessun commit diretto su develop o main.
Ogni PR: title con task Asana, descrizione con "cosa fa"
e "come testarlo", screenshot se UI.

## ✅ Definition of Done

Un task è Completato su Asana SOLO quando:
- [ ] Codice in PR mergiata su develop
- [ ] Test unitari scritti per la logica di business
  (coverage minimo 70% sul file modificato)
- [ ] Nessun console.log, TODO, o debugger nel codice
- [ ] Endpoint documentato su Swagger (BE) o
  componente su Storybook (FE) — opzionale per MVP, ma
  almeno il tipo TypeScript è definito
- [ ] Input validation e gestione errori implementata
- [ ] Nessuna vulnerabilità CRITICAL/HIGH introdotta
  (npm audit verde in CI)
