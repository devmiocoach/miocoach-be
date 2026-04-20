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
| `GET /api/v1/coaches/me/clients` | ✅ Implementato |
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

---

## Endpoint precedentemente mancanti (ora implementati)

### 1. `GET /api/v1/coaches/me`
**Stato:** ❌ Mancante  
Il `CoachController` non ha un metodo `show`/`me`. Attualmente per vedere il proprio profilo coach bisogna chiamare `GET /users/me` che carica la relazione `coach`, ma non restituisce tutti i campi del profilo coach in modo dedicato.

**Da fare:**
- Aggiungere metodo `show` in `CoachController`
- Registrare route `GET /api/v1/coaches/me`
- Il `CoachResource` esistente è già completo per questo scopo

---

### 2. `PUT /api/v1/coaches/me` — campi e logica mancanti
**Stato:** ⚠️ Parzialmente implementato (esiste come `PATCH`)

#### Colonne mancanti nella tabella `coaches`
Serve una nuova migration che aggiunga:

| Campo | Tipo | Note |
|---|---|---|
| `tagline` | `string` nullable | Frase breve del coach |
| `mode` | `enum('online','in_person','hybrid')` nullable | Modalità di lavoro |
| `intro_video_url` | `string` nullable | URL video di presentazione |
| `price_per_session` | `decimal(8,2)` nullable | Tariffa a sessione (distinta da `hourly_rate`) |
| `cancellation_window_hours` | `unsignedTinyInteger` nullable | Ore minime per cancellazione |
| `lat` | `decimal(10,7)` nullable | Latitudine (da geocoding) |
| `lng` | `decimal(10,7)` nullable | Longitudine (da geocoding) |
| `is_published` | `boolean` default `false` | Profilo pubblicato nel marketplace (diverso da `is_visible`) |

> **Nota:** `displayName` dalla spec mappa su `users.name`. Gestire l'aggiornamento anche del campo `name` dell'utente dall'endpoint `PUT /coaches/me`.

#### Campi mancanti nel FormRequest `UpdateCoachProfileRequest`
Aggiungere validazione per:
- `tagline` — `sometimes|nullable|string|max:150`
- `mode` — `sometimes|nullable|in:online,in_person,hybrid`
- `intro_video_url` — `sometimes|nullable|url|max:255`
- `price_per_session` — `sometimes|nullable|numeric|min:0|max:9999.99`
- `cancellation_window_hours` — `sometimes|nullable|integer|min:0|max:168`
- `display_name` (mappato su `users.name`) — `sometimes|nullable|string|max:255`
- `instagram_url` — campo top-level (spec usa `instagramUrl`), attualmente annidato in `social_links.instagram`

#### Logica mancante in `UpdateCoachProfileAction`
1. **Geocoding Nominatim**: quando `city` cambia, chiamare `https://nominatim.openstreetmap.org/search?q={city}&format=json` e salvare `lat`/`lng` su `coaches`
2. **Slug auto-generazione**: se il coach non ha ancora uno slug, generarlo come `slugify(displayName + "-" + city)` con dedup tramite contatore (`-1`, `-2`, ...)
3. **Audit log**: dopo l'update chiamare `AuditLogService::log('PROFILE_UPDATED', $coach->user_id)` — l'`AuditLogService` esiste già nel modulo Auth

#### Aggiornamenti a `CoachResource`
Aggiungere i nuovi campi nel resource: `tagline`, `mode`, `intro_video_url`, `price_per_session`, `cancellation_window_hours`, `lat`, `lng`, `is_published`

---

### 3. `PUT /api/v1/coaches/me/publish`
**Stato:** ❌ Mancante completamente

**Da fare:**
- Aggiungere metodo `publish` in `CoachController`
- Registrare route `PUT /api/v1/coaches/me/publish`
- Logica: verificare i campi minimi (`bio`, `specializations` non vuoto, `city`, `price_per_session`), poi impostare `is_published = true`
- Restituire errore `422` con lista campi mancanti se la verifica fallisce

---

### 4. `PUT /api/v1/coaches/me/availability`
**Stato:** ❌ Mancante completamente

**Da fare:**

**Migration** — creare tabella `coach_availabilities`:
```
id, coach_id (FK coaches), day_of_week (tinyInt 0-6), start_time (time),
end_time (time), duration_minutes (unsignedSmallInteger), is_recurring (boolean),
timestamps
```

**Modello** `CoachAvailability` con relazione `belongsTo(Coach::class)`

**Relazione** su `Coach`: `hasMany(CoachAvailability::class)`

