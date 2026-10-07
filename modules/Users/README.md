# Users Module — Stato Implementazione

---

## Tutti gli endpoint implementati

| Endpoint | Stato |
|---|---|
| `GET /api/v1/coaches/me` | ✅ Implementato |
| `PATCH /api/v1/coaches/me` | ✅ Implementato (geocoding, slug, audit) |
| `PUT /api/v1/coaches/me/publish` | ✅ Implementato |
| `PUT /api/v1/coaches/me/availability` | ✅ Implementato |
| `POST /api/v1/coaches/me/certifications` | ✅ Implementato (R2 upload, CDN URL) |
| `DELETE /api/v1/coaches/me/certifications/:id` | ✅ Implementato |
| `GET /api/v1/coaches/:id/public` | ✅ Implementato (SEO metadata, verified certs, next 5 slots) |
| `GET /api/v1/coaches/me/clients` | ⚠️ Parzialmente implementato (vedi gap sotto) |
| `GET /api/v1/users/me` | ✅ Implementato |
| `PATCH /api/v1/users/me` | ✅ Implementato |
| `DELETE /api/v1/users/me` | ✅ Implementato |
| `GET/POST/DELETE /api/v1/coaches/invitations` | ✅ Implementato |

---

## Endpoint fuori scope (altri moduli → vedere MODULES_TODO.md)

| Endpoint | Modulo |
|---|---|
| `POST/PUT/DELETE /coaches/me/packages` | Billing |
| `POST/PUT/DELETE /coaches/me/subscription-plans` | Billing |
| `rating` e `reviews_count` nel profilo pubblico | Reviews |
| `nextBooking` nel listing clienti | Bookings |
| Storico sessioni nella scheda cliente | Bookings |
| Storico pagamenti nella scheda cliente | Billing |
| `POST /auth/accept-invite/:token` (onboarding cliente) | Auth (endpoint aggiuntivo) |
| Cron jobs (expiring subscriptions, inactive clients, checkin reminders) | Notifications |
| Push notifications FCM | Notifications |
| `CheckinFormAssignment` | Forms/Checkins |

---

## Gap da colmare nel modulo Users — Gestione Clienti

### Riepilogo migrazioni necessarie

| Migration | Tipo |
|---|---|
| `add_client_fields_to_clients_table` | ALTER TABLE clients (tags JSON, phone, avatar_url, subscription_expires_at, subscription_sessions_remaining) |
| `create_client_notes_table` | CREATE TABLE |
| `create_client_anamnesis_table` | CREATE TABLE (sostituisce colonna `anamnesi` su clients) |
| `create_client_files_table` | CREATE TABLE |
| `drop_anamnesi_from_clients_table` | ALTER TABLE clients (rimuove vecchia colonna `anamnesi` dopo migrazione dati) |

---

### 1. `GET /api/v1/coaches/me/clients` — Incompleto

**Stato:** ⚠️ Esiste ma mancano filtri, campi e ordinamento

#### Campi mancanti nella risposta
La risposta attuale non include:
- `firstName`, `lastName`, `email` (da `users` join) — i campi sono nella relazione `user` ma non esposti flat
- `phone` — colonna mancante sulla tabella `clients`
- `tags` — colonna JSON mancante sulla tabella `clients`
- `avatarUrl` — colonna `avatar_url` mancante su `clients`
- `subscriptionExpiresAt` — colonna `subscription_expires_at` mancante (scritta da modulo Billing)
- `subscriptionSessionsRemaining` — colonna `subscription_sessions_remaining` mancante (scritta da Billing)
- `nextBooking` — **fuori scope Users**, viene da modulo Bookings

#### Query params mancanti
- `status` — filtro per `clients.status`
- `tags[]` — filtro: `whereJsonContains('tags', $tag)` per ogni tag
- `expiresWithin` (giorni) — filtro: `subscription_expires_at <= now() + N days`
- `search` — LIKE su `users.name` o `users.email`
- `page`, `limit` (default 20) — paginazione (già implementata, limite hardcoded a 20)

#### Ordinamento
- Attuale: `latest('joined_at')` ❌
- Spec: `subscription_expires_at ASC` di default (clienti in scadenza prima)

#### Da fare
- Migration: aggiungere `tags` (JSON), `phone`, `avatar_url`, `subscription_expires_at`, `subscription_sessions_remaining` alla tabella `clients`
- Aggiornare `Client::$fillable` e `Client::casts()`
- Aggiornare `CoachController::clients()` con filtri, ordinamento e query params
- Aggiornare `ClientResource` per esporre i nuovi campi

---

### 2. `POST /api/v1/coaches/me/clients` — Mancante come endpoint

**Stato:** ❌ `CreateClientAction` esiste ma non ha route né FormRequest

**Logica da implementare:**
1. Valida i dati in ingresso (`CreateClientRequest`)
2. Cerca `User` per email:
   - **Se esiste**: usa l'utente esistente, crea `Client` collegato al coach
   - **Se non esiste**: crea `User` con `email_verified_at = null`, genera token JWT (TTL 7gg) che codifica `{coach_id, email, exp}`, invia email con link `/accept-invite/{token}`
3. `AuditLogService::log('CLIENT_CREATED', $coach->user_id)`
4. Ritorna 201 con `ClientResource`

**FormRequest** `CreateClientRequest`:
- `email` — `required|email`
- `firstName` — `required|string|max:255`
- `lastName` — `required|string|max:255`
- `phone` — `sometimes|nullable|regex:/^[+]?[\d\s\-()] {7,20}$/`
- `tags` — `sometimes|nullable|array`
- `tags.*` — `string|max:50`
- `birth_date` — `sometimes|nullable|date`
- `gender` — `sometimes|nullable|in:male,female,other`
- `height_cm` — `sometimes|nullable|numeric|min:50|max:300`
- `weight_kg` — `sometimes|nullable|numeric|min:20|max:500`

**Aggiornamento `CreateClientAction`**: aggiungere email lookup + invite email + audit log

---

### 3. `GET /api/v1/coaches/me/clients/:id` — Mancante

**Stato:** ❌ Nessun endpoint

**Risposta attesa (scheda completa):**
- Dati base del cliente + utente
- `tags`, `internalNotes` (lista `ClientNote` — vedi §6)
- `anamnesis` — testo decifrato in runtime con `APP_ENCRYPTION_KEY` (da `ClientAnamnesis` — vedi §7)
- Ultime 10 sessioni — **fuori scope Users** → placeholders vuoti, popolati da Bookings
- Prossima sessione — **fuori scope Users** → null, popolato da Bookings
- `subscriptionExpiresAt`, `subscriptionSessionsRemaining`
- Storico pagamenti — **fuori scope Users** → array vuoto, popolato da Billing
- Files (`ClientFile[]`) — vedi §8

**Da fare:** route + metodo `show` in `ClientController` + `ClientDetailResource`

---

### 4. `PUT /api/v1/coaches/me/clients/:id` — Mancante come route

**Stato:** ❌ `UpdateClientProfileAction` esiste ma non ha route

**Da fare:**
- Registrare route `PUT /api/v1/coaches/me/clients/{client}`
- Aggiungere metodo `update` a `ClientController`
- Aggiungere `AuditLogService::log('CLIENT_UPDATED', $coach->user_id, ['client_id' => $client->id])`
- Autorizzazione: verificare `$client->coach_id === $coach->id`

---

### 5. `DELETE /api/v1/coaches/me/clients/:id` — Mancante

**Stato:** ❌ Nessun endpoint

**Da fare:**
- Registrare route `DELETE /api/v1/coaches/me/clients/{client}`
- Aggiungere metodo `destroy` a `ClientController`
- Soft delete: `$client->delete()` (il modello usa `SoftDeletes`)
- `AuditLogService::log('CLIENT_DELETED', $coach->user_id, ['client_id' => $client->id])`
- Ritorna 204

---

### 6. `POST /DELETE /api/v1/coaches/me/clients/:id/tags` — Mancante

**Stato:** ❌ Nessun endpoint, nessuna struttura dati

**Schema:** `tags` è una colonna JSON sull'entità `clients` (array di stringhe)

**Migration:** aggiungere `tags JSON nullable` a `clients` (vedi §1)

**Da fare:**
- `POST /coaches/me/clients/{client}/tags` — aggiunge un tag all'array JSON
  - Body: `{ tag: string }` — max 50 caratteri
  - Autorizzazione: `$client->coach_id === $coach->id`
- `DELETE /coaches/me/clients/{client}/tags/{tag}` — rimuove tag dall'array
  - Autorizzazione: stessa

**Controller:** nuovo `ClientTagController` con `store` e `destroy`

---

### 7. `POST /api/v1/coaches/me/clients/:id/notes` — Mancante

**Stato:** ❌ Nessun endpoint, nessun modello

**Migration** — creare tabella `client_notes`:
```
id, coach_id (FK coaches), client_id (FK clients), content (text),
created_at (solo created, immutabile — no updated_at)
```