**Controller** `AvailabilityController` (o metodo `updateAvailability` in `CoachController`) con:
- Ricezione array `slots[]`
- Replace completo: `DELETE` tutti gli slot esistenti per `coach_id`, poi `INSERT` i nuovi
- Wrappato in transazione DB

**FormRequest** `UpdateAvailabilityRequest`:
- `slots` — `required|array`
- `slots.*.day_of_week` — `required|integer|between:0,6`
- `slots.*.start_time` — `required|date_format:H:i`
- `slots.*.end_time` — `required|date_format:H:i|after:slots.*.start_time`
- `slots.*.duration_minutes` — `required|integer|min:15|max:240`
- `slots.*.is_recurring` — `required|boolean`

**Resource** `CoachAvailabilityResource`

---

### 5. `POST /api/v1/coaches/me/certifications`
**Stato:** ❌ Mancante (attualmente le certificazioni sono JSON nella colonna `coaches.certifications`)

Per supportare `DELETE /coaches/me/certifications/:id` ogni certificazione deve avere un ID persistente → serve una tabella dedicata.

**Da fare:**

**Migration** — creare tabella `certifications`:
```
id, coach_id (FK coaches), name (string), issuer (string nullable),
issued_at (date nullable), file_url (string), verified_at (timestamp nullable),
created_at, updated_at
```

**Modello** `Certification` con relazione `belongsTo(Coach::class)`

**Relazione** su `Coach`: `hasMany(Certification::class)`

**Controller** `CertificationController` con metodi `store` e `destroy`

**FormRequest** `StoreCertificationRequest`:
- `file` — `required|file|mimes:pdf,jpg,jpeg,png|max:10240`
- `name` — `required|string|max:255`
- `issuer` — `nullable|string|max:255`
- `issued_at` — `nullable|date`

**Servizio** `ObjectStorageService`:
- Upload su Aruba Object Storage (S3-compatible) nel bucket `documents-prod`
- Path: `certifications/{coachId}/{uuid}.{ext}`
- `file_url` salvato = URL CDN Cloudflare (non URL diretto Object Storage)
- Configurazione via env: `OBJECT_STORAGE_KEY`, `OBJECT_STORAGE_SECRET`, `OBJECT_STORAGE_ENDPOINT`, `OBJECT_STORAGE_BUCKET`, `CDN_BASE_URL`

**Nota:** rimuovere la colonna `certifications` (JSON) dalla tabella `coaches` dopo la migrazione dei dati.

---

### 6. `DELETE /api/v1/coaches/me/certifications/{id}`
**Stato:** ❌ Mancante

**Da fare** (insieme al punto 5):
- Metodo `destroy` in `CertificationController`
- Autorizzazione: solo il proprietario può eliminare (`$coach->id === $certification->coach_id`)
- Delete da DB + eliminazione file da Object Storage tramite `ObjectStorageService`

---

### 7. `GET /api/v1/coaches/{id}/public`
**Stato:** ❌ Mancante completamente

**Da fare:**
- Aggiungere metodo `publicProfile` in `CoachController` (o controller dedicato `PublicCoachController`)
- Route **senza auth**: `GET /api/v1/coaches/{id}/public`
- Risposta:
  - Campi pubblici: `displayName`, `bio`, `tagline`, `specializations`, `mode`, `city`, `rating` medio, `reviews_count`, `price_per_session`, `price_per_hour`
  - Certificazioni: solo quelle con `verified_at` non null
  - Prossimi 5 slot disponibili: richiedono la relazione con `coach_availabilities`
  - Metadata SEO nel campo `seo`: `{ seoTitle, seoDescription, ogImage }`

**Resource** `PublicCoachResource` (separata da `CoachResource` per non esporre campi privati)

> **Dipendenza:** `rating` e `reviews_count` vengono dal modulo **Reviews** (ancora da costruire, vedi `MODULES_TODO.md`)

---

## Servizi da aggiungere al modulo Users

| Servizio | Responsabilità |
|---|---|
| `GeocodingService` | Chiama Nominatim, restituisce `[lat, lng]` per una città |
| `ObjectStorageService` | Upload/delete file su Aruba Object Storage S3-compatible |
| `SlugService` | `slugify(text)` + dedup con contatore |

---

## Riepilogo migrazioni necessarie

| Migration | Tipo |
|---|---|
| `add_missing_fields_to_coaches_table` | ALTER TABLE coaches (tagline, mode, intro_video_url, price_per_session, cancellation_window_hours, lat, lng, is_published) |
| `create_coach_availabilities_table` | CREATE TABLE |
| `create_certifications_table` | CREATE TABLE |
| `drop_certifications_json_from_coaches_table` | ALTER TABLE (dopo migrazione dati JSON) |