**Modello** `ClientNote`:
- `$fillable`: `content` (coach_id e client_id assegnati dall'Action)
- Relazione `belongsTo(Client::class)`, `belongsTo(Coach::class)`
- `$timestamps = false` + aggiungi `created_at` manualmente (record immutabili)

**Relazione** su `Client`: `hasMany(ClientNote::class)` — nelle ultime 10

**Da fare:**
- `POST /coaches/me/clients/{client}/notes` — aggiunge nota
  - Body: `{ content: string }` — required, max 5000 caratteri
  - Salva `coach_id` dall'auth, non modificabile
- **Controller:** `ClientNoteController::store`

---

### 8. `PUT /api/v1/coaches/me/clients/:id/anamnesis` — Incompleto

**Stato:** ⚠️ La colonna `anamnesi` su `clients` usa Laravel `'encrypted'` cast, ma la spec richiede AES-256-GCM con IV random e modello separato

**Migration** — creare tabella `client_anamnesis`:
```
id, client_id (FK clients, unique — una per cliente), content_encrypted (text),
iv (string 32), created_at, updated_at
```

**Modello** `ClientAnamnesis`:
- `$fillable`: `content_encrypted`, `iv`
- `$hidden`: `content_encrypted`, `iv` (mai esposti in JSON)
- Relazione `belongsTo(Client::class)`

**Relazione** su `Client`: `hasOne(ClientAnamnesis::class)`

**Servizio** `EncryptionService`:
- `encrypt(string $plaintext, string $key): array` → `['ciphertext' => ..., 'iv' => ...]`
  - Usa `openssl_encrypt($plaintext, 'aes-256-gcm', $key, iv: $iv)` con IV random (`random_bytes(12)`, base64 encode)
- `decrypt(string $ciphertext, string $iv, string $key): string`

**Logica `UpdateClientAnamnesisAction`:**
1. Genera IV random (`random_bytes(12)`) → base64 encode
2. Cifra `content` con `EncryptionService::encrypt(content, APP_ENCRYPTION_KEY)`
3. `updateOrCreate(['client_id' => $client->id], ['content_encrypted' => ..., 'iv' => ...])`
4. `AuditLogService::log('ANAMNESIS_UPDATED', $coach->user_id, ['client_id' => $client->id])` — MAI loggare il contenuto

**Lettura in `GET /coaches/me/clients/:id`:**
- Decifra in runtime: `EncryptionService::decrypt(content_encrypted, iv, APP_ENCRYPTION_KEY)`
- Espone come campo `anamnesis` nel `ClientDetailResource`

**Migration di cleanup** — dopo migrazione dati:
- `drop_anamnesi_from_clients_table` — rimuove vecchia colonna `clients.anamnesi`

---

### 9. `POST /api/v1/coaches/me/clients/:id/files` — Mancante

**Stato:** ❌ Nessun endpoint, nessun modello

**Migration** — creare tabella `client_files`:
```
id, coach_id (FK coaches), client_id (FK clients),
storage_key (string — path su R2), name (string), type (string),
size (unsignedBigInteger — byte), created_at, updated_at
```

**Modello** `ClientFile`:
- `$fillable`: `name`, `type`, `size`
- Campi protetti: `coach_id`, `client_id`, `storage_key` (assegnati dall'Action)

**Relazione** su `Client`: `hasMany(ClientFile::class)`

**Configurazione R2:**
- Usare il disco `r2_docs` già configurato in `config/filesystems.php`
- Path: `client-files/{coachId}/{clientId}/{uuid}.{ext}`
- **Visibilità: PRIVATA** — i file non sono mai accessibili pubblicamente (accesso via presigned URL)

**Tipi consentiti:** PDF, JPG, JPEG, PNG, MP4 — max 20MB

**FormRequest** `StoreClientFileRequest`:
- `file` — `required|file|mimes:pdf,jpg,jpeg,png,mp4|max:20480`
- `name` — `sometimes|nullable|string|max:255` (default: nome originale file)

**Action** `StoreClientFileAction`:
- Usa `ObjectStorageService::upload($file, 'client-files/{coachId}/{clientId}')` MA con visibilità **privata** (non aggiungere `'public'` come terzo arg)
- Salva storage_key (non CDN URL — i file client sono privati)
- Ritorna 201 con `ClientFileResource`

> **Nota:** `ObjectStorageService::upload()` imposta `'public'` per le certificazioni coach (CDN pubblico). Per i file cliente bisogna usare `put($path, $content)` senza visibilità pubblica, oppure aggiungere un parametro `visibility` all'`ObjectStorageService`.

---

### 10. `GET /api/v1/coaches/me/clients/:id/files/:fileId/download` — Mancante

**Stato:** ❌ Nessun endpoint

**Logica:**
1. Autorizzazione: `$clientFile->client->coach_id === $coach->id`
2. Genera presigned URL con TTL 15 minuti:
   ```php
   Storage::disk('r2_docs')->temporaryUrl($clientFile->storage_key, now()->addMinutes(15));
   ```
3. Risposta: `{ url: "https://...", expires_at: "ISO8601" }`

**Controller:** `ClientFileController` con metodi `store` e `download`

---

## Servizi da aggiungere al modulo Users

| Servizio | Responsabilità |
|---|---|
| `EncryptionService` | AES-256-GCM encrypt/decrypt con IV random, usa `APP_ENCRYPTION_KEY` |

> Aggiornare `ObjectStorageService::upload()` per accettare parametro `visibility` (default `'public'`; per file cliente passare `'private'`).

---

## Riepilogo migrazioni necessarie

| Migration | Tipo |
|---|---|
| `add_client_fields_to_clients_table` | ALTER TABLE clients (tags JSON, phone, avatar_url, subscription_expires_at, subscription_sessions_remaining) |
| `create_client_notes_table` | CREATE TABLE |
| `create_client_anamnesis_table` | CREATE TABLE |
| `create_client_files_table` | CREATE TABLE |
| `drop_anamnesi_from_clients_table` | ALTER TABLE (dopo migrazione dati da colonna `anamnesi` a tabella `client_anamnesis`) |
